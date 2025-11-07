<?php
/**
 * Departments Module Model
 * 
 * Handles all database operations for the Departments module including
 * CRUD operations, validation, customer isolation, and caching.
 * 
 * Note: Cache is currently disabled for debugging purposes.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Departments
 * @since       1.0.0
 * @author      SAW Visitors Dev Team
 * @version     1.0.0
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
     * 
     * @since 1.0.0
     * @param int $id Department ID
     * @return array|null Department data or null if not found/no access
     */
    public function get_by_id($id) {
        $item = parent::get_by_id($id);
        
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
        
        // Get branch name (FIXED: SQL injection vulnerability)
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
     * Get all departments with customer isolation
     * 
     * Retrieves a list of departments filtered by current customer context.
     * 
     * Note: Cache is temporarily disabled for debugging.
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
        
        // CACHE DISABLED FOR DEBUG
        // TODO: Enable cache when debugging is complete
        return parent::get_all($filters);
    }
    
    /**
     * Create new department
     * 
     * Creates a new department record.
     * Cache is disabled so no invalidation needed.
     * 
     * @since 1.0.0
     * @param array $data Department data
     * @return int|WP_Error New department ID or error
     */
    public function create($data) {
        return parent::create($data);
    }
    
    /**
     * Update existing department
     * 
     * Updates a department record.
     * Cache is disabled so no invalidation needed.
     * 
     * @since 1.0.0
     * @param int $id Department ID
     * @param array $data Department data
     * @return bool|WP_Error True on success or error
     */
    public function update($id, $data) {
        return parent::update($id, $data);
    }
    
    /**
     * Delete department
     * 
     * Deletes a department record.
     * Cache is disabled so no invalidation needed.
     * 
     * @since 1.0.0
     * @param int $id Department ID
     * @return bool|WP_Error True on success or error
     */
    public function delete($id) {
        return parent::delete($id);
    }
}