<?php
/**
 * Users Module Model - REFACTORED v3.0.0
 * 
 * ✅ Uses SAW_Context instead of sessions
 * ✅ Proper $wpdb->prepare()
 * ✅ Auto-fills customer_id
 * 
 * @package SAW_Visitors
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Users_Model extends SAW_Base_Model 
{
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
        // ✅ Auto-fill customer_id from context
        if (empty($data['customer_id']) && isset($data['role']) && $data['role'] !== 'super_admin') {
            $data['customer_id'] = SAW_Context::get_customer_id();
            
            if (!$data['customer_id']) {
                return new WP_Error('missing_customer', 'Customer ID is required for this role');
            }
        }
        
        // Super admin has no customer
        if (isset($data['role']) && $data['role'] === 'super_admin') {
            $data['customer_id'] = null;
        }
        
        return parent::create($data);
    }
    
    /**
     * Get all with proper scope
     */
    public function get_all($args = []) {
        $current_user = wp_get_current_user();
        $is_super_admin = in_array('administrator', $current_user->roles, true);
        
        // Super Admin sees everyone
        if ($is_super_admin && !empty($this->config['filter_by_customer'])) {
            return $this->get_all_for_super_admin($args);
        }
        
        // Others see only their customer (via parent scope)
        return parent::get_all($args);
    }
    
    /**
     * Special method for SuperAdmin
     */
    private function get_all_for_super_admin($filters = []) {
        global $wpdb;
        
        $sql = "SELECT * FROM %i WHERE 1=1";
        $params = [$this->table];
        
        if (!empty($filters['search'])) {
            $search_fields = $this->config['list_config']['searchable'] ?? ['first_name', 'last_name', 'email'];
            $search_conditions = [];
            
            foreach ($search_fields as $field) {
                $search_conditions[] = "{$field} LIKE %s";
            }
            
            $search_value = '%' . $wpdb->esc_like($filters['search']) . '%';
            
            foreach ($search_fields as $field) {
                $params[] = $search_value;
            }
            
            $sql .= " AND (" . implode(' OR ', $search_conditions) . ")";
        }
        
        foreach ($this->config['list_config']['filters'] ?? [] as $filter_key => $enabled) {
            if ($enabled && isset($filters[$filter_key]) && $filters[$filter_key] !== '') {
                $sql .= " AND {$filter_key} = %s";
                $params[] = $filters[$filter_key];
            }
        }
        
        $prepared_sql = $wpdb->prepare($sql, ...$params);
        
        $orderby = $filters['orderby'] ?? 'created_at';
        $order = strtoupper($filters['order'] ?? 'DESC');
        
        if (in_array($order, ['ASC', 'DESC'])) {
            $prepared_sql .= " ORDER BY {$orderby} {$order}";
        }
        
        $total_sql = "SELECT COUNT(*) FROM ({$prepared_sql}) as count_table";
        $total = $wpdb->get_var($total_sql);
        
        $limit = intval($filters['per_page'] ?? 20);
        $page = intval($filters['page'] ?? 1);
        $offset = ($page - 1) * $limit;
        
        $prepared_sql .= " LIMIT {$limit} OFFSET {$offset}";
        
        $results = $wpdb->get_results($prepared_sql, ARRAY_A);
        
        return [
            'items' => $results,
            'total' => $total
        ];
    }
    
    /**
     * Validate
     */
    public function validate($data, $id = 0) {
        $errors = [];
        
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
    
    public function get_by_id($id) {
        $user = parent::get_by_id($id);
        
        if (!$user) {
            return null;
        }
        
        if (isset($user['role']) && $user['role'] === 'manager') {
            global $wpdb;
            $departments = $wpdb->get_results($wpdb->prepare(
                "SELECT department_id FROM %i WHERE user_id = %d",
                $wpdb->prefix . 'saw_user_departments',
                $id
            ), ARRAY_A);
            
            $user['department_ids'] = array_column($departments, 'department_id');
        }
        
        return $user;
    }
    
    public function is_used_in_system($id) {
        global $wpdb;
        
        $tables_to_check = [
            'saw_visits' => 'created_by',
            'saw_invitations' => 'created_by',
        ];
        
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
    
    public function get_by_customer($customer_id, $active_only = false) {
        $filters = [
            'customer_id' => $customer_id,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ];
        
        if ($active_only) {
            $filters['is_active'] = 1;
        }
        
        return $this->get_all($filters);
    }
}