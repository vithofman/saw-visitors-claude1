<?php
/**
 * Branches Module Model
 * 
 * Handles all database operations for branches including:
 * - CRUD operations with customer isolation
 * - Validation and data processing
 * - Opening hours JSON handling
 * - Single headquarters enforcement
 * - Cache invalidation
 * - Address formatting
 * - Usage detection in system
 * 
 * @package SAW_Visitors
 * @since 2.0.0
 * @version 3.1.0 - Customer Isolation Fix
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Branches Module Model
 * 
 * @since 2.0.0
 */
class SAW_Module_Branches_Model extends SAW_Base_Model 
{
    /**
     * Constructor - Initialize model
     * 
     * @since 2.0.0
     * @param array $config Module configuration
     */
    public function __construct($config) {
        global $wpdb;
        
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 1800;
    }
    
    /**
     * Validate branch data
     * 
     * Validates all branch fields including:
     * - Required fields (customer_id, name)
     * - Unique code per customer
     * - Email format
     * - GPS coordinates range
     * - Sort order value
     * 
     * @since 2.0.0
     * @param array $data Branch data to validate
     * @param int $id Branch ID (0 for new branches)
     * @return true|WP_Error True if valid, WP_Error with error details if invalid
     */
    public function validate($data, $id = 0) {
        $errors = array();
        
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
     * Check if branch code already exists for customer
     * 
     * @since 2.0.0
     * @param string $code Branch code to check
     * @param int $exclude_id Branch ID to exclude from check (for updates)
     * @param int $customer_id Customer ID
     * @return bool True if code exists, false otherwise
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
     * Get branch by ID with enriched data
     * 
     * Adds computed fields:
     * - opening_hours_array: Parsed opening hours
     * - full_address: Formatted address string
     * - has_gps: Boolean if GPS coordinates exist
     * - google_maps_url: Google Maps link
     * - Status/headquarters labels and badge classes
     * - Country name translation
     * - Formatted dates
     * 
     * @since 2.0.0
     * @param int $id Branch ID
     * @return array|null Branch data with enriched fields or null if not found
     */
    public function get_by_id($id) {
        $item = parent::get_by_id($id);
        
        if (!$item) {
            return null;
        }
        
        if (!empty($item['opening_hours'])) {
            $item['opening_hours_array'] = $this->get_opening_hours_as_array($item['opening_hours']);
        }
        
        $item['full_address'] = $this->get_full_address($item);
        
        $item['has_gps'] = !empty($item['latitude']) && !empty($item['longitude']);
        
        if ($item['has_gps']) {
            $item['google_maps_url'] = sprintf(
                'https://www.google.com/maps?q=%s,%s',
                $item['latitude'],
                $item['longitude']
            );
        }
        
        $item['is_active_label'] = !empty($item['is_active']) ? 'Aktivní' : 'Neaktivní';
        $item['is_active_badge_class'] = !empty($item['is_active']) ? 'saw-badge saw-badge-success' : 'saw-badge saw-badge-secondary';
        
        $item['is_headquarters_label'] = !empty($item['is_headquarters']) ? 'Ano' : 'Ne';
        $item['is_headquarters_badge_class'] = !empty($item['is_headquarters']) ? 'saw-badge saw-badge-info' : 'saw-badge saw-badge-secondary';
        
        $countries = array(
            'CZ' => 'Česká republika',
            'SK' => 'Slovensko',
            'DE' => 'Německo',
            'AT' => 'Rakousko',
            'PL' => 'Polsko',
        );
        
        if (!empty($item['country'])) {
            $item['country_name'] = $countries[$item['country']] ?? $item['country'];
        }
        
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['created_at']));
        }
        
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['updated_at']));
        }
        
        return $item;
    }
    
    /**
     * Get all branches with filters and customer isolation
     * 
     * Automatically adds customer_id filter from context if not provided.
     * Default ordering by sort_order ASC.
     * 
     * @since 2.0.0
     * @param array $filters Filters to apply (search, orderby, order, page, etc.)
     * @return array Array with 'items' and 'total' keys
     */
    public function get_all($filters = array()) {
        $customer_id = SAW_Context::get_customer_id();
        
        if (!isset($filters['customer_id'])) {
            $filters['customer_id'] = $customer_id;
        }
        
        if (!isset($filters['orderby'])) {
            $filters['orderby'] = 'sort_order';
            $filters['order'] = 'ASC';
        }
        
        return parent::get_all($filters);
    }
    
    /**
     * Create new branch
     * 
     * Processes opening hours, ensures single headquarters per customer,
     * and invalidates related caches.
     * 
     * @since 2.0.0
     * @param array $data Branch data
     * @return int|WP_Error Branch ID on success, WP_Error on failure
     */
    public function create($data) {
        $data = $this->process_opening_hours_for_save($data);
        
        $data = $this->ensure_single_headquarters($data);
        
        $result = parent::create($data);
        
        if (!is_wp_error($result)) {
            if (class_exists('SAW_Cache')) {
                SAW_Cache::forget_pattern('branches_list_*');
                SAW_Cache::forget_pattern('branches_*');
            }
            
            if (!empty($data['customer_id'])) {
                delete_transient('branches_for_switcher_' . $data['customer_id']);
                do_action('saw_branch_created', $result, $data['customer_id']);
            }
        }
        
        return $result;
    }
    
    /**
     * Update existing branch
     * 
     * Processes opening hours, ensures single headquarters per customer,
     * and invalidates related caches.
     * 
     * @since 2.0.0
     * @param int $id Branch ID
     * @param array $data Branch data
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function update($id, $data) {
        $data = $this->process_opening_hours_for_save($data);
        
        $data = $this->ensure_single_headquarters($data, $id);
        
        $result = parent::update($id, $data);
        
        if (!is_wp_error($result)) {
            if (class_exists('SAW_Cache')) {
                SAW_Cache::forget(sprintf('branches_item_%d', $id));
                SAW_Cache::forget_pattern('branches_list_*');
                SAW_Cache::forget_pattern('branches_*');
            }
            
            $branch = $this->get_by_id($id);
            if ($branch && !empty($branch['customer_id'])) {
                delete_transient('branches_for_switcher_' . $branch['customer_id']);
            }
        }
        
        return $result;
    }
    
    /**
     * Delete branch
     * 
     * Invalidates related caches after successful deletion.
     * 
     * @since 2.0.0
     * @param int $id Branch ID
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function delete($id) {
        $branch = $this->get_by_id($id);
        
        $result = parent::delete($id);
        
        if (!is_wp_error($result)) {
            if (class_exists('SAW_Cache')) {
                SAW_Cache::forget(sprintf('branches_item_%d', $id));
                SAW_Cache::forget_pattern('branches_list_*');
                SAW_Cache::forget_pattern('branches_*');
            }
            
            if ($branch && !empty($branch['customer_id'])) {
                delete_transient('branches_for_switcher_' . $branch['customer_id']);
            }
        }
        
        return $result;
    }
    
    /**
     * Ensure only one headquarters per customer
     * 
     * If this branch is being set as headquarters, removes headquarters
     * flag from all other branches of the same customer.
     * 
     * @since 2.0.0
     * @param array $data Branch data
     * @param int $exclude_id Branch ID to exclude (for updates)
     * @return array Modified branch data
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
                    array('is_headquarters' => 0),
                    array('customer_id' => $data['customer_id']),
                    array('%d'),
                    array('%d')
                );
            }
        }
        
        return $data;
    }
    
    /**
     * Process opening hours for database storage
     * 
     * Converts textarea input (line-separated) to JSON array format.
     * 
     * @since 2.0.0
     * @param array $data Branch data
     * @return array Modified branch data with processed opening hours
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
     * Parse opening hours JSON to array
     * 
     * @since 2.0.0
     * @param string $hours_json JSON-encoded opening hours
     * @return array Parsed opening hours array
     */
    public function get_opening_hours_as_array($hours_json) {
        if (empty($hours_json)) {
            return array();
        }
        
        $hours = json_decode($hours_json, true);
        
        return is_array($hours) ? $hours : array();
    }
    
    /**
     * Build full address string from branch data
     * 
     * Format: "Street, PostalCode City, Country" (if not CZ)
     * 
     * @since 2.0.0
     * @param array $item Branch data
     * @return string Formatted address string
     */
    public function get_full_address($item) {
        $parts = array();
        
        if (!empty($item['street'])) {
            $parts[] = $item['street'];
        }
        
        if (!empty($item['city']) || !empty($item['postal_code'])) {
            $city_parts = array_filter(array(
                $item['postal_code'] ?? '',
                $item['city'] ?? ''
            ));
            
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
     * 
     * Checks if branch is referenced in:
     * - Visits
     * - Invitations
     * - Users
     * - Departments
     * 
     * @since 2.0.0
     * @param int $id Branch ID
     * @return bool True if branch is used, false otherwise
     */
    public function is_used_in_system($id) {
        global $wpdb;
        
        $tables_to_check = array(
            'saw_visits',
            'saw_invitations',
            'saw_users',
            'saw_departments',
        );
        
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
     * Get all branches for specific customer
     * 
     * @since 2.0.0
     * @param int $customer_id Customer ID
     * @param bool $active_only Whether to return only active branches
     * @return array Array of branches
     */
    public function get_by_customer($customer_id, $active_only = false) {
        $filters = array(
            'customer_id' => $customer_id,
            'orderby' => 'sort_order',
            'order' => 'ASC',
        );
        
        if ($active_only) {
            $filters['is_active'] = 1;
        }
        
        $data = $this->get_all($filters);
        return $data['items'] ?? array();
    }
    
    /**
     * Get headquarters branch for customer
     * 
     * @since 2.0.0
     * @param int $customer_id Customer ID
     * @return array|null Headquarters branch data or null if not found
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