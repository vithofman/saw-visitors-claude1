<?php
/**
 * Customers Module Model
 * 
 * JEN custom validace (IČO unique check, JOIN s account types).
 * Vše ostatní dědí z Base Model.
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 * @since   4.8.0
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
     * Override: Validation s IČO unique check
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
     * Custom: Check if IČO exists
     */
    private function ico_exists($ico, $exclude_id = 0) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE ico = %s AND id != %d",
            $ico,
            $exclude_id
        );
        
        return (bool) $wpdb->get_var($query);
    }
    
    /**
     * Override: Create - přidá WordPress akci pro auto-setup
     */
    public function create($data) {
        $customer_id = parent::create($data);
        
        if ($customer_id) {
            do_action('saw_customer_created', $customer_id);
        }
        
        return $customer_id;
    }
    
    /**
     * Override: Get all (s caching ale bez JOINů - ty nejsou potřeba)
     */
    public function get_all($filters = []) {
        return parent::get_all($filters);
    }
}