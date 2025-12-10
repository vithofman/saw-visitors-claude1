<?php
/**
 * Account Types Module Model
 *
 * SAW TABLE COMPLETE IMPLEMENTATION
 * 
 * @version 12.0.0 - Added create/update methods
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Account_Types_Model extends SAW_Base_Model 
{
    public function __construct($config) {
        global $wpdb;
        
        $table_name = $config['table_name'] ?? $config['table'] ?? 'saw_account_types';
        
        if (is_array($table_name)) {
            $table_name = 'saw_account_types';
        }
        
        $this->table = $wpdb->prefix . $table_name;
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 300;
    }
    
    /**
     * Get all items
     */
    public function get_all($filters = []) {
        global $wpdb;
        
        $where = ['1=1'];
        $params = [];
        
        if (isset($filters['is_active'])) {
            $where[] = 'is_active = %d';
            $params[] = intval($filters['is_active']);
        }
        
        if (!empty($filters['search'])) {
            $search = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where[] = '(name LIKE %s OR display_name LIKE %s OR description LIKE %s)';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $where_sql = implode(' AND ', $where);
        
        $orderby = $filters['orderby'] ?? 'sort_order';
        $allowed_orderby = ['id', 'name', 'display_name', 'price', 'sort_order', 'created_at', 'is_active'];
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'sort_order';
        }
        
        $order = strtoupper($filters['order'] ?? 'ASC');
        if (!in_array($order, ['ASC', 'DESC'])) {
            $order = 'ASC';
        }
        
        $sql = "SELECT * FROM {$this->table} WHERE {$where_sql} ORDER BY {$orderby} {$order}";
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        $items = $wpdb->get_results($sql, ARRAY_A) ?: [];
        
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
     * Create new item
     */
    public function create($data) {
        global $wpdb;
        
        // Validate
        $validation = $this->validate($data);
        if ($validation !== true) {
            return new WP_Error('validation_failed', 'Opravte chyby ve formuláři', $validation);
        }
        
        // Prepare data
        $insert_data = [
            'name' => sanitize_text_field($data['name']),
            'display_name' => sanitize_text_field($data['display_name'] ?? $data['name']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'color' => sanitize_hex_color($data['color'] ?? '#3b82f6'),
            'price' => floatval($data['price'] ?? 0),
            'sort_order' => intval($data['sort_order'] ?? 0),
            'is_active' => intval($data['is_active'] ?? 1),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];
        
        $result = $wpdb->insert(
            $this->table,
            $insert_data,
            ['%s', '%s', '%s', '%s', '%f', '%d', '%d', '%s', '%s']
        );
        
        if ($result === false) {
            return new WP_Error('insert_failed', 'Nepodařilo se vytvořit záznam: ' . $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update existing item
     */
    public function update($id, $data) {
        global $wpdb;
        
        // Check exists
        $existing = $this->get_by_id($id);
        if (!$existing) {
            return new WP_Error('not_found', 'Záznam nenalezen');
        }
        
        // Validate
        $validation = $this->validate($data, $id);
        if ($validation !== true) {
            return new WP_Error('validation_failed', 'Opravte chyby ve formuláři', $validation);
        }
        
        // Prepare data
        $update_data = [
            'name' => sanitize_text_field($data['name']),
            'display_name' => sanitize_text_field($data['display_name'] ?? $data['name']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'color' => sanitize_hex_color($data['color'] ?? '#3b82f6'),
            'price' => floatval($data['price'] ?? 0),
            'sort_order' => intval($data['sort_order'] ?? 0),
            'is_active' => intval($data['is_active'] ?? 1),
            'updated_at' => current_time('mysql'),
        ];
        
        $result = $wpdb->update(
            $this->table,
            $update_data,
            ['id' => $id],
            ['%s', '%s', '%s', '%s', '%f', '%d', '%d', '%s'],
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('update_failed', 'Nepodařilo se aktualizovat záznam: ' . $wpdb->last_error);
        }
        
        return true;
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
     * Format item
     */
    protected function format_item($item) {
        if (!$item) return $item;
        
        $price = floatval($item['price'] ?? 0);
        if ($price > 0) {
            $item['price_formatted'] = number_format($price, 0, ',', ' ') . ' Kč/měsíc';
        } else {
            $item['price_formatted'] = 'Zdarma';
        }
        
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
        }
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['updated_at']));
        }
        
        return $item;
    }
    
    /**
     * Validate data
     */
    public function validate($data, $id = null) {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors['name'] = 'Systémový název je povinný';
        }
        
        return empty($errors) ? true : $errors;
    }
}
