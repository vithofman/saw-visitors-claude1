<?php
/**
 * Base Model Class - Database-First with Multi-Branch Support
 * 
 * @package SAW_Visitors
 * @version 5.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class SAW_Base_Model 
{
    protected $table;
    protected $config;
    protected $cache_ttl = 300;
    
    public function get_all($filters = []) {
        global $wpdb;
        
        $cache_key = $this->get_cache_key('list', $filters);
        $cached = $this->get_cache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];
        
        $scope_sql = $this->apply_data_scope();
        if (!empty($scope_sql)) {
            $sql .= $scope_sql;
        }
        
        if (!empty($filters['search'])) {
            $search_fields = $this->config['list_config']['searchable'] ?? ['name'];
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
        
        $orderby = $filters['orderby'] ?? 'id';
        $order = strtoupper($filters['order'] ?? 'DESC');
        
        if (in_array($order, ['ASC', 'DESC'])) {
            $sql .= " ORDER BY {$orderby} {$order}";
        }
        
        $total_sql = "SELECT COUNT(*) FROM ({$sql}) as count_table";
        $total = $wpdb->get_var($total_sql);
        
        $limit = intval($filters['per_page'] ?? 20);
        $offset = ($filters['page'] ?? 1) - 1;
        $offset = $offset * $limit;
        
        $sql .= " LIMIT {$limit} OFFSET {$offset}";
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        $data = [
            'items' => $results,
            'total' => $total
        ];
        
        $this->set_cache($cache_key, $data);
        
        return $data;
    }
    
    public function get_by_id($id) {
        global $wpdb;
        
        $cache_key = $this->get_cache_key('item', $id);
        $cached = $this->get_cache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if ($item) {
            $this->set_cache($cache_key, $item);
        }
        
        return $item;
    }
    
    public function create($data) {
        global $wpdb;
        
        $validation = $this->validate($data);
        
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->insert($this->table, $data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Database insert failed: ' . $wpdb->last_error);
        }
        
        $this->invalidate_cache();
        
        return $wpdb->insert_id;
    }
    
    public function update($id, $data) {
        global $wpdb;
        
        $validation = $this->validate($data, $id);
        
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        $data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $this->table,
            $data,
            ['id' => $id]
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Database update failed: ' . $wpdb->last_error);
        }
        
        $this->invalidate_cache();
        
        return true;
    }
    
    public function delete($id) {
        global $wpdb;
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE id = %d",
            $id
        ));
        
        if (!$exists) {
            return new WP_Error('not_found', 'Záznam nenalezen');
        }
        
        $result = $wpdb->delete(
            $this->table,
            ['id' => $id],
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Chyba databáze: ' . $wpdb->last_error);
        }
        
        if ($result === 0) {
            return new WP_Error('delete_failed', 'Záznam se nepodařilo smazat');
        }
        
        $this->invalidate_cache();
        
        return true;
    }
    
    public function count($filters = []) {
        global $wpdb;
        
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if (!empty($filters['customer_id'])) {
            $sql .= " AND customer_id = %d";
            $params[] = intval($filters['customer_id']);
        }
        
        if (isset($filters['branch_id']) && $filters['branch_id'] !== '') {
            $sql .= " AND branch_id = %d";
            $params[] = intval($filters['branch_id']);
        }
        
        $scope_sql = $this->apply_data_scope();
        if (!empty($scope_sql)) {
            $sql .= $scope_sql;
        }
        
        if (!empty($filters['search'])) {
            $search_fields = $this->config['list_config']['searchable'] ?? ['name'];
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
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }
        
        return $wpdb->get_var($sql);
    }
    
    /**
     * Apply data scope based on user role and permissions
     * 
     * ✅ UPDATED: Uses SAW_Context for customer/branch, validates multi-branch for super_manager
     * 
     * @return string SQL WHERE clause
     */
    protected function apply_data_scope() {
        if (!class_exists('SAW_Permissions')) {
            return '';
        }
        
        $role = $this->get_current_user_role();
        
        if (empty($role) || $role === 'super_admin') {
            return '';
        }
        
        $permission = SAW_Permissions::get_permission($role, $this->config['entity'], 'list');
        
        if (!$permission || !isset($permission['scope'])) {
            return '';
        }
        
        $scope = $permission['scope'];
        
        switch ($scope) {
            case 'customer':
                $customer_id = $this->get_current_customer_id();
                if ($customer_id) {
                    return " AND customer_id = " . intval($customer_id);
                }
                break;
                
            case 'branch':
                $branch_ids = $this->get_accessible_branch_ids();
                if (!empty($branch_ids)) {
                    $ids = implode(',', array_map('intval', $branch_ids));
                    return " AND branch_id IN ({$ids})";
                }
                break;
                
            case 'department':
                $department_ids = $this->get_current_department_ids();
                if (!empty($department_ids)) {
                    $ids = implode(',', array_map('intval', $department_ids));
                    return " AND department_id IN ({$ids})";
                }
                break;
                
            case 'own':
                $user_id = get_current_user_id();
                if ($user_id) {
                    return " AND wp_user_id = " . intval($user_id);
                }
                break;
        }
        
        return '';
    }
    
    /**
     * Get accessible branch IDs based on role
     * 
     * ✅ NEW: Handles multi-branch for super_manager
     * 
     * @return array Branch IDs
     */
    protected function get_accessible_branch_ids() {
        $role = $this->get_current_user_role();
        $current_branch_id = $this->get_current_branch_id();
        
        if ($role === 'super_manager') {
            if (!class_exists('SAW_User_Branches')) {
                return $current_branch_id ? [$current_branch_id] : [];
            }
            
            $saw_user_id = SAW_Context::get_saw_user_id();
            if (!$saw_user_id) {
                return $current_branch_id ? [$current_branch_id] : [];
            }
            
            $branch_ids = SAW_User_Branches::get_branch_ids_for_user($saw_user_id);
            
            if (empty($branch_ids)) {
                return $current_branch_id ? [$current_branch_id] : [];
            }
            
            if ($current_branch_id && in_array($current_branch_id, $branch_ids)) {
                return [$current_branch_id];
            }
            
            return $branch_ids;
        }
        
        return $current_branch_id ? [$current_branch_id] : [];
    }
    
    /**
     * Get current user role
     * 
     * ✅ UPDATED: Uses SAW_Context, no session fallback
     * 
     * @return string|null
     */
    protected function get_current_user_role() {
        if (current_user_can('manage_options')) {
            return 'super_admin';
        }
        
        if (class_exists('SAW_Context')) {
            return SAW_Context::get_role();
        }
        
        return null;
    }
    
    /**
     * Get current customer ID
     * 
     * ✅ UPDATED: Uses SAW_Context instead of sessions
     * 
     * @return int|null
     */
    protected function get_current_customer_id() {
        if (class_exists('SAW_Context')) {
            return SAW_Context::get_customer_id();
        }
        
        return null;
    }
    
    /**
     * Get current branch ID
     * 
     * ✅ UPDATED: Uses SAW_Context instead of sessions
     * 
     * @return int|null
     */
    protected function get_current_branch_id() {
        if (class_exists('SAW_Context')) {
            return SAW_Context::get_branch_id();
        }
        
        return null;
    }
    
    /**
     * Get current user's department IDs
     * 
     * @return array
     */
    protected function get_current_department_ids() {
        global $wpdb;
        
        if (!class_exists('SAW_Context')) {
            return [];
        }
        
        $saw_user_id = SAW_Context::get_saw_user_id();
        if (!$saw_user_id) {
            return [];
        }
        
        $department_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT department_id FROM {$wpdb->prefix}saw_user_departments 
             WHERE user_id = %d",
            $saw_user_id
        ));
        
        return array_map('intval', $department_ids);
    }
    
    abstract public function validate($data, $id = 0);
    
    protected function get_cache_key($type, $identifier = '') {
        $key = 'saw_' . $this->config['entity'] . '_' . $type;
        
        if (is_array($identifier)) {
            $key .= '_' . md5(serialize($identifier));
        } elseif ($identifier) {
            $key .= '_' . $identifier;
        }
        
        return $key;
    }
    
    protected function get_cache($key) {
        if (!$this->config['cache']['enabled'] ?? true) {
            return false;
        }
        
        return get_transient($key);
    }
    
    protected function set_cache($key, $data) {
        if (!$this->config['cache']['enabled'] ?? true) {
            return false;
        }
        
        $ttl = $this->config['cache']['ttl'] ?? $this->cache_ttl;
        
        return set_transient($key, $data, $ttl);
    }
    
    protected function invalidate_cache() {
        global $wpdb;
        
        $pattern = 'saw_' . $this->config['entity'] . '_%';
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . $pattern
        ));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_' . $pattern
        ));
    }
}