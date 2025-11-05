<?php
/**
 * Customers Module Model
 * 
 * @package SAW_Visitors
 * @version 3.0.0 - FIXED: Cache invalidation + bypass stale cache
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Customers_Model extends SAW_Base_Model 
{
    public function __construct($config) {
        global $wpdb;
        
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 300;
    }
    
    /**
     * Override: get_by_id with cache bypass option
     * ✅ FIXED: Můžeš vynutit fresh data z DB místo cache
     */
    public function get_by_id($id, $bypass_cache = false) {
        global $wpdb;
        
        $id = intval($id);
        
        if (!$id) {
            return null;
        }
        
        // ✅ Cache key
        $cache_key = sprintf('customers_item_%d', $id);
        
        // ✅ Try cache first (unless bypass)
        if (!$bypass_cache) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf('[CUSTOMERS MODEL] Cache HIT for ID: %d', $id));
                }
                return $cached;
            }
        }
        
        // ✅ Fetch from DB (fresh data)
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
                error_log(sprintf('[CUSTOMERS MODEL] ID not found in DB: %d', $id));
            }
            return null;
        }
        
        // ✅ Store in cache
        set_transient($cache_key, $item, $this->cache_ttl);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[CUSTOMERS MODEL] Cache MISS - Loaded from DB: %d', $id));
        }
        
        return $item;
    }
    
    /**
     * Override: get_all with proper cache
     */
    public function get_all($filters = []) {
        // ✅ Create unique cache key based on filters
        $cache_key = 'customers_list_' . md5(serialize($filters));
        
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[CUSTOMERS MODEL] List cache HIT');
            }
            return $cached;
        }
        
        // ✅ Fetch from parent
        $result = parent::get_all($filters);
        
        // ✅ Cache result
        set_transient($cache_key, $result, $this->cache_ttl);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[CUSTOMERS MODEL] List cache MISS - Loaded %d items', count($result['items'] ?? [])));
        }
        
        return $result;
    }
    
    /**
     * Override: create with cache invalidation
     */
    public function create($data) {
        $customer_id = parent::create($data);
        
        if ($customer_id && !is_wp_error($customer_id)) {
            // ✅ Invalidate all list caches
            $this->invalidate_list_cache();
            
            // ✅ WordPress action
            do_action('saw_customer_created', $customer_id);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[CUSTOMERS MODEL] Created customer ID: %d', $customer_id));
            }
        }
        
        return $customer_id;
    }
    
    /**
     * Override: update with cache invalidation
     * ✅ FIXED: Invaliduje jak item tak list cache
     */
    public function update($id, $data) {
        $result = parent::update($id, $data);
        
        if ($result && !is_wp_error($result)) {
            // ✅ Invalidate ITEM cache
            $this->invalidate_item_cache($id);
            
            // ✅ Invalidate LIST cache
            $this->invalidate_list_cache();
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[CUSTOMERS MODEL] Updated customer ID: %d - Cache invalidated', $id));
            }
        }
        
        return $result;
    }
    
    /**
     * Override: delete with cache invalidation
     */
    public function delete($id) {
        $result = parent::delete($id);
        
        if ($result && !is_wp_error($result)) {
            // ✅ Invalidate ITEM cache
            $this->invalidate_item_cache($id);
            
            // ✅ Invalidate LIST cache
            $this->invalidate_list_cache();
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[CUSTOMERS MODEL] Deleted customer ID: %d - Cache invalidated', $id));
            }
        }
        
        return $result;
    }
    
    /**
     * Invalidate item cache
     */
    private function invalidate_item_cache($id) {
        $cache_key = sprintf('customers_item_%d', $id);
        delete_transient($cache_key);
        
        // ✅ SAW_Cache if available
        if (class_exists('SAW_Cache')) {
            SAW_Cache::forget($cache_key);
        }
        
        // ✅ WordPress object cache
        wp_cache_delete($id, 'saw_customers');
    }
    
    /**
     * Invalidate all list caches
     */
    private function invalidate_list_cache() {
        global $wpdb;
        
        // ✅ Delete all transients starting with customers_list_
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_customers_list_%' 
             OR option_name LIKE '_transient_timeout_customers_list_%'"
        );
        
        delete_transient('customers_list');
        delete_transient('customers_for_switcher');
        
        // ✅ SAW_Cache pattern delete
        if (class_exists('SAW_Cache')) {
            SAW_Cache::forget_pattern('customers_*');
        }
        
        // ✅ WordPress object cache
        wp_cache_delete('saw_customers_all', 'saw_customers');
    }
    
    /**
     * Validation with IČO unique check
     */
    public function validate($data, $id = 0) {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors['name'] = 'Název je povinný';
        }
        
        if (!empty($data['ico'])) {
            if (!preg_match('/^\d{8}$/', $data['ico'])) {
                $errors['ico'] = 'IČO musí být 8 číslic';
            }
            
            if ($this->ico_exists($data['ico'], $id)) {
                $errors['ico'] = 'Zákazník s tímto IČO již existuje';
            }
        }
        
        if (!empty($data['contact_email']) && !is_email($data['contact_email'])) {
            $errors['contact_email'] = 'Neplatný formát emailu';
        }
        
        if (empty($data['status'])) {
            $errors['status'] = 'Status je povinný';
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validation failed', $errors);
    }
    
    /**
     * Check if IČO exists
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