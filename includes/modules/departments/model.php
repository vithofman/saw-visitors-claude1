<?php
/**
 * Departments Module Model
 * 
 * Handles all database operations for the Departments module including
 * CRUD operations, validation, customer isolation, and caching.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Departments
 * @since       1.0.0
 * @author      SAW Visitors Dev Team
 * @version     1.0.2
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Departments Model Class
 * 
 * Extends SAW_Base_Model to provide department-specific functionality
 * including validation and data formatting.
 * 
 * @since 1.0.0
 */
class SAW_Module_Departments_Model extends SAW_Base_Model 
{
    /**
     * Constructor - Initialize model with config
     * 
     * Sets up table name, configuration, and cache TTL.
     * 
     * @since 1.0.0
     * @param array $config Module configuration array
     */
    public function __construct($config) {
        global $wpdb;
        
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 300;
    }
    
    /**
     * Validate department data
     * 
     * Validates all required fields and business rules:
     * - customer_id must be present
     * - branch_id must be present
     * - name must be present
     * - department_number must be unique within branch (if provided)
     * 
     * @since 1.0.0
     * @param array $data Department data to validate
     * @param int $id Department ID (for update validation, 0 for create)
     * @return bool|WP_Error True if valid, WP_Error if validation fails
     */
    public function validate($data, $id = 0) {
        $errors = array();
        
        // Customer ID validation
        if (empty($data['customer_id'])) {
            $errors['customer_id'] = 'Customer ID is required';
        }
        
        // Branch ID validation
        if (empty($data['branch_id'])) {
            $errors['branch_id'] = 'Branch is required';
        }
        
        // Name validation
        if (empty($data['name'])) {
            $errors['name'] = 'Department name is required';
        }
        
        // Department number uniqueness check (if provided)
        if (!empty($data['department_number']) && $this->department_number_exists($data['customer_id'], $data['branch_id'], $data['department_number'], $id)) {
            $errors['department_number'] = 'Department with this number already exists in this branch';
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validation failed', $errors);
    }
    
    /**
     * Check if department number already exists
     * 
     * Verifies uniqueness of department_number within the same customer and branch.
     * Empty department numbers are considered valid (not checked).
     * 
     * @since 1.0.0
     * @param int $customer_id Customer ID
     * @param int $branch_id Branch ID
     * @param string $department_number Department number to check
     * @param int $exclude_id Department ID to exclude from check (for updates)
     * @return bool True if department number exists, false otherwise
     */
    private function department_number_exists($customer_id, $branch_id, $department_number, $exclude_id = 0) {
        global $wpdb;
        
        // Empty department numbers are allowed (not unique constraint)
        if (empty($department_number)) {
            return false;
        }
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE customer_id = %d AND branch_id = %d AND department_number = %s AND id != %d",
            $this->table,
            $customer_id,
            $branch_id,
            $department_number,
            $exclude_id
        );
        
        return (bool) $wpdb->get_var($query);
    }
    
    /**
     * Get department by ID with formatting and isolation check
     * 
     * Retrieves a single department record by ID, validates customer isolation,
     * and formats the data for display (branch name, status labels, dates).
     * Uses transient cache with 5 minute TTL.
     * 
     * @since 1.0.0
     * @param int $id Department ID
     * @return array|null Department data or null if not found/no access
     */
    public function get_by_id($id) {
        // Try cache first
        $cache_key = sprintf('saw_departments_item_%d', $id);
        $item = get_transient($cache_key);
        
        if ($item === false) {
            // Cache miss - fetch from database
            $item = parent::get_by_id($id);
            
            if ($item) {
                // Cache for 5 minutes
                set_transient($cache_key, $item, $this->cache_ttl);
            }
        }
        
        if (!$item) {
            return null;
        }
        
        // Customer isolation check
        $current_customer_id = SAW_Context::get_customer_id();
        
        // Super admin can see all departments
        if (!current_user_can('manage_options')) {
            if (empty($item['customer_id']) || $item['customer_id'] != $current_customer_id) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        '[DEPARTMENTS] Isolation violation - Item customer: %s, Current: %s',
                        $item['customer_id'] ?? 'NULL',
                        $current_customer_id ?? 'NULL'
                    ));
                }
                return null;
            }
        }
        
        // Get branch name
        if (!empty($item['branch_id'])) {
            global $wpdb;
            $branch = $wpdb->get_row($wpdb->prepare(
                "SELECT name FROM %i WHERE id = %d",
                $wpdb->prefix . 'saw_branches',
                $item['branch_id']
            ), ARRAY_A);
            
            $item['branch_name'] = $branch['name'] ?? 'N/A';
        }
        
        // Format active status
        $item['is_active_label'] = !empty($item['is_active']) ? 'Aktivní' : 'Neaktivní';
        $item['is_active_badge_class'] = !empty($item['is_active']) ? 'saw-badge saw-badge-success' : 'saw-badge saw-badge-secondary';
        
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
     * Get all departments with customer isolation and caching
     * 
     * Retrieves a list of departments filtered by current customer context.
     * Results are cached for 5 minutes using transients.
     * 
     * @since 1.0.0
     * @param array $filters Query filters (search, orderby, page, etc.)
     * @return array Array with 'items' and 'total' keys
     */
    public function get_all($filters = array()) {
        $customer_id = SAW_Context::get_customer_id();
        
        // Auto-set customer_id filter from context
        if (!isset($filters['customer_id'])) {
            $filters['customer_id'] = $customer_id;
        }
        
        // Create cache key based on customer and filters
        $cache_key = sprintf(
            'saw_departments_list_%d_%s',
            $customer_id,
            md5(serialize($filters))
        );
        
        // Try cache first
        $data = get_transient($cache_key);
        
        if ($data === false) {
            // Cache miss - fetch from database
            $data = parent::get_all($filters);
            
            // Cache for 5 minutes
            set_transient($cache_key, $data, $this->cache_ttl);
        }
        
        return $data;
    }
    
    /**
     * Create new department with cache invalidation
     * 
     * Creates a new department record and invalidates relevant caches.
     * 
     * @since 1.0.0
     * @param array $data Department data
     * @return int|WP_Error New department ID or error
     */
    public function create($data) {
        $result = parent::create($data);
        
        // Invalidate list caches on success
        if (!is_wp_error($result)) {
            $this->invalidate_list_cache();
        }
        
        return $result;
    }
    
    /**
     * Update existing department with cache invalidation
     * 
     * Updates a department record and invalidates relevant caches.
     * 
     * @since 1.0.0
     * @param int $id Department ID
     * @param array $data Department data
     * @return bool|WP_Error True on success or error
     */
    public function update($id, $data) {
        $result = parent::update($id, $data);
        
        // Invalidate caches on success
        if (!is_wp_error($result)) {
            delete_transient(sprintf('saw_departments_item_%d', $id));
            $this->invalidate_list_cache();
        }
        
        return $result;
    }
    
    /**
     * Delete department with cache invalidation
     * 
     * Deletes a department record and invalidates relevant caches.
     * 
     * @since 1.0.0
     * @param int $id Department ID
     * @return bool|WP_Error True on success or error
     */
    public function delete($id) {
        $result = parent::delete($id);
        
        // Invalidate caches on success
        if (!is_wp_error($result)) {
            delete_transient(sprintf('saw_departments_item_%d', $id));
            $this->invalidate_list_cache();
        }
        
        return $result;
    }
    
    /**
     * Invalidate all list caches
     * 
     * Removes all cached department lists from transients.
     * 
     * @since 1.0.2
     * @return void
     */
    private function invalidate_list_cache() {
        global $wpdb;
        
        // Delete all department list transients
        $wpdb->query($wpdb->prepare(
            "DELETE FROM %i WHERE option_name LIKE %s",
            $wpdb->options,
            $wpdb->esc_like('_transient_saw_departments_list_') . '%'
        ));
        
        // Also delete timeout records
        $wpdb->query($wpdb->prepare(
            "DELETE FROM %i WHERE option_name LIKE %s",
            $wpdb->options,
            $wpdb->esc_like('_transient_timeout_saw_departments_list_') . '%'
        ));
    }
}