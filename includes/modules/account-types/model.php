<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Account_Types_Model extends SAW_Base_Model 
{
    public function __construct($config) {
        global $wpdb;
        
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 300;
    }
    
    public function validate($data, $id = 0) {
        if (empty($data['name'])) {
            return new WP_Error('validation_error', 'Name is required');
        }
        
        return true;
    }
}
