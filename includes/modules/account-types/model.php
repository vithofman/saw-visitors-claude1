<?php
/**
 * Account Types Module Model
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @version     4.2.0 - FIXED: Correct return formats
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Account_Types_Model extends SAW_Base_Model 
{
    public function __construct($config) {
        global $wpdb;
        $this->table = $wpdb->prefix . $config['table'];
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
     * Get by ID
     */
    public function get_by_id($id, $bypass_cache = false) {
        $item = parent::get_by_id($id, $bypass_cache);
        return $item ? $this->format_item($item) : null;
    }
    
    /**
     * Count records
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
            return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
        }
        
        return (int) $wpdb->get_var($sql);
    }
    
    /**
     * Format item
     */
    protected function format_item($item) {
        if (!is_array($item)) return $item;
        
        // Price formatted
        $price = floatval($item['price'] ?? 0);
        $item['price_formatted'] = $price > 0 
            ? number_format($price, 0, ',', ' ') . ' Kč' 
            : 'Zdarma';
        
        // Features count
        $feature_fields = ['has_api_access', 'has_custom_branding', 'has_advanced_reports', 'has_sso', 'has_priority_support'];
        $item['features_count'] = 0;
        foreach ($feature_fields as $f) {
            if (!empty($item[$f])) $item['features_count']++;
        }
        
        // Dates
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['created_at']));
        }
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['updated_at']));
        }
        
        return $item;
    }
    
    /**
     * Validate
     */
    public function validate($data, $id = 0) {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors['name'] = 'Systémový název je povinný';
        } elseif (!preg_match('/^[a-z0-9_-]+$/', $data['name'])) {
            $errors['name'] = 'Pouze malá písmena, čísla, pomlčky a podtržítka';
        }
        
        if (empty($data['display_name'])) {
            $errors['display_name'] = 'Zobrazovaný název je povinný';
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validace selhala', $errors);
    }
}
