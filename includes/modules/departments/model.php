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
        $this->cache_ttl = $config['cache']['ttl'] ?? 1800;
    }
    
    public function validate($data, $id = 0) {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors['name'] = 'Název oddělení je povinný';
        }
        
        if (!empty($data['name']) && $this->name_exists($data['name'], $id, $data['customer_id'] ?? 0)) {
            $errors['name'] = 'Oddělení s tímto názvem již existuje';
        }
        
        if (isset($data['training_version'])) {
            $version = intval($data['training_version']);
            if ($version < 1) {
                $errors['training_version'] = 'Verze školení musí být alespoň 1';
            }
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validace selhala', $errors);
    }
    
    private function name_exists($name, $exclude_id = 0, $customer_id = 0) {
        global $wpdb;
        
        if (empty($name)) {
            return false;
        }
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE name = %s AND customer_id = %d AND id != %d",
            $name,
            $customer_id,
            $exclude_id
        );
        
        return (bool) $wpdb->get_var($query);
    }
    
    public function create($data) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $customer_id = isset($_SESSION['saw_current_customer_id']) ? absint($_SESSION['saw_current_customer_id']) : 0;
        
        if (!$customer_id) {
            global $wpdb;
            $customer_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}saw_customers ORDER BY id ASC LIMIT 1");
        }
        
        $data['customer_id'] = $customer_id;
        
        return parent::create($data);
    }
    
    public function update($id, $data) {
        if (empty($data['customer_id'])) {
            $existing = $this->get_by_id($id);
            $data['customer_id'] = $existing['customer_id'] ?? 1;
        }
        
        return parent::update($id, $data);
    }
    
    public function is_used_in_system($id) {
        global $wpdb;
        
        $tables_to_check = [
            'saw_visits' => 'department_id',
            'saw_invitations' => 'department_id',
            'saw_users' => 'department_id',
        ];
        
        foreach ($tables_to_check as $table => $column) {
            $full_table = $wpdb->prefix . $table;
            
            if ($wpdb->get_var("SHOW TABLES LIKE '{$full_table}'") !== $full_table) {
                continue;
            }
            
            $column_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW COLUMNS FROM `{$full_table}` LIKE %s",
                $column
            ));
            
            if (!$column_exists) {
                continue;
            }
            
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$full_table} WHERE {$column} = %d",
                $id
            ));
            
            if ($count > 0) {
                return true;
            }
        }
        
        return false;
    }
    
    public function get_by_customer($customer_id, $active_only = false) {
        $filters = [
            'customer_id' => $customer_id,
            'orderby' => 'name',
            'order' => 'ASC',
        ];
        
        if ($active_only) {
            $filters['is_active'] = 1;
        }
        
        return $this->get_all($filters);
    }
    
    public function get_all($filters = []) {
        if (!isset($filters['orderby'])) {
            $filters['orderby'] = 'name';
            $filters['order'] = 'ASC';
        }
        
        return parent::get_all($filters);
    }
}
