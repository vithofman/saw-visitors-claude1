<?php
/**
 * Departments Module Model
 *
 * Handles all database operations for the Departments module.
 * Inherits robust scoping and context logic from SAW_Base_Model.
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Departments
 * @version     3.1.0 - VERIFIED: Works with Base Model 7.0
 * @since       1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Departments_Model extends SAW_Base_Model 
{
    /**
     * Constructor
     */
    public function __construct($config) {
        global $wpdb;
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = 300;
    }
    
    /**
     * Validate department data
     */
    public function validate($data, $id = 0) {
        $errors = array();
        
        if (empty($data['customer_id'])) {
            $errors['customer_id'] = 'Customer ID is required';
        }
        
        if (empty($data['branch_id'])) {
            $errors['branch_id'] = 'Branch is required';
        }
        
        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        }
        
        // Check duplicate department number within the same branch
        if (!empty($data['department_number']) && $this->department_number_exists($data['customer_id'], $data['branch_id'], $data['department_number'], $id)) {
            $errors['department_number'] = 'Duplicitní číslo oddělení';
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validation failed', $errors);
    }
    
    /**
     * Check if department number exists
     */
    private function department_number_exists($customer_id, $branch_id, $department_number, $exclude_id = 0) {
        global $wpdb;
        
        if (empty($department_number)) {
            return false;
        }
        
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE customer_id = %d AND branch_id = %d AND department_number = %s AND id != %d",
            $this->table,
            $customer_id,
            $branch_id,
            $department_number,
            $exclude_id
        ));
    }
    
    /**
     * Get by ID with formatting
     * We override this just to add specific formatting for the detail view.
     */
    public function get_by_id($id) {
        // Parent method handles security & access check
        $item = parent::get_by_id($id);
        
        if ($item) {
            // Add display helpers
            $item['is_active_label'] = !empty($item['is_active']) ? 'Aktivní' : 'Neaktivní';
            $item['is_active_badge_class'] = !empty($item['is_active']) ? 'saw-badge saw-badge-success' : 'saw-badge saw-badge-secondary';
            
            // Format dates if needed
            if (!empty($item['created_at'])) {
                $item['created_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
            }
        }
        
        return $item;
    }
}