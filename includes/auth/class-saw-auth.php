<?php
/**
 * SAW Authentication System
 *
 * Database-first authentication system using SAW_Context for state management.
 * Handles user authentication, role checking, and customer isolation.
 *
 * @package    SAW_Visitors
 * @subpackage Auth
 * @version    5.2.0 - FIXED: Admin Branch Context
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW_Auth Class
 *
 * Manages authentication, authorization, and customer context switching.
 *
 * @since 1.0.0
 */
class SAW_Auth {

    /**
     * Check if user has specific permission
     *
     * Validates user permissions for various system operations.
     * Currently supports: manage_account_types, manage_customers, manage_settings.
     *
     * @since 1.0.0
     * @param string $permission Permission to check
     * @return bool True if user has permission
     */
    public static function check_permission($permission) {
        $allowed_permissions = [
            'manage_account_types',
            'manage_customers',
            'manage_settings',
        ];
        
        if (!in_array($permission, $allowed_permissions, true)) {
            return false;
        }
        
        return current_user_can('manage_options');
    }

    /**
     * Get current customer ID
     *
     * Retrieves active customer ID from context.
     *
     * @since 1.0.0
     * @return int|null Customer ID or null
     */
    public function get_current_customer_id() {
        return SAW_Context::get_customer_id();
    }
    
    /**
     * Get current user role
     *
     * Determines user role from WordPress capabilities or SAW_Context.
     * Hierarchy: super_admin → SAW_Context → database fallback.
     *
     * @since 1.0.0
     * @return string|null Role name or null
     */
    public function get_current_user_role() {
        // Check for super admin (WordPress admin)
        if (current_user_can('manage_options')) {
            return 'super_admin';
        }
        
        // Try SAW_Context first
        if (class_exists('SAW_Context')) {
            return SAW_Context::get_role();
        }
        
        // Fallback to direct database query
        $saw_user = $this->get_saw_user_from_db(get_current_user_id());
        
        return $saw_user ? $saw_user->role : null;
    }
    
    /**
     * Get current branch ID
     *
     * Returns active branch ID from context.
     * FIXED: Removed restriction for super admins/admins to allow switcher usage.
     *
     * @since 1.0.0
     * @return int|null Branch ID or null
     */
    public function get_current_branch_id() {
        // Původní kód zde natvrdo vracel null pro adminy.
        // To jsme odstranili, aby fungoval Branch Switcher.
        // SAW_Context se postará o to, aby vrátil správné ID (pokud je ve switcheru vybráno).
        
        return SAW_Context::get_branch_id();
    }
    
    /**
     * Switch customer context
     *
     * Allows super admins to switch active customer context.
     * Logs the switch in audit trail.
     *
     * @since 1.0.0
     * @param int $customer_id Target customer ID
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function switch_customer($customer_id) {
        // Only super admins can switch customers
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'no_permission',
                __('Nemáte oprávnění přepínat zákazníky', 'saw-visitors')
            );
        }
        
        $customer_id = intval($customer_id);
        
        if ($customer_id <= 0) {
            return new WP_Error(
                'invalid_customer_id',
                __('Neplatné ID zákazníka', 'saw-visitors')
            );
        }
        
        // Verify customer exists
        global $wpdb;
        $customer_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE id = %d",
            $wpdb->prefix . 'saw_customers',
            $customer_id
        ));
        
        if (!$customer_exists) {
            return new WP_Error(
                'customer_not_found',
                __('Zákazník nenalezen', 'saw-visitors')
            );
        }
        
        // Switch context
        SAW_Context::set_customer_id($customer_id);
        
        // Audit log
        if (class_exists('SAW_Audit')) {
            SAW_Audit::log([
                'action' => 'customer_switched',
                'user_id' => get_current_user_id(),
                'customer_id' => $customer_id,
                'details' => sprintf(
                    __('SuperAdmin přepnul na zákazníka ID: %d', 'saw-visitors'),
                    $customer_id
                )
            ]);
        }
        
        return true;
    }
    
    /**
     * Check if user can switch customers
     *
     * Only super admins can switch between customer contexts.
     *
     * @since 1.0.0
     * @return bool True if user can switch customers
     */
    public function can_switch_customers() {
        return current_user_can('manage_options');
    }

    /**
     * Check authentication status
     *
     * Verifies if user is logged in to WordPress.
     *
     * @since 1.0.0
     * @return bool True if authenticated
     */
    public function check_auth() {
        return is_user_logged_in();
    }

    /**
     * Get current SAW user
     *
     * Retrieves SAW user record from database.
     *
     * @since 1.0.0
     * @return object|null User object or null
     */
    public function get_current_user() {
        if (!is_user_logged_in()) {
            return null;
        }

        return $this->get_saw_user_from_db(get_current_user_id());
    }

    /**
     * Check if user is super admin
     *
     * Super admins have WordPress 'manage_options' capability.
     *
     * @since 1.0.0
     * @return bool True if super admin
     */
    public function is_super_admin() {
        return current_user_can('manage_options');
    }

    /**
     * Check if user is admin
     *
     * @since 1.0.0
     * @return bool True if admin role
     */
    public function is_admin() {
        $role = $this->get_current_user_role();
        return $role === 'admin';
    }

    /**
     * Check if user is manager
     *
     * Includes both 'manager' and 'super_manager' roles.
     *
     * @since 1.0.0
     * @return bool True if manager or super_manager role
     */
    public function is_manager() {
        $role = $this->get_current_user_role();
        return $role === 'manager' || $role === 'super_manager';
    }

    /**
     * Check if user is terminal
     *
     * @since 1.0.0
     * @return bool True if terminal role
     */
    public function is_terminal() {
        $role = $this->get_current_user_role();
        return $role === 'terminal';
    }

    /**
     * Check customer isolation
     *
     * Validates if user can access data from specific customer.
     * Super admins bypass this check.
     *
     * @since 1.0.0
     * @param int $customer_id Customer ID to check access for
     * @return bool True if user has access
     */
    public function check_customer_isolation($customer_id) {
        if ($this->is_super_admin()) {
            return true;
        }

        $current_customer_id = $this->get_current_customer_id();

        return $current_customer_id && (int) $customer_id === $current_customer_id;
    }
    
    /**
     * Get SAW user from database
     *
     * Helper method to retrieve SAW user record by WordPress user ID.
     *
     * @since 5.1.0
     * @param int $wp_user_id WordPress user ID
     * @return object|null User object or null
     */
    private function get_saw_user_from_db($wp_user_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM %i WHERE wp_user_id = %d AND is_active = 1",
            $wpdb->prefix . 'saw_users',
            $wp_user_id
        ));
    }
}