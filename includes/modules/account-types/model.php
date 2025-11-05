<?php
/**
 * Account Types Module Model
 * 
 * @package SAW_Visitors
 * @version 2.0.0 - FIXED: Added customer isolation + format methods
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
        $errors = [];
        
        if (empty($data['name'])) {
            $errors['name'] = 'Internal name is required';
        }
        
        if (empty($data['display_name'])) {
            $errors['display_name'] = 'Display name is required';
        }
        
        if (!empty($data['name']) && $this->name_exists($data['name'], $id)) {
            $errors['name'] = 'Account type with this name already exists';
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validation failed', $errors);
    }
    
    /**
     * Check if name exists (globally - account types are NOT customer-isolated)
     */
    private function name_exists($name, $exclude_id = 0) {
        global $wpdb;
        
        if (empty($name)) {
            return false;
        }
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE name = %s AND id != %d",
            $name,
            $exclude_id
        );
        
        return (bool) $wpdb->get_var($query);
    }
    
    /**
     * Get by ID - override to add features formatting
     */
    public function get_by_id($id) {
        $item = parent::get_by_id($id);
        
        if (!$item) {
            return null;
        }
        
        // Format features
        if (!empty($item['features'])) {
            $features = json_decode($item['features'], true);
            $item['features_array'] = is_array($features) ? $features : [];
        }
        
        // Format price
        $price = floatval($item['price'] ?? 0);
        if ($price > 0) {
            $item['price_formatted'] = number_format($price, 2, ',', ' ') . ' Kč';
        } else {
            $item['price_formatted'] = 'Zdarma';
        }
        
        // Format dates
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['created_at']));
        }
        
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['updated_at']));
        }
        
        return $item;
    }
    
    /**
     * Get all - override to set default sorting
     */
    public function get_all($filters = []) {
        if (!isset($filters['orderby'])) {
            $filters['orderby'] = 'sort_order';
            $filters['order'] = 'ASC';
        }
        
        return parent::get_all($filters);
    }
    
    /**
     * Create - process features before save
     */
    public function create($data) {
        $data = $this->process_features_for_save($data);
        return parent::create($data);
    }
    
    /**
     * Update - process features before save
     */
    public function update($id, $data) {
        $data = $this->process_features_for_save($data);
        return parent::update($id, $data);
    }
    
    /**
     * Process features array to JSON for save
     */
    private function process_features_for_save($data) {
        if (isset($data['features']) && is_array($data['features'])) {
            $features = array_filter($data['features'], function($feature) {
                return !empty(trim($feature));
            });
            $data['features'] = !empty($features) ? json_encode(array_values($features), JSON_UNESCAPED_UNICODE) : null;
        }
        
        return $data;
    }
}