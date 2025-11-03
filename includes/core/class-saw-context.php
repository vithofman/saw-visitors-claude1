<?php
/**
 * SAW Context - Database-First Architecture
 * 
 * REVOLUTION v5.0.0:
 * ✅ Customer & Branch context stored in DATABASE (saw_users table)
 * ✅ NO sessions for customer/branch (only for user_id & role)
 * ✅ Multi-branch support for super_manager via SAW_User_Branches
 * ✅ Single source of truth: Database
 * ✅ Fast: 1 SQL query instead of 3 data sources
 * ✅ Reliable: No race conditions, no session expiry issues
 * 
 * @package SAW_Visitors
 * @version 5.0.0 - Database Revolution
 * @since 4.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Context {
    
    private static $instance = null;
    private $customer_id = null;
    private $branch_id = null;
    private $saw_user_id = null;
    private $role = null;
    private $initialized = false;
    
    /**
     * Private constructor - Singleton pattern
     */
    private function __construct() {
        $this->load_from_database();
        $this->initialized = true;
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[SAW_Context v5.0] Loaded from DB - Customer: %s, Branch: %s, User: %d, Role: %s',
                $this->customer_id ?? 'NULL',
                $this->branch_id ?? 'NULL',
                get_current_user_id(),
                $this->role ?? 'NULL'
            ));
        }
    }
    
    /**
     * Singleton instance
     * 
     * @return SAW_Context
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        if (!self::$instance->initialized) {
            self::$instance->load_from_database();
            self::$instance->initialized = true;
        }
        
        return self::$instance;
    }
    
    /**
     * ✅ CORE METHOD: Load everything from DATABASE
     * 
     * This is the ONLY source of truth.
     * No sessions, no user_meta, just clean DB query.
     */
    private function load_from_database() {
        if (!is_user_logged_in()) {
            $this->load_fallback_customer();
            return;
        }
        
        global $wpdb;
        
        $saw_user = $wpdb->get_row($wpdb->prepare(
            "SELECT id, customer_id, context_customer_id, context_branch_id, role 
             FROM {$wpdb->prefix}saw_users 
             WHERE wp_user_id = %d AND is_active = 1",
            get_current_user_id()
        ), ARRAY_A);
        
        if (!$saw_user) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[SAW_Context] No saw_users record for wp_user_id: %d',
                    get_current_user_id()
                ));
            }
            $this->load_fallback_customer();
            return;
        }
        
        $this->saw_user_id = intval($saw_user['id']);
        $this->role = $saw_user['role'];
        
        $this->customer_id = $this->resolve_customer_id($saw_user);
        $this->branch_id = $this->resolve_branch_id($saw_user);
        
        //$this->cache_to_session();
    }
    
    /**
     * Resolve customer_id based on role
     * 
     * Logic:
     * - super_admin: Uses context_customer_id (switchable)
     * - Others: Use fixed customer_id
     * 
     * @param array $saw_user
     * @return int|null
     */
    private function resolve_customer_id($saw_user) {
        if ($saw_user['role'] === 'super_admin') {
            return $saw_user['context_customer_id'] 
                ? intval($saw_user['context_customer_id']) 
                : ($saw_user['customer_id'] ? intval($saw_user['customer_id']) : null);
        }
        
        return $saw_user['customer_id'] ? intval($saw_user['customer_id']) : null;
    }
    
    /**
     * Resolve branch_id based on role
     * 
     * Logic:
     * - super_admin: Uses context_branch_id (switchable)
     * - admin: Uses context_branch_id (switchable, all branches)
     * - super_manager: Uses context_branch_id (switchable, limited branches via user_branches)
     * - manager: Uses fixed branch_id (NOT switchable)
     * - terminal: Uses fixed branch_id (NOT switchable)
     * 
     * @param array $saw_user
     * @return int|null
     */
    private function resolve_branch_id($saw_user) {
        if (in_array($saw_user['role'], ['super_admin', 'admin', 'super_manager'])) {
            return $saw_user['context_branch_id'] ? intval($saw_user['context_branch_id']) : null;
        }
        
        return null;
    }
    
    /**
     * Cache to session (for backwards compatibility only)
     * Sessions are NO LONGER the source of truth!
     */
    private function cache_to_session() {
        if (!class_exists('SAW_Session_Manager')) {
            return;
        }
        
        $session = SAW_Session_Manager::instance();
        
        if ($this->customer_id) {
            $session->set('saw_current_customer_id', $this->customer_id);
            $session->set('saw_customer_id', $this->customer_id);
        }
        
        if ($this->branch_id) {
            $session->set('saw_current_branch_id', $this->branch_id);
            $session->set('saw_branch_id', $this->branch_id);
        }
        
        if ($this->saw_user_id) {
            $session->set('saw_user_id', $this->saw_user_id);
        }
        
        if ($this->role) {
            $session->set('saw_role', $this->role);
        }
    }
    
    /**
     * Fallback: Load first active customer (for super_admin setup or non-SAW users)
     */
    private function load_fallback_customer() {
        global $wpdb;
        
        $first_customer = $wpdb->get_var(
            "SELECT id FROM {$wpdb->prefix}saw_customers 
             WHERE status = 'active' 
             ORDER BY id ASC LIMIT 1"
        );
        
        if ($first_customer) {
            $this->customer_id = intval($first_customer);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[SAW_Context] Using fallback customer: %d', $this->customer_id));
            }
        }
    }
    
    /**
     * Get current customer ID
     * 
     * @return int|null
     */
    public static function get_customer_id() {
        return self::instance()->customer_id;
    }
    
    /**
     * Get current branch ID
     * 
     * @return int|null
     */
    public static function get_branch_id() {
        return self::instance()->branch_id;
    }
    
    /**
     * Get current SAW user ID
     * 
     * @return int|null
     */
    public static function get_saw_user_id() {
        return self::instance()->saw_user_id;
    }
    
    /**
     * Get current SAW role
     * 
     * @return string|null
     */
    public static function get_role() {
        return self::instance()->role;
    }
    
    /**
     * ✅ SET CUSTOMER (for super_admin switcher)
     * 
     * Updates DATABASE, then reloads context
     * 
     * @param int $customer_id
     * @return bool
     */
    public static function set_customer_id($customer_id) {
        $customer_id = intval($customer_id);
        
        if (!$customer_id) {
            return false;
        }
        
        $instance = self::instance();
        
        if (!$instance->saw_user_id) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[SAW_Context] Cannot set_customer_id - no saw_user_id');
            }
            return false;
        }
        
        if ($instance->role !== 'super_admin') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[SAW_Context] Cannot set_customer_id - not super_admin');
            }
            return false;
        }
        
        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'saw_users',
            ['context_customer_id' => $customer_id],
            ['id' => $instance->saw_user_id],
            ['%d'],
            ['%d']
        );
        
        if ($result !== false) {
            $instance->customer_id = $customer_id;
            $instance->cache_to_session();
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[SAW_Context] Customer switched to: %d', $customer_id));
            }
            
            self::handle_branch_on_customer_switch($customer_id);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * ✅ SET BRANCH (for switchers)
     * 
     * Updates DATABASE with validation, then reloads context
     * 
     * Validation:
     * - super_admin: Can switch to any branch
     * - admin: Can switch to any branch of their customer
     * - super_manager: Can switch ONLY to assigned branches (via user_branches)
     * - manager: CANNOT switch (fixed branch)
     * - terminal: CANNOT switch (fixed branch)
     * 
     * @param int $branch_id
     * @return bool
     */
    public static function set_branch_id($branch_id) {
        $branch_id = $branch_id ? intval($branch_id) : null;
        
        $instance = self::instance();
        
        if (!$instance->saw_user_id) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[SAW_Context] Cannot set_branch_id - no saw_user_id');
            }
            return false;
        }
        
        if (in_array($instance->role, ['manager', 'terminal'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[SAW_Context] Cannot set_branch_id - role %s has fixed branch', $instance->role));
            }
            return false;
        }
        
        if ($branch_id) {
            if (!self::validate_branch_access($branch_id)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf('[SAW_Context] Access denied to branch: %d', $branch_id));
                }
                return false;
            }
        }
        
        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'saw_users',
            ['context_branch_id' => $branch_id],
            ['id' => $instance->saw_user_id],
            ['%d'],
            ['%d']
        );
        
        if ($result !== false) {
            $instance->branch_id = $branch_id;
            $instance->cache_to_session();
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[SAW_Context] Branch switched to: %s', $branch_id ?? 'NULL'));
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Validate if user has access to branch
     * 
     * @param int $branch_id
     * @return bool
     */
    private static function validate_branch_access($branch_id) {
        global $wpdb;
        
        $instance = self::instance();
        
        if ($instance->role === 'super_admin') {
            return true;
        }
        
        $branch = $wpdb->get_row($wpdb->prepare(
            "SELECT customer_id FROM {$wpdb->prefix}saw_branches WHERE id = %d AND is_active = 1",
            $branch_id
        ), ARRAY_A);
        
        if (!$branch) {
            return false;
        }
        
        if ($branch['customer_id'] != $instance->customer_id) {
            return false;
        }
        
        if ($instance->role === 'admin') {
            return true;
        }
        
        if ($instance->role === 'super_manager') {
            if (class_exists('SAW_User_Branches')) {
                return SAW_User_Branches::is_user_allowed_branch($instance->saw_user_id, $branch_id);
            }
            return false;
        }
        
        return false;
    }
    
    /**
     * Handle branch selection when customer switches
     * 
     * @param int $customer_id
     */
    private static function handle_branch_on_customer_switch($customer_id) {
        global $wpdb;
        
        $branches = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_branches 
             WHERE customer_id = %d AND is_active = 1 
             LIMIT 2",
            $customer_id
        ), ARRAY_A);
        
        if (count($branches) === 1) {
            self::set_branch_id($branches[0]['id']);
        } else {
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
    
    /**
     * Reload context from database (after external changes)
     * 
     * @return bool
     */
    public static function reload() {
        $instance = self::instance();
        $instance->load_from_database();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[SAW_Context] Context reloaded from DB');
        }
        
        return true;
    }
}