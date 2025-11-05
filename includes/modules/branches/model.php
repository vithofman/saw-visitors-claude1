<?php
/**
 * Branches Module Model
 * 
 * REFACTORED v3.0.0:
 * ✅ NO duplicate customer isolation (trait handles it)
 * ✅ get_by_id() only formats data
 * ✅ Uses SAW_Context instead of sessions
 * ✅ Proper $wpdb->prepare() everywhere
 * ✅ Cache enabled with invalidation
 * 
 * @package SAW_Visitors
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Branches_Model extends SAW_Base_Model 
{
    public function __construct($config) {
        global $wpdb;
        
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 1800;
    }
    
    /**
     * Validate data
     */
    public function validate($data, $id = 0) {
        $errors = [];
        
        if (empty($data['customer_id'])) {
            $errors['customer_id'] = 'Customer ID is required';
        }
        
        if (empty($data['name'])) {
            $errors['name'] = 'Název pobočky je povinný';
        }
        
        if (!empty($data['code']) && $this->code_exists($data['code'], $id, $data['customer_id'] ?? 0)) {
            $errors['code'] = 'Pobočka s tímto kódem již existuje';
        }
        
        if (!empty($data['email']) && !is_email($data['email'])) {
            $errors['email'] = 'Neplatná emailová adresa';
        }
        
        if (!empty($data['latitude'])) {
            $lat = floatval($data['latitude']);
            if ($lat < -90 || $lat > 90) {
                $errors['latitude'] = 'Zeměpisná šířka musí být mezi -90 a 90';
            }
        }
        
        if (!empty($data['longitude'])) {
            $lon = floatval($data['longitude']);
            if ($lon < -180 || $lon > 180) {
                $errors['longitude'] = 'Zeměpisná délka musí být mezi -180 a 180';
            }
        }
        
        if (isset($data['sort_order'])) {
            $sort = intval($data['sort_order']);
            if ($sort < 0) {
                $errors['sort_order'] = 'Pořadí nemůže být záporné';
            }
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validace selhala', $errors);
    }
    
    /**
     * Check if code exists
     */
    private function code_exists($code, $exclude_id = 0, $customer_id = 0) {
        global $wpdb;
        
        if (empty($code)) {
            return false;
        }
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE code = %s AND customer_id = %d AND id != %d",
            $this->table,
            $code,
            $customer_id,
            $exclude_id
        );
        
        return (bool) $wpdb->get_var($query);
    }
    
    /**
     * Get by ID with formatting
     * 
     * ✅ NO customer isolation check - trait handles it in AJAX
     * ✅ ONLY formatting logic here
     */
    public function get_by_id($id) {
        $item = parent::get_by_id($id);
        
        if (!$item) {
            return null;
        }
        
        // ✅ Format opening hours (JSON → array)
        if (!empty($item['opening_hours'])) {
            $item['opening_hours_array'] = $this->get_opening_hours_as_array($item['opening_hours']);
        }
        
        // ✅ Format full address
        $item['full_address'] = $this->get_full_address($item);
        
        // ✅ GPS check
        $item['has_gps'] = !empty($item['latitude']) && !empty($item['longitude']);
        
        if ($item['has_gps']) {
            $item['google_maps_url'] = sprintf(
                'https://www.google.com/maps?q=%s,%s',
                $item['latitude'],
                $item['longitude']
            );
        }
        
        // ✅ Status labels and badges
        $item['is_active_label'] = !empty($item['is_active']) ? 'Aktivní' : 'Neaktivní';
        $item['is_active_badge_class'] = !empty($item['is_active']) ? 'saw-badge saw-badge-success' : 'saw-badge saw-badge-secondary';
        
        $item['is_headquarters_label'] = !empty($item['is_headquarters']) ? 'Ano' : 'Ne';
        $item['is_headquarters_badge_class'] = !empty($item['is_headquarters']) ? 'saw-badge saw-badge-info' : 'saw-badge saw-badge-secondary';
        
        // ✅ Country name
        $countries = [
            'CZ' => 'Česká republika',
            'SK' => 'Slovensko',
            'DE' => 'Německo',
            'AT' => 'Rakousko',
            'PL' => 'Polsko',
        ];
        
        if (!empty($item['country'])) {
            $item['country_name'] = $countries[$item['country']] ?? $item['country'];
        }
        
        // ✅ Format dates
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['created_at']));
        }
        
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['updated_at']));
        }
        
        return $item;
    }
    
    /**
     * Get all with customer isolation and caching
     */
    public function get_all($filters = []) {
        $customer_id = SAW_Context::get_customer_id();
        
        if (!isset($filters['customer_id'])) {
            $filters['customer_id'] = $customer_id;
        }
        
        // Default ordering
        if (!isset($filters['orderby'])) {
            $filters['orderby'] = 'sort_order';
            $filters['order'] = 'ASC';
        }
        
        // Cache key
        $cache_key = sprintf(
            'branches_list_%d_%s',
            $customer_id,
            md5(serialize($filters))
        );
        
        // Try cache
        if (class_exists('SAW_Cache')) {
            return SAW_Cache::remember($cache_key, function() use ($filters) {
                return parent::get_all($filters);
            }, $this->cache_ttl);
        }
        
        return parent::get_all($filters);
    }
    
    /**
     * Create with cache invalidation
     */
    public function create($data) {
        // Process opening hours
        $data = $this->process_opening_hours_for_save($data);
        
        // Ensure single headquarters
        $data = $this->ensure_single_headquarters($data);
        
        $result = parent::create($data);
        
        if (!is_wp_error($result)) {
            // Invalidate cache
            if (class_exists('SAW_Cache')) {
                SAW_Cache::forget_pattern('branches_list_*');
            }
            
            // Fire action
            if (!empty($data['customer_id'])) {
                do_action('saw_branch_created', $result, $data['customer_id']);
            }
        }
        
        return $result;
    }
    
    /**
     * Update with cache invalidation
     */
    public function update($id, $data) {
        // Process opening hours
        $data = $this->process_opening_hours_for_save($data);
        
        // Ensure single headquarters
        $data = $this->ensure_single_headquarters($data, $id);
        
        $result = parent::update($id, $data);
        
        if (!is_wp_error($result)) {
            // Invalidate cache
            if (class_exists('SAW_Cache')) {
                SAW_Cache::forget(sprintf('branches_item_%d', $id));
                SAW_Cache::forget_pattern('branches_list_*');
            }
        }
        
        return $result;
    }
    
    /**
     * Delete with cache invalidation
     */
    public function delete($id) {
        $result = parent::delete($id);
        
        if (!is_wp_error($result)) {
            // Invalidate cache
            if (class_exists('SAW_Cache')) {
                SAW_Cache::forget(sprintf('branches_item_%d', $id));
                SAW_Cache::forget_pattern('branches_list_*');
            }
        }
        
        return $result;
    }
    
    /**
     * Ensure only one headquarters per customer
     */
    private function ensure_single_headquarters($data, $exclude_id = 0) {
        if (!empty($data['is_headquarters']) && !empty($data['customer_id'])) {
            global $wpdb;
            
            if ($exclude_id > 0) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE %i SET is_headquarters = 0 WHERE customer_id = %d AND id != %d",
                    $this->table,
                    $data['customer_id'],
                    $exclude_id
                ));
            } else {
                $wpdb->update(
                    $this->table,
                    ['is_headquarters' => 0],
                    ['customer_id' => $data['customer_id']],
                    ['%d'],
                    ['%d']
                );
            }
        }
        
        return $data;
    }
    
    /**
     * Process opening hours for save
     */
    private function process_opening_hours_for_save($data) {
        if (isset($data['opening_hours']) && is_string($data['opening_hours'])) {
            $lines = explode("\n", $data['opening_hours']);
            $hours = array_filter(array_map('trim', $lines));
            $data['opening_hours'] = !empty($hours) ? json_encode(array_values($hours), JSON_UNESCAPED_UNICODE) : null;
        }
        
        return $data;
    }
    
    /**
     * Get opening hours as array
     */
    public function get_opening_hours_as_array($hours_json) {
        if (empty($hours_json)) {
            return [];
        }
        
        $hours = json_decode($hours_json, true);
        
        return is_array($hours) ? $hours : [];
    }
    
    /**
     * Get full address string
     */
    public function get_full_address($item) {
        $parts = [];
        
        if (!empty($item['street'])) {
            $parts[] = $item['street'];
        }
        
        if (!empty($item['city']) || !empty($item['postal_code'])) {
            $city_parts = array_filter([
                $item['postal_code'] ?? '',
                $item['city'] ?? ''
            ]);
            
            if (!empty($city_parts)) {
                $parts[] = implode(' ', $city_parts);
            }
        }
        
        if (!empty($item['country']) && $item['country'] !== 'CZ') {
            $parts[] = $item['country'];
        }
        
        return implode(', ', $parts);
    }
    
    /**
     * Check if branch is used in system
     */
    public function is_used_in_system($id) {
        global $wpdb;
        
        $tables_to_check = [
            'saw_visits',
            'saw_invitations',
            'saw_users',
            'saw_departments',
        ];
        
        foreach ($tables_to_check as $table) {
            $full_table = $wpdb->prefix . $table;
            
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table)) !== $full_table) {
                continue;
            }
            
            $column = 'branch_id';
            $column_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW COLUMNS FROM %i LIKE %s",
                $full_table,
                $column
            ));
            
            if (!$column_exists) {
                continue;
            }
            
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE %i = %d",
                $full_table,
                $column,
                $id
            ));
            
            if ($count > 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get branches by customer
     */
    public function get_by_customer($customer_id, $active_only = false) {
        $filters = [
            'customer_id' => $customer_id,
            'orderby' => 'sort_order',
            'order' => 'ASC',
        ];
        
        if ($active_only) {
            $filters['is_active'] = 1;
        }
        
        $data = $this->get_all($filters);
        return $data['items'] ?? [];
    }
    
    /**
     * Get headquarters for customer
     */
    public function get_headquarters($customer_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM %i WHERE customer_id = %d AND is_headquarters = 1 LIMIT 1",
            $this->table,
            $customer_id
        ), ARRAY_A);
    }
}