<?php
/**
 * SAW Authentication System - Database-First
 * 
 * @package SAW_Visitors
 * @version 5.0.0
 * @since 4.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Auth {

    public static function check_permission($permission) {
        if ($permission === 'manage_account_types') {
            return current_user_can('manage_options');
        }
        
        if ($permission === 'manage_customers') {
            return current_user_can('manage_options');
        }
        
        if ($permission === 'manage_settings') {
            return current_user_can('manage_options');
        }
        
        return false;
    }

    public function get_current_customer_id() {
        return SAW_Context::get_customer_id();
    }
    
    /**
     * Get current user role
     * 
     * ✅ UPDATED: Uses SAW_Context, no session fallback
     * 
     * @return string|null
     */
    public function get_current_user_role() {
        if (current_user_can('manage_options')) {
            return 'super_admin';
        }
        
        if (class_exists('SAW_Context')) {
            return SAW_Context::get_role();
        }
        
        global $wpdb;
        $saw_user = $wpdb->get_row($wpdb->prepare(
            "SELECT role FROM {$wpdb->prefix}saw_users 
             WHERE wp_user_id = %d AND is_active = 1",
            get_current_user_id()
        ));
        
        return $saw_user ? $saw_user->role : null;
    }
    
    public function get_current_branch_id() {
        $role = $this->get_current_user_role();
        
        if ($role === 'super_admin' || $role === 'admin') {
            return null;
        }
        
        return SAW_Context::get_branch_id();
    }
    
    public function switch_customer($customer_id) {
        if (!current_user_can('manage_options')) {
            return new WP_Error('no_permission', 'Nemáte oprávnění přepínat zákazníky');
        }
        
        $customer_id = intval($customer_id);
        
        if ($customer_id <= 0) {
            return new WP_Error('invalid_customer_id', 'Neplatné ID zákazníka');
        }
        
        global $wpdb;
        $customer_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_customers WHERE id = %d",
            $customer_id
        ));
        
        if (!$customer_exists) {
            return new WP_Error('customer_not_found', 'Zákazník nenalezen');
        }
        
        SAW_Context::set_customer_id($customer_id);
        
        if (class_exists('SAW_Audit')) {
            SAW_Audit::log([
                'action' => 'customer_switched',
                'user_id' => get_current_user_id(),
                'customer_id' => $customer_id,
                'details' => 'SuperAdmin přepnul na zákazníka ID: ' . $customer_id
            ]);
        }
        
        return true;
    }
    
    public function can_switch_customers() {
        return current_user_can('manage_options');
    }

    public function check_auth() {
        return is_user_logged_in();
    }

    public function get_current_user() {
        if (!is_user_logged_in()) {
            return null;
        }

        global $wpdb;
        $saw_user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_users 
             WHERE wp_user_id = %d AND is_active = 1",
            get_current_user_id()
        ));

        return $saw_user;
    }

    public function is_super_admin() {
        return current_user_can('manage_options');
    }

    public function is_admin() {
        $role = $this->get_current_user_role();
        return $role === 'admin';
    }

    public function is_manager() {
        $role = $this->get_current_user_role();
        return $role === 'manager' || $role === 'super_manager';
    }

    public function is_terminal() {
        $role = $this->get_current_user_role();
        return $role === 'terminal';
    }

    public function check_customer_isolation($customer_id) {
        if ($this->is_super_admin()) {
            return true;
        }

        $current_customer_id = $this->get_current_customer_id();

        return $current_customer_id && (int) $customer_id === $current_customer_id;
    }
}