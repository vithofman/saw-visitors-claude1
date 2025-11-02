<?php
/**
 * SAW Authentication System
 * Handles user authentication using WordPress + SAW metadata
 * 
 * KEY CONCEPTS:
 * - All users are WordPress users (wp_users table)
 * - Additional metadata in saw_users table
 * - Login via wp_signon()
 * - PHP sessions for SAW-specific data
 * 
 * @package SAW_Visitors
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Auth {

    /**
     * Check if user has permission (STATIC METHOD)
     * 
     * @param string $permission Permission identifier
     * @return bool
     */
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

    /**
     * Get current customer ID
     * 
     * For SuperAdmin: from user meta (switchable)
     * For other roles: from saw_users table (fixed)
     * 
     * @return int|null Customer ID or null
     */
    public function get_current_customer_id() {
        // Super admin can switch customers
        if (current_user_can('manage_options')) {
            $saved_customer_id = get_user_meta(get_current_user_id(), 'saw_selected_customer_id', true);
            
            if ($saved_customer_id) {
                return intval($saved_customer_id);
            }
            
            // Default to first customer
            global $wpdb;
            $first_customer_id = $wpdb->get_var(
                "SELECT id FROM {$wpdb->prefix}saw_customers ORDER BY id ASC LIMIT 1"
            );
            
            return $first_customer_id ? intval($first_customer_id) : null;
        }
        
        // Other roles - get from session (set during login)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!empty($_SESSION['saw_customer_id'])) {
            return intval($_SESSION['saw_customer_id']);
        }
        
        // Fallback - load from DB
        global $wpdb;
        $saw_user = $wpdb->get_row($wpdb->prepare(
            "SELECT customer_id FROM {$wpdb->prefix}saw_users 
             WHERE wp_user_id = %d AND is_active = 1",
            get_current_user_id()
        ));
        
        return $saw_user ? intval($saw_user->customer_id) : null;
    }
    
    /**
     * Get current user role (SAW role, not WP role)
     * 
     * @return string|null
     */
    public function get_current_user_role() {
        // Super admin
        if (current_user_can('manage_options')) {
            return 'super_admin';
        }
        
        // From session (faster)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!empty($_SESSION['saw_role'])) {
            return $_SESSION['saw_role'];
        }
        
        // Fallback - load from DB
        global $wpdb;
        $saw_user = $wpdb->get_row($wpdb->prepare(
            "SELECT role FROM {$wpdb->prefix}saw_users 
             WHERE wp_user_id = %d AND is_active = 1",
            get_current_user_id()
        ));
        
        return $saw_user ? $saw_user->role : null;
    }
    
    /**
     * Get current branch ID
     * 
     * @return int|null
     */
    public function get_current_branch_id() {
        $role = $this->get_current_user_role();
        
        // Super admin and admin see all branches
        if ($role === 'super_admin' || $role === 'admin') {
            return null;
        }
        
        // From session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['saw_branch_id']) ? intval($_SESSION['saw_branch_id']) : null;
    }
    
    /**
     * Switch customer (SuperAdmin only)
     * 
     * @param int $customer_id Customer ID to switch to
     * @return bool|WP_Error Success or error
     */
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
        
        // Save to user meta
        update_user_meta(get_current_user_id(), 'saw_selected_customer_id', $customer_id);
        
        // Update session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['saw_current_customer_id'] = $customer_id;
        
        // Audit log
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
    
    /**
     * Can user switch customers?
     * 
     * @return bool
     */
    public function can_switch_customers() {
        return current_user_can('manage_options');
    }

    /**
     * Check if user is authenticated
     * 
     * @return bool
     */
    public function check_auth() {
        return is_user_logged_in();
    }

    /**
     * Get current SAW user data
     * 
     * @return object|null
     */
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

    /**
     * Check if user is SuperAdmin
     * 
     * @return bool
     */
    public function is_super_admin() {
        return current_user_can('manage_options');
    }

    /**
     * Check if user is Admin
     * 
     * @return bool
     */
    public function is_admin() {
        $role = $this->get_current_user_role();
        return $role === 'admin';
    }

    /**
     * Check if user is Manager
     * 
     * @return bool
     */
    public function is_manager() {
        $role = $this->get_current_user_role();
        return $role === 'manager' || $role === 'super_manager';
    }

    /**
     * Check if user is Terminal
     * 
     * @return bool
     */
    public function is_terminal() {
        $role = $this->get_current_user_role();
        return $role === 'terminal';
    }

    /**
     * Check customer isolation
     * 
     * Ensures users can only access data from their own customer
     * 
     * @param int $customer_id Customer ID to check
     * @return bool
     */
    public function check_customer_isolation($customer_id) {
        if ($this->is_super_admin()) {
            return true;
        }

        $current_customer_id = $this->get_current_customer_id();

        return $current_customer_id && (int) $customer_id === $current_customer_id;
    }
}