<?php
/**
 * SAW Context - FINAL FIX v3
 * 
 * CRITICAL FIXES:
 * - ✅ Loads customer_id directly from saw_users table for ALL logged-in users
 * - ✅ No session dependency for customer_id loading
 * - ✅ Session only used for caching after successful DB load
 * 
 * @package SAW_Visitors
 * @version 1.3.0 - FINAL FIX
 * @since 4.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Context {
    
    private static $instance = null;
    private $customer_id = null;
    private $branch_id = null;
    private $initialized = false;
    
    private function __construct() {
        $this->init_session();
        $this->customer_id = $this->load_customer_id();
        $this->branch_id = $this->load_branch_id();
        $this->initialized = true;
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[SAW_Context] Init - Customer: %s, Branch: %s, User: %d, Logged: %s, Super: %s',
                $this->customer_id ?? 'NULL',
                $this->branch_id ?? 'NULL',
                get_current_user_id(),
                is_user_logged_in() ? 'YES' : 'NO',
                current_user_can('manage_options') ? 'YES' : 'NO'
            ));
        }
    }
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        if (!self::$instance->initialized) {
            self::$instance->init_session();
            self::$instance->customer_id = self::$instance->load_customer_id();
            self::$instance->branch_id = self::$instance->load_branch_id();
            self::$instance->initialized = true;
        }
        
        return self::$instance;
    }
    
    private function init_session() {
        if (session_status() === PHP_SESSION_NONE) {
            if (!headers_sent()) {
                @session_start();
            }
        }
    }
    
    /**
     * ✅ FINAL FIX: Always load from DATABASE first, session is only cache
     */
    private function load_customer_id() {
        // ================================================
        // FOR ALL LOGGED-IN USERS: LOAD FROM DATABASE
        // ================================================
        if (is_user_logged_in()) {
            global $wpdb;
            
            $saw_user = $wpdb->get_row($wpdb->prepare(
                "SELECT customer_id FROM {$wpdb->prefix}saw_users WHERE wp_user_id = %d AND is_active = 1",
                get_current_user_id()
            ), ARRAY_A);
            
            if ($saw_user) {
                $customer_id = $saw_user['customer_id'] ? intval($saw_user['customer_id']) : null;
                
                // Update session cache
                if (session_status() === PHP_SESSION_ACTIVE && $customer_id) {
                    $_SESSION['saw_current_customer_id'] = $customer_id;
                    $_SESSION['saw_customer_id'] = $customer_id; // Backwards compatibility
                }
                
                if (defined('WP_DEBUG') && WP_DEBUG && $customer_id) {
                    error_log(sprintf(
                        '[SAW_Context] Loaded from DB - Customer: %d, User: %d, Super Admin: %s',
                        $customer_id,
                        get_current_user_id(),
                        current_user_can('manage_options') ? 'YES' : 'NO'
                    ));
                }
                
                return $customer_id;
            }
            
            // User has no saw_users record
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[SAW_Context] No saw_users record for wp_user: %d',
                    get_current_user_id()
                ));
            }
        }
        
        // ================================================
        // FALLBACK: First active customer (for super admin or setup)
        // ================================================
        global $wpdb;
        $first = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}saw_customers WHERE status = 'active' ORDER BY id ASC LIMIT 1");
        
        if ($first) {
            $customer_id = intval($first);
            
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['saw_current_customer_id'] = $customer_id;
                $_SESSION['saw_customer_id'] = $customer_id;
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[SAW_Context] Using fallback customer: %d', $customer_id));
            }
            
            return $customer_id;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[SAW_Context] CRITICAL: No customers found');
        }
        
        return null;
    }
    
    private function load_branch_id() {
        // Check session
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (isset($_SESSION['saw_current_branch_id']) && $_SESSION['saw_current_branch_id']) {
                return intval($_SESSION['saw_current_branch_id']);
            }
            if (isset($_SESSION['saw_branch_id']) && $_SESSION['saw_branch_id']) {
                return intval($_SESSION['saw_branch_id']);
            }
        }
        
        // Check user meta
        if (is_user_logged_in() && $this->customer_id) {
            $meta = get_user_meta(get_current_user_id(), 'saw_branch_customer_' . $this->customer_id, true);
            if ($meta) {
                $branch_id = intval($meta);
                if (session_status() === PHP_SESSION_ACTIVE) {
                    $_SESSION['saw_current_branch_id'] = $branch_id;
                }
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
        
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['saw_current_customer_id'] = $customer_id;
            $_SESSION['saw_customer_id'] = $customer_id;
        }
        $instance->customer_id = $customer_id;
        
        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), 'saw_current_customer_id', $customer_id);
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[SAW_Context] Customer switched to: %d', $customer_id));
        }
        
        self::handle_branch_on_customer_switch($customer_id);
    }
    
    public static function set_branch_id($branch_id) {
        $branch_id = $branch_id ? intval($branch_id) : null;
        
        $instance = self::instance();
        $instance->init_session();
        
        if ($branch_id) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['saw_current_branch_id'] = $branch_id;
                $_SESSION['saw_branch_id'] = $branch_id;
            }
            $instance->branch_id = $branch_id;
            
            if (is_user_logged_in()) {
                update_user_meta(get_current_user_id(), 'saw_current_branch_id', $branch_id);
                
                $customer_id = self::get_customer_id();
                if ($customer_id) {
                    update_user_meta(get_current_user_id(), 'saw_branch_customer_' . $customer_id, $branch_id);
                }
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[SAW_Context] Branch switched to: %d', $branch_id));
            }
        } else {
            if (session_status() === PHP_SESSION_ACTIVE) {
                unset($_SESSION['saw_current_branch_id']);
                unset($_SESSION['saw_branch_id']);
            }
            $instance->branch_id = null;
            
            if (is_user_logged_in()) {
                delete_user_meta(get_current_user_id(), 'saw_current_branch_id');
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[SAW_Context] Branch cleared');
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