<?php
/**
 * Companies Module Model
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Companies
 * @version     2.0.0 - FIXED: Added bypass_cache + proper parent::get_by_id()
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Companies_Model extends SAW_Base_Model 
{
    public function __construct($config) {
        global $wpdb;
        
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 300;
    }
    
    /**
     * Validate company data
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
            $errors['name'] = 'Company name is required';
        }
        
        if (!empty($data['email']) && !is_email($data['email'])) {
            $errors['email'] = 'Invalid email format';
        }
        
        if (!empty($data['website']) && !filter_var($data['website'], FILTER_VALIDATE_URL)) {
            $errors['website'] = 'Invalid website URL';
        }
        
        if (!empty($data['ico']) && $this->ico_exists($data['customer_id'], $data['ico'], $id)) {
            $errors['ico'] = 'Company with this IČO already exists';
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validation failed', $errors);
    }
    
    /**
     * Check if IČO already exists
     */
    private function ico_exists($customer_id, $ico, $exclude_id = 0) {
        global $wpdb;
        
        if (empty($ico)) {
            return false;
        }
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE customer_id = %d AND ico = %s AND id != %d",
            $this->table,
            $customer_id,
            $ico,
            $exclude_id
        );
        
        return (bool) $wpdb->get_var($query);
    }
    
    /**
     * ✅ FIXED: Added $bypass_cache parameter
     */
    public function get_by_id($id, $bypass_cache = false) {
        $item = parent::get_by_id($id, $bypass_cache);
        
        if (!$item) {
            return null;
        }
        
        // Format archived status
        $item['is_archived_label'] = !empty($item['is_archived']) ? 'Archivováno' : 'Aktivní';
        $item['is_archived_badge_class'] = !empty($item['is_archived']) ? 'saw-badge saw-badge-secondary' : 'saw-badge saw-badge-success';
        
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
     * Get branches for select dropdown
     */
    public function get_branches_for_select($customer_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, name FROM %i WHERE customer_id = %d AND is_active = 1 ORDER BY name ASC",
            $wpdb->prefix . 'saw_branches',
            $customer_id
        ), ARRAY_A);
    }
}