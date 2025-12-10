<?php
/**
 * Account Types Module Model
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @version     3.2.0
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
     * Validate data
     */
    public function validate($data, $id = 0) {
        $errors = array();
        
        if (empty($data['name'])) {
            $errors['name'] = 'Interní název je povinný';
        }
        
        if (empty($data['display_name'])) {
            $errors['display_name'] = 'Zobrazovaný název je povinný';
        }
        
        // Check name format (slug)
        if (!empty($data['name']) && !preg_match('/^[a-z0-9\-_]+$/', $data['name'])) {
            $errors['name'] = 'Interní název může obsahovat pouze malá písmena, číslice, pomlčky a podtržítka';
        }
        
        // Check unique name
        if (!empty($data['name']) && $this->name_exists($data['name'], $id)) {
            $errors['name'] = 'Typ účtu s tímto názvem již existuje';
        }
        
        if (empty($errors)) {
            return true;
        }
        
        return new WP_Error('validation_error', 'Validace selhala', $errors);
    }
    
    /**
     * Check if name exists
     */
    private function name_exists($name, $exclude_id = 0) {
        global $wpdb;
        
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE name = %s AND id != %d",
            $this->table,
            $name,
            $exclude_id
        ));
    }
    
    /**
     * Get by ID with formatting
     */
    public function get_by_id($id, $bypass_cache = false) {
        $item = parent::get_by_id($id, $bypass_cache);
        
        if (!$item) {
            return null;
        }
        
        // Features
        if (!empty($item['features'])) {
            $features = json_decode($item['features'], true);
            $item['features_array'] = is_array($features) ? $features : array();
        } else {
            $item['features_array'] = array();
        }
        
        // Price
        $price = floatval($item['price'] ?? 0);
        $item['price_formatted'] = $price > 0 
            ? number_format($price, 0, ',', ' ') . ' Kč' 
            : 'Zdarma';
        
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
     * Get all with default sorting and custom filters
     */
    public function get_all($filters = array()) {
        if (!isset($filters['orderby'])) {
            $filters['orderby'] = 'sort_order';
            $filters['order'] = 'ASC';
        }
        
        // Get base result from parent
        $result = parent::get_all($filters);
        
        // Apply custom price_type filter after getting results
        if (isset($filters['price_type']) && $filters['price_type'] !== '' && !empty($result['items'])) {
            $filtered_items = array();
            foreach ($result['items'] as $item) {
                $price = floatval($item['price'] ?? 0);
                $is_free = $price <= 0;
                
                if ($filters['price_type'] === 'free' && $is_free) {
                    $filtered_items[] = $item;
                } elseif ($filters['price_type'] === 'paid' && !$is_free) {
                    $filtered_items[] = $item;
                }
            }
            
            $result['items'] = $filtered_items;
            $result['total'] = count($filtered_items);
        }
        
        return $result;
    }
    
    /**
     * Create with features processing
     */
    public function create($data) {
        $data = $this->process_features($data);
        return parent::create($data);
    }
    
    /**
     * Update with features processing
     */
    public function update($id, $data) {
        $data = $this->process_features($data);
        return parent::update($id, $data);
    }
    
    /**
     * Process features array to JSON
     */
    private function process_features($data) {
        if (isset($data['features']) && is_array($data['features'])) {
            $features = array_filter($data['features'], function($f) {
                return !empty(trim($f));
            });
            $data['features'] = !empty($features) 
                ? json_encode(array_values($features), JSON_UNESCAPED_UNICODE) 
                : null;
        }
        return $data;
    }
}
