<?php
/**
 * Account Types Module Model
 * 
 * Handles all database operations for account types including:
 * - CRUD operations with features processing (array ↔ JSON)
 * - Name uniqueness validation (global - not customer-isolated)
 * - Complete data formatting (prices, dates, status labels)
 * - Default sorting by sort_order ASC
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @since       1.0.0
 * @version     2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Account Types Model Class
 * 
 * @extends SAW_Base_Model
 */
class SAW_Module_Account_Types_Model extends SAW_Base_Model 
{
    /**
     * Constructor
     * 
     * @param array $config Module configuration
     * @since 1.0.0
     */
    public function __construct($config) {
        global $wpdb;
        
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 300;
    }
    
    /**
     * Validate account type data
     * 
     * Validates:
     * - Internal name is required
     * - Display name is required
     * - Internal name is unique (global check)
     * 
     * @param array $data Account type data to validate
     * @param int   $id   Account type ID (0 for new record)
     * @return bool|WP_Error True if valid, WP_Error with validation errors
     * @since 1.0.0
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
     * 
     * Account types are global (not customer-isolated), so this checks
     * for duplicate names across entire system.
     * 
     * @param string $name       Internal name to check
     * @param int    $exclude_id Account type ID to exclude from check
     * @return bool True if name exists, false otherwise
     * @since 1.0.0
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
     * Get account type by ID with complete formatting
     * 
     * Formats data for display:
     * - Features: JSON → array (features_array)
     * - Price: float → formatted string with currency (price_formatted)
     * - Status: int → label and badge class
     * - Dates: timestamp → formatted date string
     * 
     * @param int $id Account type ID
     * @return array|null Formatted account type data or null if not found
     * @since 1.0.0
     */
    public function get_by_id($id) {
        $item = parent::get_by_id($id);
        
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
     * Get all account types with default sorting
     * 
     * Override parent to set default sorting by sort_order ASC.
     * This ensures account types are displayed in correct order by default.
     * 
     * @param array $filters Filters to apply
     * @return array Array with 'items' and pagination data
     * @since 1.0.0
     */
    public function get_all($filters = array()) {
        if (!isset($filters['orderby'])) {
            $filters['orderby'] = 'sort_order';
            $filters['order'] = 'ASC';
        }
        
        return parent::get_all($filters);
    }
    
    /**
     * Create new account type
     * 
     * Override parent to process features array before saving.
     * 
     * @param array $data Account type data
     * @return int|WP_Error Account type ID on success, WP_Error on failure
     * @since 1.0.0
     */
    public function create($data) {
        $data = $this->process_features_for_save($data);
        return parent::create($data);
    }
    
    /**
     * Update account type
     * 
     * Override parent to process features array before saving.
     * 
     * @param int   $id   Account type ID
     * @param array $data Account type data
     * @return bool|WP_Error True on success, WP_Error on failure
     * @since 1.0.0
     */
    public function update($id, $data) {
        $data = $this->process_features_for_save($data);
        return parent::update($id, $data);
    }
    
    /**
     * Process features array to JSON for database storage
     * 
     * Converts features array to JSON string:
     * - Filters out empty lines
     * - Preserves order (array_values)
     * - Uses JSON_UNESCAPED_UNICODE for proper Czech characters
     * - Returns null if no features
     * 
     * @param array $data Account type data
     * @return array Data with features processed
     * @since 1.0.0
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