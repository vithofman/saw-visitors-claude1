<?php
/**
 * Permissions Module Model
 * 
 * Database operations for permissions.
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 * @since 4.10.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Permissions_Model extends SAW_Base_Model {
    
    public function __construct($config) {
        global $wpdb;
        
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 3600;
    }
    
    /**
     * Get all permissions (override parent to disable customer filtering)
     */
    public function get_all($args = []) {
        global $wpdb;
        
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if (!empty($args['search'])) {
            $search_fields = $this->config['list_config']['searchable'] ?? ['role', 'module', 'action'];
            $search_conditions = [];
            
            foreach ($search_fields as $field) {
                $search_conditions[] = "{$field} LIKE %s";
            }
            
            $search_value = '%' . $wpdb->esc_like($args['search']) . '%';
            
            foreach ($search_fields as $field) {
                $params[] = $search_value;
            }
            
            $sql .= " AND (" . implode(' OR ', $search_conditions) . ")";
        }
        
        foreach ($this->config['list_config']['filters'] ?? [] as $filter_key => $enabled) {
            if ($enabled && isset($args[$filter_key]) && $args[$filter_key] !== '') {
                $sql .= " AND {$filter_key} = %s";
                $params[] = $args[$filter_key];
            }
        }
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }
        
        $orderby = $args['orderby'] ?? 'role';
        $order = strtoupper($args['order'] ?? 'ASC');
        
        if (in_array($order, ['ASC', 'DESC'])) {
            $sql .= " ORDER BY {$orderby} {$order}";
        }
        
        $total_sql = "SELECT COUNT(*) FROM ({$sql}) as count_table";
        $total = $wpdb->get_var($total_sql);
        
        $limit = intval($args['per_page'] ?? 50);
        $page = intval($args['page'] ?? 1);
        $offset = ($page - 1) * $limit;
        
        $sql .= " LIMIT {$limit} OFFSET {$offset}";
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        return [
            'items' => $results,
            'total' => $total,
        ];
    }
    
    /**
     * Validate permission data
     */
    public function validate($data, $id = 0) {
        $errors = [];
        
        if (empty($data['role'])) {
            $errors['role'] = 'Role je povinná';
        }
        
        if (empty($data['module'])) {
            $errors['module'] = 'Modul je povinný';
        }
        
        if (empty($data['action'])) {
            $errors['action'] = 'Akce je povinná';
        }
        
        if (empty($data['scope'])) {
            $errors['scope'] = 'Rozsah dat je povinný';
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validace selhala', $errors);
    }
}