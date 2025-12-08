<?php
/**
 * Customers Module Model
 * 
 * Handles all database operations for customers including:
 * - CRUD operations with automatic cache management
 * - IČO uniqueness validation
 * - Email format validation
 * - Cache bypass option for fresh data
 * - Automatic cache invalidation on write operations
 * - Integration with SAW_Cache system
 * - Multi-language validation messages
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Customers
 * @since       1.0.0
 * @version     3.1.0 - ADDED: Translation system for validation messages
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Customers Model Class
 * 
 * @extends SAW_Base_Model
 */
class SAW_Module_Customers_Model extends SAW_Base_Model 
{
    /**
     * Translations array
     *
     * @since 3.1.0
     * @var array
     */
    private $translations = array();
    
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
        
        // Load translations
        $this->load_translations();
    }
    
    /**
     * Load translations for this module
     *
     * @since 3.1.0
     * @return void
     */
    private function load_translations() {
        $lang = 'cs';
        if (class_exists('SAW_Component_Language_Switcher')) {
            $lang = SAW_Component_Language_Switcher::get_user_language();
        }
        
        $this->translations = function_exists('saw_get_translations') 
            ? saw_get_translations($lang, 'admin', 'customers') 
            : array();
    }
    
    /**
     * Get translation for key
     *
     * @since 3.1.0
     * @param string $key Translation key
     * @param string $fallback Fallback text
     * @return string Translated text
     */
    private function tr($key, $fallback = null) {
        return $this->translations[$key] ?? $fallback ?? $key;
    }
    
    /**
     * Get customer by ID with optional cache bypass
     * 
     * Override parent method to add cache bypass option.
     * Useful when you need fresh data from database (e.g. after update).
     * 
     * @param int  $id           Customer ID
     * @param bool $bypass_cache If true, skip cache and fetch from DB
     * @return array|null Customer data or null if not found
     * @since 1.0.0
     */
    public function get_by_id($id, $bypass_cache = false) {
        global $wpdb;
        
        $id = intval($id);
        
        if (!$id) {
            return null;
        }
        
        // Cache key
        $cache_key = sprintf('customers_item_%d', $id);
        
        // Try cache first (unless bypass)
        if (!$bypass_cache) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf('[CUSTOMERS MODEL] Cache HIT for ID: %d', $id));
                }
                return $cached;
            }
        }
        
        // Fetch from DB (fresh data)
        $item = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE id = %d",
                $this->table,
                $id
            ),
            ARRAY_A
        );
        
        if (!$item) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                SAW_Logger::debug(sprintf('[CUSTOMERS MODEL] ID not found in DB: %d', $id));
            }
            return null;
        }
        
        // Store in cache
        set_transient($cache_key, $item, $this->cache_ttl);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[CUSTOMERS MODEL] Cache MISS - Loaded from DB: %d', $id));
        }
        
        return $item;
    }
    
    /**
     * Get all customers with filters and caching
     * 
     * Override parent method to add proper caching based on filters.
     * Each unique filter combination gets its own cache key.
     * 
     * @param array $filters Filters to apply (status, search, etc.)
     * @return array Array with 'items' and pagination data
     * @since 1.0.0
     */
    public function get_all($filters = array()) {
        // Create unique cache key based on filters
        $cache_key = 'customers_list_' . md5(serialize($filters));
        
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[CUSTOMERS MODEL] List cache HIT');
            }
            return $cached;
        }
        
        // Fetch from parent
        $result = parent::get_all($filters);
        
        // Cache result
        set_transient($cache_key, $result, $this->cache_ttl);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            SAW_Logger::debug(sprintf('[CUSTOMERS MODEL] List cache MISS - Loaded %d items', count($result['items'] ?? array())));
        }
        
        return $result;
    }
    
    /**
     * Create new customer with cache invalidation
     * 
     * Override parent method to invalidate list caches after creation.
     * 
     * @param array $data Customer data to insert
     * @return int|WP_Error Customer ID on success, WP_Error on failure
     * @since 1.0.0
     */
    public function create($data) {
        $customer_id = parent::create($data);
        
        if ($customer_id && !is_wp_error($customer_id)) {
            // ✅ Base Model už volá invalidate_cache() automaticky
            
            // WordPress action hook
            do_action('saw_customer_created', $customer_id);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[CUSTOMERS MODEL] Created customer ID: %d', $customer_id));
            }
        }
        
        return $customer_id;
    }
    
    /**
     * Update customer with cache invalidation
     * 
     * Override parent method to invalidate both item and list caches.
     * This ensures all views show updated data immediately.
     * 
     * @param int   $id   Customer ID
     * @param array $data Customer data to update
     * @return bool|WP_Error True on success, WP_Error on failure
     * @since 1.0.0
     */
    public function update($id, $data) {
        $result = parent::update($id, $data);
        
        if ($result && !is_wp_error($result)) {
            // ✅ Base Model už volá invalidate_cache() automaticky
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[CUSTOMERS MODEL] Updated customer ID: %d - Cache invalidated', $id));
            }
        }
        
        return $result;
    }
    
    /**
     * Delete customer with cache invalidation
     * 
     * Override parent method to invalidate all related caches.
     * 
     * @param int $id Customer ID
     * @return bool|WP_Error True on success, WP_Error on failure
     * @since 1.0.0
     */
    public function delete($id) {
        $result = parent::delete($id);
        
        if ($result && !is_wp_error($result)) {
            // ✅ Base Model už volá invalidate_cache() automaticky
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[CUSTOMERS MODEL] Deleted customer ID: %d - Cache invalidated', $id));
            }
        }
        
        return $result;
    }
    
    /**
     * Validate customer data
     * 
     * Validates customer data before create/update:
     * - Name is required
     * - IČO must be 8 digits and unique
     * - Email must be valid format
     * - Status is required
     * 
     * @param array $data Customer data to validate
     * @param int   $id   Customer ID (0 for new customer)
     * @return bool|WP_Error True if valid, WP_Error with validation errors
     * @since 1.0.0
     */
    public function validate($data, $id = 0) {
        $errors = array();
        
        // Name is required
        if (empty($data['name'])) {
            $errors['name'] = $this->tr('validation_name_required', 'Název je povinný');
        }
        
        // IČO validation (if provided)
        if (!empty($data['ico'])) {
            // Must be 8 digits
            if (!preg_match('/^\d{8}$/', $data['ico'])) {
                $errors['ico'] = $this->tr('validation_ico_format', 'IČO musí být 8 číslic');
            }
            
            // Must be unique
            if ($this->ico_exists($data['ico'], $id)) {
                $errors['ico'] = $this->tr('validation_ico_exists', 'Zákazník s tímto IČO již existuje');
            }
        }
        
        // Email format validation (if provided)
        if (!empty($data['contact_email']) && !is_email($data['contact_email'])) {
            $errors['contact_email'] = $this->tr('validation_email_format', 'Neplatný formát emailu');
        }
        
        // Status is required
        if (empty($data['status'])) {
            $errors['status'] = $this->tr('validation_status_required', 'Status je povinný');
        }
        
        // Return true if no errors, otherwise WP_Error
        if (empty($errors)) {
            return true;
        }
        
        return new WP_Error(
            'validation_error', 
            $this->tr('validation_failed', 'Validace selhala'), 
            $errors
        );
    }
    
    /**
     * Check if IČO already exists
     * 
     * Checks database for duplicate IČO, excluding current customer if editing.
     * 
     * @param string $ico        IČO to check
     * @param int    $exclude_id Customer ID to exclude from check
     * @return bool True if IČO exists, false otherwise
     * @since 1.0.0
     */
    private function ico_exists($ico, $exclude_id = 0) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE ico = %s AND id != %d",
            $this->table,
            $ico,
            $exclude_id
        );
        
        return (bool) $wpdb->get_var($query);
    }
}