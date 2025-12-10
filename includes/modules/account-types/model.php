<?php
/**
 * Account Types Module Model
 *
 * @version 4.4.0 - FIXED: Uses table_name from config
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Account_Types_Model extends SAW_Base_Model 
{
    public function __construct($config) {
        global $wpdb;
        
        // FIXED: Use table_name (string), not table (array)
        $table_name = $config['table_name'] ?? $config['table'] ?? 'saw_account_types';
        
        // If table_name is array (wrong config), use default
        if (is_array($table_name)) {
            $table_name = 'saw_account_types';
        }
        
        $this->table = $wpdb->prefix . $table_name;
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 300;
    }
    
    /**
     * Get all items
     * 
     * @param array $filters
     * @return array ['items' => [...], 'total' => int]
     */
    public function get_all($filters = []) {
        global $wpdb;
        
        $where = ['1=1'];
        $params = [];
        
        // Filter by is_active
        if (isset($filters['is_active'])) {
            $where[] = 'is_active = %d';
            $params[] = intval($filters['is_active']);
        }
        
        // Search
        if (!empty($filters['search'])) {
            $search = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where[] = '(name LIKE %s OR display_name LIKE %s OR description LIKE %s)';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $where_sql = implode(' AND ', $where);
        
        // Order
        $orderby = $filters['orderby'] ?? 'sort_order';
        $allowed_orderby = ['id', 'name', 'display_name', 'price', 'sort_order', 'created_at', 'is_active'];
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'sort_order';
        }
        
        $order = strtoupper($filters['order'] ?? 'ASC');
        if (!in_array($order, ['ASC', 'DESC'])) {
            $order = 'ASC';
        }
        
        // Build query
        $sql = "SELECT * FROM {$this->table} WHERE {$where_sql} ORDER BY {$orderby} {$order}";
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        $items = $wpdb->get_results($sql, ARRAY_A) ?: [];
        
        // Format items
        foreach ($items as &$item) {
            $item = $this->format_item($item);
        }
        
        return [
            'items' => $items,
            'total' => count($items),
        ];
    }
    
    /**
     * Count items
     * 
     * @param array $filters
     * @return int
     */
    public function count($filters = []) {
        global $wpdb;
        
        $where = ['1=1'];
        $params = [];
        
        if (isset($filters['is_active'])) {
            $where[] = 'is_active = %d';
            $params[] = intval($filters['is_active']);
        }
        
        $where_sql = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$where_sql}";
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        return (int) $wpdb->get_var($sql);
    }
    
    /**
     * Get by ID
     */
    public function get_by_id($id, $bypass_cache = false) {
        global $wpdb;
        
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        ), ARRAY_A);
        
        return $item ? $this->format_item($item) : null;
    }
    
    /**
     * Format item
     */
    protected function format_item($item) {
        if (!$item) return $item;
        
        // Format price
        $price = floatval($item['price'] ?? 0);
        if ($price > 0) {
            $item['price_formatted'] = number_format($price, 0, ',', ' ') . ' Kč/měsíc';
        } else {
            $item['price_formatted'] = 'Zdarma';
        }
        
        // Format dates
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
        }
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['updated_at']));
        }
        
        return $item;
    }
    
    /**
     * Delete item
     */
    public function delete($id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->table,
            ['id' => $id],
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('delete_failed', 'Nepodařilo se smazat záznam');
        }
        
        return true;
    }
    
    /**
     * Validate data
     */
    public function validate($data, $id = null) {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors['name'] = 'Název je povinný';
        }
        
        if (empty($data['display_name'])) {
            $errors['display_name'] = 'Zobrazovaný název je povinný';
        }
        
        return empty($errors) ? true : $errors;
    }
}
