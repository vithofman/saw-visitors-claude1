<?php
/**
 * Autentizační systém pro SAW Visitors - WITH CUSTOMER SWITCHING
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Auth {

    private $session;
    private $password;

    public function __construct() {
        $this->session = null;
        $this->password = null;
    }
    
    private function get_session() {
        if ($this->session === null && class_exists('SAW_Session')) {
            $this->session = new SAW_Session();
        }
        return $this->session;
    }
    
    private function get_password() {
        if ($this->password === null && class_exists('SAW_Password')) {
            $this->password = new SAW_Password();
        }
        return $this->password;
    }

    /**
     * Get current customer ID
     * 
     * For SuperAdmin: from user meta (switchable)
     * For Admin: from saw_users table (fixed)
     * For Manager: from saw_users table (fixed)
     * 
     * @return int|null Customer ID or null
     */
    public function get_current_customer_id() {
        // SuperAdmin (WordPress admin with manage_options)
        if (current_user_can('manage_options')) {
            $saved_customer_id = get_user_meta(get_current_user_id(), 'saw_selected_customer_id', true);
            
            if ($saved_customer_id) {
                return intval($saved_customer_id);
            }
            
            // Default: first customer
            global $wpdb;
            $first_customer_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}saw_customers ORDER BY id ASC LIMIT 1");
            
            return $first_customer_id ? intval($first_customer_id) : null;
        }
        
        // Admin or Manager (from saw_users table)
        $saw_user = $this->get_current_user();
        
        if ($saw_user && isset($saw_user->customer_id)) {
            return intval($saw_user->customer_id);
        }
        
        return null;
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
        
        // Verify customer exists
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
        
        // Log the switch
        if (class_exists('SAW_Audit')) {
            SAW_Audit::log(array(
                'action' => 'customer_switched',
                'user_id' => get_current_user_id(),
                'customer_id' => $customer_id,
                'details' => 'SuperAdmin přepnul na zákazníka ID: ' . $customer_id
            ));
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
     * Login user
     */
    public function login($email, $password, $role) {
        global $wpdb;

        if (empty($email) || empty($password) || empty($role)) {
            return new WP_Error('missing_fields', 'Vyplňte všechna pole');
        }

        $allowed_roles = array('admin', 'manager', 'terminal');
        if (!in_array($role, $allowed_roles, true)) {
            return new WP_Error('invalid_role', 'Neplatná role');
        }

        if (!$this->check_rate_limit($_SERVER['REMOTE_ADDR'], 'login')) {
            return new WP_Error('rate_limit_exceeded', 'Příliš mnoho pokusů o přihlášení');
        }

        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_users WHERE email = %s AND role = %s AND is_active = 1",
            $email,
            $role
        ));

        if (!$user) {
            $this->increment_rate_limit($_SERVER['REMOTE_ADDR'], 'login');
            return new WP_Error('invalid_credentials', 'Neplatné přihlašovací údaje');
        }

        $password_handler = $this->get_password();
        if (!$password_handler || !$password_handler->verify($password, $user->password_hash)) {
            $this->increment_rate_limit($_SERVER['REMOTE_ADDR'], 'login');
            return new WP_Error('invalid_credentials', 'Neplatné přihlašovací údaje');
        }

        $session_handler = $this->get_session();
        if (!$session_handler) {
            return new WP_Error('session_error', 'Chyba inicializace session');
        }

        $session_token = $session_handler->create($user->id, $user->role, $user->customer_id);

        if (!$session_token) {
            return new WP_Error('session_create_failed', 'Nepodařilo se vytvořit session');
        }

        $this->reset_rate_limit($_SERVER['REMOTE_ADDR'], 'login');

        return array(
            'success' => true,
            'token' => $session_token,
            'user' => $user,
        );
    }

    /**
     * Logout user
     */
    public function logout() {
        $session_handler = $this->get_session();
        
        if ($session_handler && isset($_COOKIE['saw_session'])) {
            $session_handler->destroy($_COOKIE['saw_session']);
        }

        setcookie('saw_session', '', time() - 3600, '/', '', true, true);

        return true;
    }

    /**
     * Get current user
     */
    public function get_current_user() {
        $session_handler = $this->get_session();
        
        if (!$session_handler) {
            return null;
        }

        $session_token = isset($_COOKIE['saw_session']) ? $_COOKIE['saw_session'] : null;

        if (!$session_token) {
            return null;
        }

        $session_data = $session_handler->validate_session($session_token);

        if (!$session_data) {
            return null;
        }

        global $wpdb;
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_users WHERE id = %d AND is_active = 1",
            $session_data['user_id']
        ));

        return $user;
    }

    /**
     * Check if user is SuperAdmin
     */
    public function is_super_admin() {
        return current_user_can('manage_options');
    }

    /**
     * Check if user is Admin
     */
    public function is_admin() {
        $user = $this->get_current_user();
        return $user && $user->role === 'admin';
    }

    /**
     * Check if user is Manager
     */
    public function is_manager() {
        $user = $this->get_current_user();
        return $user && $user->role === 'manager';
    }

    /**
     * Check if user is Terminal
     */
    public function is_terminal() {
        $user = $this->get_current_user();
        return $user && $user->role === 'terminal';
    }

    /**
     * Check customer isolation
     */
    public function check_customer_isolation($customer_id) {
        if ($this->is_super_admin()) {
            return true;
        }

        $current_customer_id = $this->get_current_customer_id();

        return $current_customer_id && (int) $customer_id === $current_customer_id;
    }

    /**
     * Rate limit check
     */
    private function check_rate_limit($ip, $action) {
        global $wpdb;

        $window_start = date('Y-m-d H:i:s', strtotime('-1 hour'));

        $attempts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_rate_limits 
            WHERE ip_address = %s AND action = %s AND attempted_at >= %s",
            $ip,
            $action,
            $window_start
        ));

        return $attempts < 5;
    }

    /**
     * Increment rate limit
     */
    private function increment_rate_limit($ip, $action) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'saw_rate_limits',
            array(
                'ip_address' => $ip,
                'action' => $action,
                'attempted_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s')
        );
    }

    /**
     * Reset rate limit
     */
    private function reset_rate_limit($ip, $action) {
        global $wpdb;

        $wpdb->delete(
            $wpdb->prefix . 'saw_rate_limits',
            array(
                'ip_address' => $ip,
                'action' => $action,
            ),
            array('%s', '%s')
        );
    }
}