<?php
/**
 * Account Types Module Model
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @version     3.0.0 - REFACTORED: New architecture with COMMIT + bypass_cache
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
     * Validate account type data
     */
    public function validate($data, $id = 0) {
        $errors = array();
        
        if (empty($data['name'])) {
            $errors['name'] = __('Interní název je povinný', 'saw-visitors');
        }
        
        if (empty($data['display_name'])) {
            $errors['display_name'] = __('Zobrazovaný název je povinný', 'saw-visitors');
        }
        
        if (!empty($data['name']) && $this->name_exists($data['name'], $id)) {
            $errors['name'] = __('Typ účtu s tímto názvem již existuje', 'saw-visitors');
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', __('Validace selhala', 'saw-visitors'), $errors);
    }
    
    /**
     * Check if internal name already exists
     */
    private function name_exists($name, $exclude_id = 0) {
        global $wpdb;
        
        if (empty($name)) {
            return false;
        }
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE name = %s AND id != %d",
            $this->table,
            $name,
            $exclude_id
        );
        
        return (bool) $wpdb->get_var($query);
    }
    
    /**
     * ✅ FIXED: Added $bypass_cache parameter
     */
    public function get_by_id($id, $bypass_cache = false) {
        $item = parent::get_by_id($id, $bypass_cache);
        
        if (!$item) {
            return null;
        }
        
        // Format features (JSON to array)
        if (!empty($item['features'])) {
            $features = json_decode($item['features'], true);
            $item['features_array'] = is_array($features) ? $features : array();
        } else {
            $item['features_array'] = array();
        }
        
        // Format price
        $price = floatval($item['price'] ?? 0);
        if ($price > 0) {
            $item['price_formatted'] = number_format($price, 2, ',', ' ') . ' ' . __('Kč', 'saw-visitors');
        } else {
            $item['price_formatted'] = __('Zdarma', 'saw-visitors');
        }
        
        // Format status
        $item['is_active_label'] = !empty($item['is_active']) ? __('Aktivní', 'saw-visitors') : __('Neaktivní', 'saw-visitors');
        $item['is_active_badge_class'] = !empty($item['is_active']) ? 'saw-badge-success' : 'saw-badge-secondary';
        
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
     * Get all with default sorting
     */
    public function get_all($filters = array()) {
        if (!isset($filters['orderby'])) {
            $filters['orderby'] = 'sort_order';
            $filters['order'] = 'ASC';
        }
        
        return parent::get_all($filters);
    }
    
    /**
     * ✅ USES: parent::create() - COMMIT handled automatically
     */
    public function create($data) {
        $data = $this->process_features_for_save($data);
        return parent::create($data);
    }
    
    /**
     * ✅ USES: parent::update() - COMMIT handled automatically
     */
    public function update($id, $data) {
        $data = $this->process_features_for_save($data);
        return parent::update($id, $data);
    }
    
    /**
     * ✅ USES: parent::delete() - COMMIT handled automatically
     */
    public function delete($id) {
        return parent::delete($id);
    }
    
    /**
     * Process features array to JSON
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