<?php
/**
 * Users Module Model - FINAL WORKING VERSION
 * 
 * @package SAW_Visitors
 * @version 5.3.0 - ADMIN SEES HIMSELF
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
    
    public function create($data) {
        if (isset($data['role']) && $data['role'] === 'super_admin') {
            $data['customer_id'] = null;
        } else {
            if (empty($data['customer_id'])) {
                $data['customer_id'] = SAW_Context::get_customer_id();
            }
            if (!$data['customer_id']) {
                return new WP_Error('missing_customer', 'Customer ID is required');
            }
        }
        
        $result = parent::create($data);
        if (!is_wp_error($result)) {
            $this->invalidate_list_cache();
        }
        return $result;
    }
    
    public function update($id, $data) {
        $result = parent::update($id, $data);
        if (!is_wp_error($result)) {
            $this->invalidate_item_cache($id);
            $this->invalidate_list_cache();
        }
        return $result;
    }
    
    public function delete($id) {
        $result = parent::delete($id);
        if (!is_wp_error($result)) {
            $this->invalidate_item_cache($id);
            $this->invalidate_list_cache();
        }
        return $result;
    }
    
    /**
     * Get all users - FIXED: Admin sees himself
     */
    public function get_all($filters = array()) {
        global $wpdb;
        
        $current_wp_user = wp_get_current_user();
        $is_wp_super_admin = in_array('administrator', $current_wp_user->roles, true);
        
        if ($is_wp_super_admin) {
            return parent::get_all($filters);
        }
        
        $context_customer_id = SAW_Context::get_customer_id();
        if (!$context_customer_id) {
            return array('items' => array(), 'total' => 0);
        }
        
        $sql = "SELECT * FROM {$this->table} WHERE customer_id = %d";
        $count_sql = "SELECT COUNT(*) FROM {$this->table} WHERE customer_id = %d";
        
        $params = array($context_customer_id);
        $count_params = array($context_customer_id);
        
        $context_branch_id = SAW_Context::get_branch_id();
        if ($context_branch_id) {
            $sql .= " AND (branch_id = %d OR branch_id IS NULL)";
            $count_sql .= " AND (branch_id = %d OR branch_id IS NULL)";
            $params[] = $context_branch_id;
            $count_params[] = $context_branch_id;
        }
        
        if (!empty($filters['search'])) {
            $search_fields = $this->config['list_config']['searchable'] ?? array('first_name', 'last_name');
            $search_conditions = array();
            $search_value = '%' . $wpdb->esc_like($filters['search']) . '%';
            
            foreach ($search_fields as $field) {
                $search_conditions[] = $wpdb->prepare("{$field} LIKE %s", $search_value);
            }
            
            $search_where = ' AND (' . implode(' OR ', $search_conditions) . ')';
            $sql .= $search_where;
            $count_sql .= $search_where;
        }
        
        foreach ($this->config['list_config']['filters'] ?? array() as $filter_key => $enabled) {
            if ($enabled && isset($filters[$filter_key]) && $filters[$filter_key] !== '') {
                $sql .= $wpdb->prepare(" AND {$filter_key} = %s", $filters[$filter_key]);
                $count_sql .= $wpdb->prepare(" AND {$filter_key} = %s", $filters[$filter_key]);
            }
        }
        
        $sql = $wpdb->prepare($sql, ...$params);
        $count_sql = $wpdb->prepare($count_sql, ...$count_params);
        
        $total = (int) $wpdb->get_var($count_sql);
        
        $orderby = $filters['orderby'] ?? 'first_name';
        $order = strtoupper($filters['order'] ?? 'ASC');
        
        if (!in_array($order, array('ASC', 'DESC'))) {
            $order = 'ASC';
        }
        
        $sql .= " ORDER BY {$orderby} {$order}";
        
        $page = isset($filters['page']) ? max(1, intval($filters['page'])) : 1;
        $per_page = isset($filters['per_page']) ? max(1, intval($filters['per_page'])) : 20;
        $offset = ($page - 1) * $per_page;
        
        $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $per_page, $offset);
        
        $items = $wpdb->get_results($sql, ARRAY_A);
        
        return array(
            'items' => $items ?: array(),
            'total' => $total
        );
    }
    
    public function get_by_id($id) {
        $cache_key = sprintf('saw_users_item_%d', $id);
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
            if (!empty($item['customer_id']) && $item['customer_id'] != $current_customer_id) {
                return null;
            }
            if (empty($item['customer_id']) && isset($item['role']) && $item['role'] === 'super_admin') {
                return null;
            }
        }
        
        if (isset($item['role']) && $item['role'] === 'manager') {
            global $wpdb;
            $departments = $wpdb->get_results($wpdb->prepare(
                "SELECT department_id FROM %i WHERE user_id = %d",
                $wpdb->prefix . 'saw_user_departments',
                $id
            ), ARRAY_A);
            $item['department_ids'] = array_column($departments, 'department_id');
        }
        
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
                $errors['pin'] = 'PIN musí být 4 číslice';
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
    
    private function invalidate_item_cache($id) {
        delete_transient(sprintf('saw_users_item_%d', $id));
    }
    
    private function invalidate_list_cache() {
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_saw_users_list_%' 
             OR option_name LIKE '_transient_timeout_saw_users_list_%'"
        );
    }
}