<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Users Module Model - FIXED with auto-fill customer_id
 * 
 * ✅ Auto-fills customer_id from session when creating users
 * ✅ Respects scope-based filtering
 * 
 * @package SAW_Visitors
 * @version 1.0.4
 */
class SAW_Module_Users_Model extends SAW_Base_Model 
{
    public function __construct($config) {
        global $wpdb;
        
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 1800;
    }
    
    /**
     * ✅ Override create() - auto-fill customer_id from session
     */
    public function create($data) {
        // ✅ Auto-fill customer_id from session if not provided
        if (empty($data['customer_id']) && isset($data['role']) && $data['role'] !== 'super_admin') {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $data['customer_id'] = $_SESSION['saw_current_customer_id'] ?? null;
            
            if (!$data['customer_id']) {
                return new WP_Error('missing_customer', 'Customer ID is required for this role');
            }
        }
        
        // Super admin has no customer
        if (isset($data['role']) && $data['role'] === 'super_admin') {
            $data['customer_id'] = null;
        }
        
        // Admin role - set customer_id from session
        if (isset($data['role']) && $data['role'] === 'admin') {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $data['customer_id'] = $_SESSION['saw_current_customer_id'] ?? null;
        }
        
        return parent::create($data);
    }
    
    /**
     * ✅ FINAL FIX: get_all() for users
     */
    public function get_all($args = []) {
        global $wpdb;
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $session_customer_id = $_SESSION['saw_current_customer_id'] ?? null;
        
        $current_user = wp_get_current_user();
        $is_super_admin = in_array('administrator', $current_user->roles, true);
        
        // Super Admin sees everyone
        if ($is_super_admin && !empty($this->config['filter_by_customer'])) {
            return $this->get_all_for_super_admin($args);
        }
        
        // Others see only their customer (via scope)
        // Parent::get_all() will apply scope automatically
        return parent::get_all($args);
    }
    
    /**
     * ✅ Special method for SuperAdmin
     */
    private function get_all_for_super_admin($filters = []) {
        global $wpdb;
        
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];
        
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
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }
        
        $orderby = $filters['orderby'] ?? 'created_at';
        $order = strtoupper($filters['order'] ?? 'DESC');
        
        if (in_array($order, ['ASC', 'DESC'])) {
            $sql .= " ORDER BY {$orderby} {$order}";
        }
        
        $total_sql = "SELECT COUNT(*) FROM ({$sql}) as count_table";
        $total = $wpdb->get_var($total_sql);
        
        $limit = intval($filters['per_page'] ?? 20);
        $page = intval($filters['page'] ?? 1);
        $offset = ($page - 1) * $limit;
        
        $sql .= " LIMIT {$limit} OFFSET {$offset}";
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        return [
            'items' => $results,
            'total' => $total
        ];
    }
    
    /**
     * Validace
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
            "SELECT COUNT(*) FROM {$this->table} WHERE email = %s AND id != %d",
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
                "SELECT department_id FROM {$wpdb->prefix}saw_user_departments WHERE user_id = %d",
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
            
            if ($wpdb->get_var("SHOW TABLES LIKE '{$full_table}'") !== $full_table) {
                continue;
            }
            
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$full_table} WHERE {$column} = %d",
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