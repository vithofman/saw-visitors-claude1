<?php
/**
 * Branches Module Model
 *
 * REFACTORED to new architecture. Extends SAW_Base_Model.
 * UPDATED (v13.0.0) - CRITICAL FIX:
 * Mirrored customers/model.php structure exactly.
 * Replaced direct call to SAW_Context::get_active_customer_id() inside get_all()
 * with a local get_current_customer_id() helper method to prevent
 * fatal error (Class not found) during initialization.
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches
 * @since       9.0.0 (Refactored)
 * @version     13.0.0 (Context-Fix)
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SAW_Base_Model')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-model.php';
}

class SAW_Module_Branches_Model extends SAW_Base_Model 
{
    /**
     * Constructor
     */
    public function __construct($config) {
        global $wpdb;
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 300;

        $this->allowed_orderby = [
            'id', 'name', 'code', 'city', 'phone', 
            'sort_order', 'is_headquarters', 'created_at'
        ];
    }
    
    /**
     * Get branch by ID with optional cache bypass
     */
    public function get_by_id($id, $bypass_cache = false) {
        global $wpdb;
        
        $id = intval($id);
        if (!$id) {
            return null;
        }
        
        $cache_key = sprintf('branches_item_%d', $id);
        
        if (!$bypass_cache) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        $item = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE id = %d",
                $this->table,
                $id
            ),
            ARRAY_A
        );
        
        if (!$item) {
            return null;
        }
        
        set_transient($cache_key, $item, $this->cache_ttl);
        
        return $item;
    }
    
    /**
     * Get all branches with filters and caching
     * (CRITICAL FIX v13.0.0)
     */
    public function get_all($filters = array()) {
        
        // --- BRANCH SPECIFIC LOGIC (Copied from customers/model.php) ---
        // Použije lokální helper, NE SAW_Context::
        $filters['customer_id'] = $this->get_current_customer_id(); 
        if (empty($filters['customer_id'])) {
            return array(
                'items' => array(),
                'total_items' => 0,
                'total_pages' => 0,
                'total' => 0
            );
        }
        // --- END SPECIFIC LOGIC ---

        $cache_key = 'branches_list_' . md5(serialize($filters));
        
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        // Volání parent::get_all() je teď bezpečné
        $result = parent::get_all($filters);
        
        set_transient($cache_key, $result, $this->cache_ttl);
        
        return $result;
    }

    /**
     * Create new branch with cache invalidation
     */
    public function create($data) {
        $id = parent::create($data); 
        
        if ($id && !is_wp_error($id)) {
            $this->invalidate_list_cache();
            do_action('saw_branch_created', $id, $data['customer_id']);
        }
        return $id;
    }

    /**
     * Update branch with cache invalidation
     */
    public function update($id, $data) {
        $result = parent::update($id, $data); 
        
        if ($result && !is_wp_error($result)) {
            $this->invalidate_item_cache($id);
            $this->invalidate_list_cache();
            do_action('saw_branch_updated', $id);
        }
        return $result;
    }

    /**
     * Delete branch with cache invalidation
     */
    public function delete($id) {
        $item = $this->get_by_id($id, true);
        
        $result = parent::delete($id); 
        
        if ($result && !is_wp_error($result)) {
            $this->invalidate_item_cache($id);
            $this->invalidate_list_cache();
            if ($item) {
                do_action('saw_branch_deleted', $id, $item['customer_id']);
            }
        }
        return $result;
    }

    /**
     * Invalidate item cache
     */
    private function invalidate_item_cache($id) {
        $cache_key = sprintf('branches_item_%d', $id);
        delete_transient($cache_key);
    }
    
    /**
     * Invalidate all list caches
     */
    private function invalidate_list_cache() {
        global $wpdb;
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM %i 
             WHERE option_name LIKE %s 
             OR option_name LIKE %s",
            $wpdb->options,
            $wpdb->esc_like('_transient_branches_list_') . '%',
            $wpdb->esc_like('_transient_timeout_branches_list_') . '%'
        ));
    }

    /**
     * Validate data (Required by abstract SAW_Base_Model)
     */
    public function validate($data, $id = 0) {
        $errors = array();
        
        if (empty($data['name'])) {
            $errors['name'] = __('Název pobočky je povinný.', 'saw-visitors');
        }
        
        if (empty($data['customer_id'])) {
            $errors['customer_id'] = __('Chybí ID zákazníka.', 'saw-visitors');
        }
        
        if (!empty($data['code'])) {
            if ($this->code_exists($data['code'], $data['customer_id'], $id)) {
                $errors['code'] = __('Pobočka s tímto kódem již existuje.', 'saw-visitors');
            }
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', __('Validation failed', 'saw-visitors'), $errors);
    }
    
    /**
     * *** HELPER (Copied from customers/model.php) ***
     * Get current customer ID from context or user data
     */
    private function get_current_customer_id() {
        if (class_exists('SAW_Context')) {
            $context_id = SAW_Context::get_customer_id();
            if ($context_id) {
                return $context_id;
            }
        }

        // Fallback for non-super-admin
        if (is_user_logged_in() && !current_user_can('manage_options')) {
            global $wpdb;
            $saw_user = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT customer_id FROM {$wpdb->prefix}saw_users WHERE wp_user_id = %d AND is_active = 1",
                    get_current_user_id()
                ),
                ARRAY_A
            );
            if ($saw_user && $saw_user['customer_id']) {
                return intval($saw_user['customer_id']);
            }
        }
        
        // Fallback pro Super Admina, který ještě nevybral (stane se zřídka)
        if (class_exists('SAW_Context')) {
             return SAW_Context::get_active_customer_id(); // Bezpečnější volání
        }

        return null;
    }

    /**
     * Check if branch code already exists for this customer
     */
    private function code_exists($code, $customer_id, $exclude_id = 0) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE code = %s AND customer_id = %d AND id != %d",
            $this->table,
            $code,
            $customer_id,
            $exclude_id
        );
        
        return (bool) $wpdb->get_var($query);
    }

    // ============================================
    // MODULE-SPECIFIC DB METHODS
    // ============================================

    public function set_new_headquarters($branch_id, $customer_id) {
        global $wpdb;
        
        $wpdb->update(
            $this->table,
            array('is_headquarters' => 0),
            array('customer_id' => $customer_id)
        );
        
        $wpdb->update(
            $this->table,
            array('is_headquarters' => 1),
            array('id' => $branch_id, 'customer_id' => $customer_id)
        );

        $this->invalidate_list_cache();
    }

    public function get_headquarters_count($customer_id, $exclude_id = 0) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE customer_id = %d AND is_headquarters = 1 AND id != %d",
            $this->table,
            $customer_id,
            $exclude_id
        ));
    }

    public function get_gps_coordinates($address) {
        $api_key = get_option('saw_google_maps_api_key', '');
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('Chybí Google Maps API klíč v nastavení.', 'saw-visitors'));
        }
        
        $url = add_query_arg(array(
            'address' => urlencode($address),
            'key' => $api_key,
        ), 'https://maps.googleapis.com/maps/api/geocode/json');
        
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data['status'] === 'OK') {
            return array(
                'lat' => $data['results'][0]['geometry']['location']['lat'],
                'lng' => $data['results'][0]['geometry']['location']['lng'],
            );
        } else {
            return new WP_Error('api_error', $data['error_message'] ?? __('Nepodařilo se načíst GPS souřadnice.', 'saw-visitors'));
        }
    }
}