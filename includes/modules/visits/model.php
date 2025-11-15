<?php
/**
 * Visits Module Model
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Visits_Model extends SAW_Base_Model 
{
    /**
     * Constructor - Initialize model with config
     * 
     * Sets up table name, configuration, and cache TTL.
     * 
     * @since 1.0.0
     * @param array $config Module configuration array
     */
    public function __construct($config) {
        global $wpdb;
        
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 300;
    }
    
    /**
     * Validate company data
     * 
     * Validates all required fields and business rules:
     * - customer_id must be present
     * - branch_id must be present
     * - name must be present
     * - email must be valid if provided
     * - website must be valid URL if provided
     * - IČO must be unique within customer (if provided)
     * 
     * @since 1.0.0
     * @param array $data Company data to validate
     * @param int $id Company ID (for update validation, 0 for create)
     * @return bool|WP_Error True if valid, WP_Error if validation fails
     */
    public function validate($data, $id = 0) {
        $errors = array();
        
        if (empty($data['customer_id'])) {
            $errors['customer_id'] = 'Customer ID is required';
        }
        
        if (empty($data['branch_id'])) {
            $errors['branch_id'] = 'Branch is required';
        }
        
        if (empty($data['company_id'])) {
            $errors['company_id'] = 'Company is required';
        }
        
        if (!empty($data['invitation_email']) && !is_email($data['invitation_email'])) {
            $errors['email'] = 'Invalid email format';
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validation failed', $errors);
    }
    
    /**
     * Check if IČO already exists
     * 
     * Verifies uniqueness of IČO within the same customer.
     * Empty IČO values are considered valid (not checked).
     * 
     * @since 1.0.0
     * @param int $customer_id Customer ID
     * @param string $ico IČO to check
     * @param int $exclude_id Company ID to exclude from check (for updates)
     * @return bool True if IČO exists, false otherwise
     */
    public function get_by_id($id) {
        $cache_key = sprintf('saw_visits_item_%d', $id);
        $item = get_transient($cache_key);
        
        if ($item === false) {
            $item = parent::get_by_id($id);
            
            if ($item) {
                set_transient($cache_key, $item, $this->cache_ttl);
            }
        }
        
        if (!$item) {
            return null;
        }
        
        $current_customer_id = SAW_Context::get_customer_id();
        
        if (!current_user_can('manage_options')) {
            if (empty($item['customer_id']) || $item['customer_id'] != $current_customer_id) {
                return null;
            }
        }
        
        return $item;
    }
    
    /**
     * Get all companies - COMPLETE OVERRIDE with forced filtering
     * 
     * CRITICAL: This method COMPLETELY BYPASSES parent::get_all() and apply_data_scope()
     * to ensure ALL users (including super_admin) are filtered by customer_id and branch_id.
     * 
     * @since 1.0.0
     * @param array $filters Query filters
     * @return array ['items' => array, 'total' => int]
     */
    public function get_all($filters = array()) {
        global $wpdb;
        
        $customer_id = SAW_Context::get_customer_id();
        $branch_id = SAW_Context::get_branch_id();
        
        // ✅ ALWAYS require customer_id (even for super_admin)
        if (!$customer_id) {
            return array('items' => array(), 'total' => 0);
        }
        
        // Build cache key
        $cache_key = sprintf(
            'saw_visits_list_%d_%s_%s',
            $customer_id,
            $branch_id ? $branch_id : 'all',
            md5(serialize($filters))
        );
        
        // Try cache first
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        // ✅ Build query with FORCED customer and branch filtering
        $sql = $wpdb->prepare(
            "SELECT v.*, c.name as company_name,
                    DATE(v.planned_date_from) as planned_date_from,
                    TIME_FORMAT(v.planned_date_from, '%%H:%%i') as planned_time_from,
                    DATE(v.planned_date_to) as planned_date_to,
                    TIME_FORMAT(v.planned_date_to, '%%H:%%i') as planned_time_to
             FROM %i v 
             LEFT JOIN %i c ON v.company_id = c.id 
             WHERE v.customer_id = %d",
            $this->table,
            $wpdb->prefix . 'saw_companies',
            $customer_id
        );
        $params = array();
        
        // ✅ FORCE branch filter if branch is selected
        if ($branch_id) {
            $sql .= " AND v.branch_id = %d";
            $params[] = $branch_id;
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $search_fields = $this->config['list_config']['searchable'] ?? array('name');
            $search_conditions = array();
            
            foreach ($search_fields as $field) {
                $search_conditions[] = "`{$field}` LIKE %s";
            }
            
            if (!empty($search_conditions)) {
                $search_value = '%' . $wpdb->esc_like($filters['search']) . '%';
                foreach ($search_conditions as $condition) {
                    $params[] = $search_value;
                }
                $sql .= " AND (" . implode(' OR ', $search_conditions) . ")";
            }
        }
        
        // is_archived filter
        if (isset($filters['is_archived']) && $filters['is_archived'] !== '') {
            $sql .= " AND is_archived = %d";
            $params[] = intval($filters['is_archived']);
        }
        
        // Count total
        $count_sql = str_replace('SELECT v.*, c.name as company_name,
                    DATE(v.planned_date_from) as planned_date_from,
                    TIME_FORMAT(v.planned_date_from, \'%H:%i\') as planned_time_from,
                    DATE(v.planned_date_to) as planned_date_to,
                    TIME_FORMAT(v.planned_date_to, \'%H:%i\') as planned_time_to', 'SELECT COUNT(*)', $sql);
        if (!empty($params)) {
            $count_sql = $wpdb->prepare($count_sql, ...$params);
        }
        $total = (int) $wpdb->get_var($count_sql);
        
        $orderby = $filters['orderby'] ?? 'id';
        $order = strtoupper($filters['order'] ?? 'DESC');
        
        if (!in_array($orderby, array('id', 'planned_date_from'), true)) {
            $orderby = 'id';
        }
        if (!in_array($order, array('ASC', 'DESC'), true)) {
            $order = 'DESC';
        }
        
        $sql .= " ORDER BY v.`{$orderby}` {$order}";
        
        // Pagination
        $page = isset($filters['page']) ? max(1, intval($filters['page'])) : 1;
        $per_page = isset($filters['per_page']) ? intval($filters['per_page']) : 20;
        $offset = ($page - 1) * $per_page;
        
        $sql .= " LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        
        // Execute query
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }
        
        $items = $wpdb->get_results($sql, ARRAY_A);
        
        $result = array(
            'items' => $items ?: array(),
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => $per_page > 0 ? ceil($total / $per_page) : 0,
        );
        
        // Cache for 5 minutes
        set_transient($cache_key, $result, $this->cache_ttl);
        
        return $result;
    }
    
    /**
     * Create new company with cache invalidation
     * 
     * Creates a new company record and invalidates relevant caches.
     * 
     * @since 1.0.0
     * @param array $data Company data
     * @return int|WP_Error New company ID or error
     */
    public function create($data) {
        $result = parent::create($data);
        
        if (!is_wp_error($result)) {
            $this->invalidate_list_cache();
        }
        
        return $result;
    }
    
    /**
     * Update existing company with cache invalidation
     * 
     * Updates a company record and invalidates relevant caches.
     * 
     * @since 1.0.0
     * @param int $id Company ID
     * @param array $data Company data
     * @return bool|WP_Error True on success, error on failure
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
     * Delete company with cache invalidation
     * 
     * Deletes a company record and invalidates relevant caches.
     * 
     * @since 1.0.0
     * @param int $id Company ID
     * @return bool|WP_Error True on success, error on failure
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
     * Invalidate item cache
     * 
     * Removes cached data for a specific company.
     * 
     * @since 1.0.0
     * @param int $id Company ID
     * @return void
     */
    private function invalidate_item_cache($id) {
        $cache_key = sprintf('saw_visits_item_%d', $id);
        delete_transient($cache_key);
    }
    
    /**
     * Invalidate list cache
     * 
     * Removes all cached company lists.
     * Uses wildcard pattern to clear all list variations.
     * 
     * @since 1.0.0
     * @return void
     */
    private function invalidate_list_cache() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_saw_visits_list_%' 
             OR option_name LIKE '_transient_timeout_saw_visits_list_%'"
        );
    }
    
    
    public function save_hosts($visit_id, $user_ids) {
        global $wpdb;
        
        $wpdb->delete(
            $wpdb->prefix . 'saw_visit_hosts',
            array('visit_id' => $visit_id)
        );
        
        if (!empty($user_ids)) {
            foreach ($user_ids as $user_id) {
                $wpdb->insert(
                    $wpdb->prefix . 'saw_visit_hosts',
                    array(
                        'visit_id' => $visit_id,
                        'user_id' => $user_id,
                    )
                );
            }
        }
    }
    
    public function get_hosts($visit_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT u.id, u.first_name, u.last_name, u.email
             FROM %i vh
             INNER JOIN %i u ON vh.user_id = u.id
             WHERE vh.visit_id = %d
             ORDER BY u.last_name, u.first_name",
            $wpdb->prefix . 'saw_visit_hosts',
            $wpdb->prefix . 'saw_users',
            $visit_id
        ), ARRAY_A);
    }
}
