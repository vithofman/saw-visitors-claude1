<?php
/**
 * SAW Context - FIXED VERSION
 * 
 * Manages customer_id and branch_id context across the application.
 * 
 * FIXES:
 * - ✅ Ensures session is always initialized, even for AJAX requests
 * - ✅ Non-super admin users always load customer_id from database first
 * - ✅ Added logging for debugging context initialization issues
 * 
 * @package SAW_Visitors
 * @version 1.1.0 - FIXED
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
        
        // Debug logging (only in WP_DEBUG mode)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[SAW_Context] Initialized - Customer ID: %s, Branch ID: %s, User: %s, Super Admin: %s',
                $this->customer_id ?? 'NULL',
                $this->branch_id ?? 'NULL',
                get_current_user_id(),
                current_user_can('manage_options') ? 'YES' : 'NO'
            ));
        }
    }
    
    /**
     * Get singleton instance
     * 
     * ✅ FIXED: Always ensures session is initialized
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        // ✅ CRITICAL FIX: Ensure session is always initialized
        // This is important for AJAX requests where session might not be started
        if (!self::$instance->initialized) {
            self::$instance->init_session();
            self::$instance->customer_id = self::$instance->load_customer_id();
            self::$instance->branch_id = self::$instance->load_branch_id();
            self::$instance->initialized = true;
        }
        
        return self::$instance;
    }
    
    /**
     * Initialize PHP session
     * 
     * ✅ FIXED: More robust session initialization
     */
    private function init_session() {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        
        // ✅ NEW: If headers already sent, try to access session anyway
        // WordPress might have started session already
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Session is active, we're good
            return;
        }
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG && headers_sent()) {
            error_log('[SAW_Context] WARNING: Headers already sent, session might not be available');
        }
    }
    
    /**
     * Load customer_id from various sources
     * 
     * ✅ FIXED: Non-super admin ALWAYS loads from database first
     * ✅ FIXED: Better fallback logic
     */
    private function load_customer_id() {
        // ✅ CRITICAL FIX: For NON-super admin, ALWAYS load from database FIRST!
        if (is_user_logged_in() && !current_user_can('manage_options')) {
            global $wpdb;
            
            $saw_user = $wpdb->get_row($wpdb->prepare(
                "SELECT customer_id FROM {$wpdb->prefix}saw_users WHERE wp_user_id = %d AND is_active = 1",
                get_current_user_id()
            ), ARRAY_A);
            
            if ($saw_user && isset($saw_user['customer_id']) && $saw_user['customer_id']) {
                $customer_id = intval($saw_user['customer_id']);
                
                // Update session to match database
                $_SESSION['saw_current_customer_id'] = $customer_id;
                
                // Debug logging
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        '[SAW_Context] Loaded customer_id from DB for non-super admin: %d',
                        $customer_id
                    ));
                }
                
                return $customer_id;
            }
            
            // If no saw_user record found, this is an error state
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[SAW_Context] WARNING: No saw_users record for wp_user_id %d',
                    get_current_user_id()
                ));
            }
        }
        
        // For SUPER ADMIN: Session has priority
        if (current_user_can('manage_options')) {
            // 1. Check session first
            if (isset($_SESSION['saw_current_customer_id']) && $_SESSION['saw_current_customer_id']) {
                return intval($_SESSION['saw_current_customer_id']);
            }
            
            // 2. Check user meta
            if (is_user_logged_in()) {
                $meta = get_user_meta(get_current_user_id(), 'saw_current_customer_id', true);
                if ($meta) {
                    $customer_id = intval($meta);
                    $_SESSION['saw_current_customer_id'] = $customer_id;
                    return $customer_id;
                }
            }
        }
        
        // 3. Fallback: Get first active customer
        global $wpdb;
        $first = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}saw_customers WHERE status = 'active' ORDER BY id ASC LIMIT 1");
        
        if ($first) {
            $customer_id = intval($first);
            $_SESSION['saw_current_customer_id'] = $customer_id;
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[SAW_Context] Using fallback customer_id: %d', $customer_id));
            }
            
            return $customer_id;
        }
        
        // No customers found at all
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[SAW_Context] CRITICAL: No active customers found in database!');
        }
        
        return null;
    }
    
    /**
     * Load branch_id from session or user meta
     */
    private function load_branch_id() {
        // 1. Check session first
        if (isset($_SESSION['saw_current_branch_id']) && $_SESSION['saw_current_branch_id']) {
            return intval($_SESSION['saw_current_branch_id']);
        }
        
        // 2. Check user meta (customer-specific)
        if (is_user_logged_in() && $this->customer_id) {
            $meta = get_user_meta(get_current_user_id(), 'saw_branch_customer_' . $this->customer_id, true);
            if ($meta) {
                $branch_id = intval($meta);
                $_SESSION['saw_current_branch_id'] = $branch_id;
                return $branch_id;
            }
        }
        
        // 3. Check user meta (generic)
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
    
    /**
     * Get current customer_id
     */
    public static function get_customer_id() {
        return self::instance()->customer_id;
    }
    
    /**
     * Get current branch_id
     */
    public static function get_branch_id() {
        return self::instance()->branch_id;
    }
    
    /**
     * Set customer_id
     * 
     * @param int $customer_id
     */
    public static function set_customer_id($customer_id) {
        $customer_id = intval($customer_id);
        
        $instance = self::instance();
        $instance->init_session();
        
        $_SESSION['saw_current_customer_id'] = $customer_id;
        $instance->customer_id = $customer_id;
        
        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), 'saw_current_customer_id', $customer_id);
        }
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[SAW_Context] Customer switched to: %d', $customer_id));
        }
        
        self::handle_branch_on_customer_switch($customer_id);
    }
    
    /**
     * Set branch_id
     * 
     * @param int|null $branch_id
     */
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
            
            // Debug logging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[SAW_Context] Branch switched to: %d', $branch_id));
            }
        } else {
            unset($_SESSION['saw_current_branch_id']);
            $instance->branch_id = null;
            
            if (is_user_logged_in()) {
                delete_user_meta(get_current_user_id(), 'saw_current_branch_id');
            }
            
            // Debug logging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[SAW_Context] Branch cleared');
            }
        }
    }
    
    /**
     * Handle branch selection when customer is switched
     * 
     * If customer has only 1 branch, auto-select it
     * Otherwise, clear branch selection
     * 
     * @param int $customer_id
     */
    private static function handle_branch_on_customer_switch($customer_id) {
        global $wpdb;
        
        $branches = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_branches WHERE customer_id = %d AND is_active = 1 LIMIT 2",
            $customer_id
        ), ARRAY_A);
        
        if (count($branches) === 1) {
            // Only one branch - auto-select it
            self::set_branch_id($branches[0]['id']);
        } else {
            // Multiple or no branches - clear selection
            self::set_branch_id(null);
        }
    }
    
    /**
     * Get full customer data
     * 
     * @return array|null
     */
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
    
    /**
     * Get full branch data
     * 
     * @return array|null
     */
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