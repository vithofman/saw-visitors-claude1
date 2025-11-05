<?php
/**
 * Departments Module Model
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Departments_Model extends SAW_Base_Model 
{
    public function __construct($config) {
        global $wpdb;
        
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 300;
    }
    
    /**
     * Validate data
     */
    public function validate($data, $id = 0) {
        $errors = [];
        
        if (empty($data['customer_id'])) {
            $errors['customer_id'] = 'Customer ID is required';
        }
        
        if (empty($data['branch_id'])) {
            $errors['branch_id'] = 'Branch is required';
        }
        
        if (empty($data['name'])) {
            $errors['name'] = 'Department name is required';
        }
        
        if (!empty($data['department_number']) && $this->department_number_exists($data['customer_id'], $data['branch_id'], $data['department_number'], $id)) {
            $errors['department_number'] = 'Department with this number already exists in this branch';
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
     * Get by ID with formatting
     */
    public function get_by_id($id) {
        $item = parent::get_by_id($id);
        
        if (!$item) {
            return null;
        }
        
        // Customer isolation check
        $current_customer_id = SAW_Context::get_customer_id();
        
        // Super admin can see all
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
                "SELECT name FROM {$wpdb->prefix}saw_branches WHERE id = %d",
                $item['branch_id']
            ), ARRAY_A);
            
            $item['branch_name'] = $branch['name'] ?? 'N/A';
        }
        
        // Format status
        $item['is_active_label'] = !empty($item['is_active']) ? 'Aktivní' : 'Neaktivní';
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
     * Get all with customer isolation - CACHE DISABLED FOR DEBUG
     */
    public function get_all($filters = []) {
        $customer_id = SAW_Context::get_customer_id();
        
        if (!isset($filters['customer_id'])) {
            $filters['customer_id'] = $customer_id;
        }
        
        // TEMPORARILY DISABLED CACHE FOR DEBUG
        return parent::get_all($filters);
    }
    
    /**
     * Create with cache disabled
     */
    public function create($data) {
        return parent::create($data);
    }
    
    /**
     * Update with cache disabled
     */
    public function update($id, $data) {
        return parent::update($id, $data);
    }
    
    /**
     * Delete with cache disabled
     */
    public function delete($id) {
        return parent::delete($id);
    }
}