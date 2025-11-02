<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Users_Model extends SAW_Base_Model 
{
    public function __construct($config) {
        global $wpdb;
        
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 1800;
    }
    
    public function validate($data, $id = 0) {
        $errors = [];
        
        if (empty($data['email'])) {
            $errors['email'] = 'Email je povinný';
        } elseif (!is_email($data['email'])) {
            $errors['email'] = 'Neplatný formát emailu';
        } elseif ($this->email_exists($data['email'], $id)) {
            $errors['email'] = 'Uživatel s tímto emailem již existuje';
        }
        
        if (empty($data['first_name'])) {
            $errors['first_name'] = 'Jméno je povinné';
        }
        
        if (empty($data['last_name'])) {
            $errors['last_name'] = 'Příjmení je povinné';
        }
        
        if (empty($data['role'])) {
            $errors['role'] = 'Role je povinná';
        }
        
        if ($data['role'] === 'terminal' && !empty($data['pin'])) {
            if (!preg_match('/^\d{4}$/', $data['pin'])) {
                $errors['pin'] = 'PIN musí být 4 čísla';
            }
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validace selhala', $errors);
    }
    
    private function email_exists($email, $exclude_id = 0) {
        global $wpdb;
        
        if (empty($email)) {
            return false;
        }
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE email = %s AND id != %d",
            $email,
            $exclude_id
        );
        
        return (bool) $wpdb->get_var($query);
    }
    
    public function get_by_id($id) {
        $user = parent::get_by_id($id);
        
        if (!$user) {
            return null;
        }
        
        if ($user['role'] === 'manager') {
            global $wpdb;
            $departments = $wpdb->get_results($wpdb->prepare(
                "SELECT department_id FROM {$wpdb->prefix}saw_user_departments WHERE user_id = %d",
                $id
            ), ARRAY_A);
            
            $user['department_ids'] = array_column($departments, 'department_id');
        }
        
        return $user;
    }
    
    public function is_used_in_system($id) {
        global $wpdb;
        
        $tables_to_check = [
            'saw_visits' => 'created_by',
            'saw_invitations' => 'created_by',
        ];
        
        foreach ($tables_to_check as $table => $column) {
            $full_table = $wpdb->prefix . $table;
            
            if ($wpdb->get_var("SHOW TABLES LIKE '{$full_table}'") !== $full_table) {
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
            'orderby' => 'created_at',
            'order' => 'DESC',
        ];
        
        if ($active_only) {
            $filters['is_active'] = 1;
        }
        
        return $this->get_all($filters);
    }
}
