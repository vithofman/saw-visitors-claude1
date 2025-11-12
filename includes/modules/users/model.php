<?php
/**
 * Users Module Model
 * 
 * Handles all database operations for the Users module including
 * CRUD operations, validation, customer isolation, and caching.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Users
 * @version     5.0.0 - COMPLETE with customer/branch filtering like departments
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Users_Model extends SAW_Base_Model 
{
    /**
     * Constructor - Initialize model with config
     */
    public function __construct($config) {
        global $wpdb;
        
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 1800;
    }
    
    /**
     * Override create() - auto-fill customer_id
     */
    public function create($data) {
        if (empty($data['customer_id']) && isset($data['role']) && $data['role'] !== 'super_admin') {
            $data['customer_id'] = SAW_Context::get_customer_id();
            
            if (!$data['customer_id']) {
                return new WP_Error('missing_customer', 'Customer ID is required for this role');
            }
        }
        
        if (isset($data['role']) && $data['role'] === 'super_admin') {
            $data['customer_id'] = null;
        }
        
        $result = parent::create($data);
        
        if (!is_wp_error($result)) {
            $this->invalidate_list_cache();
        }
        
        return $result;
    }
    
    /**
     * Override update() - invalidate caches
     */
    public function update($id, $data) {
        $result = parent::update($id, $data);
        
        if (!is_wp_error($result)) {
            $this->invalidate_item_cache($id);
            $this->invalidate_list_cache();
        }
        
        return $result;
    }
    
    /**
     * Override delete() - invalidate caches
     */
    public function delete($id) {
        $result = parent::delete($id);
        
        if (!is_wp_error($result)) {
            $this->invalidate_item_cache($id);
            $this->invalidate_list_cache();
        }
        
        return $result;
    }
    
    /**
     * Get all users with customer and branch isolation
     * 
     * FILTERING RULES:
     * 1. Super Admin (WP admin role) → sees EVERYONE
     * 2. Others → see users with matching customer_id (from context) AND optionally branch_id
     * 3. Users with branch_id=NULL are visible across all branches of that customer
     * 4. Never show super_admin role users to non-super-admins
     * 
     * @param array $filters Query filters
     * @return array Array with 'items' and 'total' keys
     */
    public function get_all($filters = array()) {
        global $wpdb;
        
        // Check if current WP user is super admin
        $current_wp_user = wp_get_current_user();
        $is_wp_super_admin = in_array('administrator', $current_wp_user->roles, true);
        
        // Super admin sees everyone - use parent method
        if ($is_wp_super_admin) {
            $cache_key = 'saw_users_list_superadmin_' . md5(serialize($filters));
            $data = get_transient($cache_key);
            
            if ($data === false) {
                $data = parent::get_all($filters);
                set_transient($cache_key, $data, $this->cache_ttl);
            }
            
            return $data;
        }
        
        // Get current context from switcher
        $context_customer_id = SAW_Context::get_customer_id();
        $context_branch_id = SAW_Context::get_branch_id();
        
        if (!$context_customer_id) {
            return array('items' => array(), 'total' => 0);
        }
        
        // Build base query
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $count_sql = "SELECT COUNT(*) FROM {$this->table} WHERE 1=1";
        
        // Filter by customer_id (exclude super_admin role)
        $sql .= $wpdb->prepare(" AND customer_id = %d AND role != 'super_admin'", $context_customer_id);
        $count_sql .= $wpdb->prepare(" AND customer_id = %d AND role != 'super_admin'", $context_customer_id);
        
        // Filter by branch_id if selected (include NULL branch_id - those are visible everywhere)
        if ($context_branch_id) {
            $sql .= $wpdb->prepare(" AND (branch_id = %d OR branch_id IS NULL)", $context_branch_id);
            $count_sql .= $wpdb->prepare(" AND (branch_id = %d OR branch_id IS NULL)", $context_branch_id);
        }
        
        // Apply search filter
        if (!empty($filters['search'])) {
            $search_fields = $this->config['list_config']['searchable'] ?? array('first_name', 'last_name', 'email');
            $search_conditions = array();
            $search_value = '%' . $wpdb->esc_like($filters['search']) . '%';
            
            foreach ($search_fields as $field) {
                $search_conditions[] = $wpdb->prepare("{$field} LIKE %s", $search_value);
            }
            
            $search_where = ' AND (' . implode(' OR ', $search_conditions) . ')';
            $sql .= $search_where;
            $count_sql .= $search_where;
        }
        
        // Apply additional filters (role, is_active, etc.)
        foreach ($this->config['list_config']['filters'] ?? array() as $filter_key => $enabled) {
            if ($enabled && isset($filters[$filter_key]) && $filters[$filter_key] !== '') {
                $sql .= $wpdb->prepare(" AND {$filter_key} = %s", $filters[$filter_key]);
                $count_sql .= $wpdb->prepare(" AND {$filter_key} = %s", $filters[$filter_key]);
            }
        }
        
        // Get total count
        $total = (int) $wpdb->get_var($count_sql);
        
        // Apply ordering
        $orderby = $filters['orderby'] ?? 'first_name';
        $order = strtoupper($filters['order'] ?? 'ASC');
        
        if (!in_array($order, array('ASC', 'DESC'))) {
            $order = 'ASC';
        }
        
        $sql .= " ORDER BY {$orderby} {$order}";
        
        // Apply pagination
        $page = isset($filters['page']) ? max(1, intval($filters['page'])) : 1;
        $per_page = isset($filters['per_page']) ? max(1, intval($filters['per_page'])) : 20;
        $offset = ($page - 1) * $per_page;
        
        $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $per_page, $offset);
        
        // Execute query
        $items = $wpdb->get_results($sql, ARRAY_A);
        
        return array(
            'items' => $items ?: array(),
            'total' => $total
        );
    }
    
    /**
     * Get user by ID with formatting and isolation check
     * 
     * Retrieves a single user record by ID, validates customer isolation,
     * and formats the data for display (branch name, role labels, dates).
     * Loads department_ids for managers.
     * Uses transient cache with 30 minute TTL.
     * 
     * @param int $id User ID
     * @return array|null User data or null if not found/no access
     */
    public function get_by_id($id) {
        // Try cache first
        $cache_key = sprintf('saw_users_item_%d', $id);
        $item = get_transient($cache_key);
        
        if ($item === false) {
            // Cache miss - fetch from database
            $item = parent::get_by_id($id);
            
            if ($item) {
                // Cache for 30 minutes
                set_transient($cache_key, $item, $this->cache_ttl);
            }
        }
        
        if (!$item) {
            return null;
        }
        
        // Customer isolation check
        $current_customer_id = SAW_Context::get_customer_id();
        
        // Super admin can see all users
        if (!current_user_can('manage_options')) {
            if (!empty($item['customer_id']) && $item['customer_id'] != $current_customer_id) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        '[USERS] Isolation violation - Item customer: %s, Current: %s',
                        $item['customer_id'] ?? 'NULL',
                        $current_customer_id ?? 'NULL'
                    ));
                }
                return null;
            }
        }
        
        // Load departments for managers
        if (isset($item['role']) && $item['role'] === 'manager') {
            global $wpdb;
            $departments = $wpdb->get_results($wpdb->prepare(
                "SELECT department_id FROM %i WHERE user_id = %d",
                $wpdb->prefix . 'saw_user_departments',
                $id
            ), ARRAY_A);
            
            $item['department_ids'] = array_column($departments, 'department_id');
        }
        
        // Format dates
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['created_at']));
        }
        
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['updated_at']));
        }
        
        if (!empty($item['last_login'])) {
            $item['last_login_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['last_login']));
        }
        
        return $item;
    }
    
    /**
     * Validate user data
     * 
     * Validates all required fields and business rules
     * 
     * @param array $data User data to validate
     * @param int $id User ID (for update validation, 0 for create)
     * @return bool|WP_Error True if valid, WP_Error if validation fails
     */
    public function validate($data, $id = 0) {
        $errors = array();
        
        if (empty($data['email'])) {
            $errors['email'] = 'Email je povinný';
        } elseif (!is_email($data['email'])) {
            $errors['email'] = 'Neplatný formát emailu';
        } elseif ($this->email_exists($data['email'], $id)) {
            $errors['email'] = 'Uživatel s tímto emailem již existuje';
        }
        
        if (empty($data['first_name'])) {
            $errors['first_name'] = 'Jméno je povinné';
        }
        
        if (empty($data['last_name'])) {
            $errors['last_name'] = 'Příjmení je povinné';
        }
        
        if (empty($data['role'])) {
            $errors['role'] = 'Role je povinná';
        }
        
        if (isset($data['role']) && $data['role'] === 'terminal' && !empty($data['pin'])) {
            if (!preg_match('/^\d{4}$/', $data['pin'])) {
                $errors['pin'] = 'PIN musí být 4 čísla';
            }
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validace selhala', $errors);
    }
    
    /**
     * Check if email already exists
     * 
     * @param string $email Email to check
     * @param int $exclude_id User ID to exclude from check (for updates)
     * @return bool True if email exists, false otherwise
     */
    private function email_exists($email, $exclude_id = 0) {
        global $wpdb;
        
        if (empty($email)) {
            return false;
        }
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE email = %s AND id != %d",
            $this->table,
            $email,
            $exclude_id
        );
        
        return (bool) $wpdb->get_var($query);
    }
    
    /**
     * Check if user is used in system
     * 
     * @param int $id User ID
     * @return bool True if used, false otherwise
     */
    public function is_used_in_system($id) {
        global $wpdb;
        
        $tables_to_check = array(
            'saw_visits' => 'created_by',
            'saw_invitations' => 'created_by',
        );
        
        foreach ($tables_to_check as $table => $column) {
            $full_table = $wpdb->prefix . $table;
            
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table)) !== $full_table) {
                continue;
            }
            
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE %i = %d",
                $full_table,
                $column,
                $id
            ));
            
            if ($count > 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get users by customer
     * 
     * @param int $customer_id Customer ID
     * @param bool $active_only Only active users
     * @return array Users data
     */
    public function get_by_customer($customer_id, $active_only = false) {
        $filters = array(
            'customer_id' => $customer_id,
            'orderby' => 'first_name',
            'order' => 'ASC',
        );
        
        if ($active_only) {
            $filters['is_active'] = 1;
        }
        
        return $this->get_all($filters);
    }
    
    /**
     * Invalidate item cache
     * 
     * @param int $id User ID
     */
    private function invalidate_item_cache($id) {
        $cache_key = sprintf('saw_users_item_%d', $id);
        delete_transient($cache_key);
    }
    
    /**
     * Invalidate list cache
     * 
     * Removes all cached user lists
     */
    private function invalidate_list_cache() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_saw_users_list_%' 
             OR option_name LIKE '_transient_timeout_saw_users_list_%'"
        );
    }
}