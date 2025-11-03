<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAW_Context {
    
    private static $instance = null;
    private $customer_id = null;
    private $branch_id = null;
    
    private function __construct() {
        $this->init_session();
        $this->customer_id = $this->load_customer_id();
        $this->branch_id = $this->load_branch_id();
    }
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function init_session() {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
    }
    
    private function load_customer_id() {
        // ✅ OPRAVA: Pro NON-super admina VŽDY načti z DB jako PRVNÍ!
        if (is_user_logged_in() && !current_user_can('manage_options')) {
            global $wpdb;
            $saw_user = $wpdb->get_row($wpdb->prepare(
                "SELECT customer_id FROM {$wpdb->prefix}saw_users WHERE wp_user_id = %d AND is_active = 1",
                get_current_user_id()
            ), ARRAY_A);
            
            if ($saw_user && $saw_user['customer_id']) {
                $customer_id = intval($saw_user['customer_id']);
                // Přepiš session!
                $_SESSION['saw_current_customer_id'] = $customer_id;
                return $customer_id;
            }
        }
        
        // Pro SUPER ADMINA: Session má prioritu
        if (current_user_can('manage_options')) {
            // 1. Session
            if (isset($_SESSION['saw_current_customer_id'])) {
                return intval($_SESSION['saw_current_customer_id']);
            }
            
            // 2. User meta
            if (is_user_logged_in()) {
                $meta = get_user_meta(get_current_user_id(), 'saw_current_customer_id', true);
                if ($meta) {
                    $customer_id = intval($meta);
                    $_SESSION['saw_current_customer_id'] = $customer_id;
                    return $customer_id;
                }
            }
        }
        
        // 3. First customer (fallback)
        global $wpdb;
        $first = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}saw_customers ORDER BY id ASC LIMIT 1");
        if ($first) {
            $customer_id = intval($first);
            $_SESSION['saw_current_customer_id'] = $customer_id;
            return $customer_id;
        }
        
        return null;
    }
    
    private function load_branch_id() {
        if (isset($_SESSION['saw_current_branch_id'])) {
            return intval($_SESSION['saw_current_branch_id']);
        }
        
        if (is_user_logged_in() && $this->customer_id) {
            $meta = get_user_meta(get_current_user_id(), 'saw_branch_customer_' . $this->customer_id, true);
            if ($meta) {
                $branch_id = intval($meta);
                $_SESSION['saw_current_branch_id'] = $branch_id;
                return $branch_id;
            }
        }
        
        if (is_user_logged_in()) {
            $meta = get_user_meta(get_current_user_id(), 'saw_current_branch_id', true);
            if ($meta) {
                $branch_id = intval($meta);
                $_SESSION['saw_current_branch_id'] = $branch_id;
                return $branch_id;
            }
        }
        
        return null;
    }
    
    public static function get_customer_id() {
        return self::instance()->customer_id;
    }
    
    public static function get_branch_id() {
        return self::instance()->branch_id;
    }
    
    public static function set_customer_id($customer_id) {
        $customer_id = intval($customer_id);
        
        $instance = self::instance();
        $instance->init_session();
        
        $_SESSION['saw_current_customer_id'] = $customer_id;
        $instance->customer_id = $customer_id;
        
        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), 'saw_current_customer_id', $customer_id);
        }
        
        self::handle_branch_on_customer_switch($customer_id);
    }
    
    public static function set_branch_id($branch_id) {
        $branch_id = $branch_id ? intval($branch_id) : null;
        
        $instance = self::instance();
        $instance->init_session();
        
        if ($branch_id) {
            $_SESSION['saw_current_branch_id'] = $branch_id;
            $instance->branch_id = $branch_id;
            
            if (is_user_logged_in()) {
                update_user_meta(get_current_user_id(), 'saw_current_branch_id', $branch_id);
                
                $customer_id = self::get_customer_id();
                if ($customer_id) {
                    update_user_meta(get_current_user_id(), 'saw_branch_customer_' . $customer_id, $branch_id);
                }
            }
        } else {
            unset($_SESSION['saw_current_branch_id']);
            $instance->branch_id = null;
            
            if (is_user_logged_in()) {
                delete_user_meta(get_current_user_id(), 'saw_current_branch_id');
            }
        }
    }
    
    private static function handle_branch_on_customer_switch($customer_id) {
        global $wpdb;
        
        $branches = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_branches WHERE customer_id = %d AND is_active = 1 LIMIT 2",
            $customer_id
        ), ARRAY_A);
        
        if (count($branches) === 1) {
            self::set_branch_id($branches[0]['id']);
        } else {
            self::set_branch_id(null);
        }
    }
    
    public static function get_customer_data() {
        $customer_id = self::get_customer_id();
        if (!$customer_id) {
            return null;
        }
        
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_customers WHERE id = %d",
            $customer_id
        ), ARRAY_A);
    }
    
    public static function get_branch_data() {
        $branch_id = self::get_branch_id();
        if (!$branch_id) {
            return null;
        }
        
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_branches WHERE id = %d AND is_active = 1",
            $branch_id
        ), ARRAY_A);
    }
}