<?php
/**
 * SAW Context - Database-First Architecture
 *
 * Customer & Branch context stored in DATABASE (saw_users table).
 * NO sessions for customer/branch (only for user_id & role).
 * Single source of truth: Database.
 * Fast: 1 SQL query instead of multiple data sources.
 * Reliable: No race conditions, no session expiry issues.
 *
 * @package    SAW_Visitors
 * @subpackage Core
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Context manager class - handles customer and branch context
 *
 * @since 1.0.0
 */
class SAW_Context {
    
    /**
     * @var SAW_Context|null Singleton instance
     */
    private static $instance = null;
    
    /**
     * @var int|null Current customer ID
     */
    private $customer_id = null;
    
    /**
     * @var int|null Current branch ID
     */
    private $branch_id = null;
    
    /**
     * @var int|null SAW user ID
     */
    private $saw_user_id = null;
    
    /**
     * @var string|null User role
     */
    private $role = null;
    
    /**
     * @var bool Initialization flag
     */
    private $initialized = false;
    
    /**
     * Private constructor
     *
     * @since 1.0.0
     */
    private function __construct() {
        $this->load_from_database();
        $this->initialized = true;
    }
    
    /**
     * Get singleton instance
     *
     * @since 1.0.0
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
     * Load context from database
     *
     * @since 1.0.0
     */
    private function load_from_database() {
        if (!is_user_logged_in()) {
            $this->load_fallback_customer();
            return;
        }
        
        global $wpdb;
        
        $saw_user = $wpdb->get_row($wpdb->prepare(
            "SELECT id, customer_id, context_customer_id, context_branch_id, role 
             FROM %i 
             WHERE wp_user_id = %d AND is_active = 1",
            $wpdb->prefix . 'saw_users',
            get_current_user_id()
        ), ARRAY_A);
        
        if (!$saw_user) {
            if (current_user_can('manage_options')) {
                $this->role = 'super_admin';
                
                $meta_customer = get_user_meta(get_current_user_id(), 'saw_context_customer_id', true);
                if ($meta_customer) {
                    $this->customer_id = absint($meta_customer);
                    return;
                }
            }
            
            $this->load_fallback_customer();
            return;
        }
        
        $this->saw_user_id = absint($saw_user['id']);
        $this->role = $saw_user['role'];
        
        $this->customer_id = $this->resolve_customer_id($saw_user);
        $this->branch_id = $this->resolve_branch_id($saw_user);
        
        // AUTO-SELECT: Pokud není vybraná pobočka a uživatel může vybírat pobočky
        if (!$this->branch_id && $this->customer_id && in_array($this->role, ['super_admin', 'admin', 'super_manager'])) {
            $this->auto_select_branch();
        }
    }
    
    /**
     * Resolve customer ID based on role
     *
     * @since 1.0.0
     * @param array $saw_user User data from database
     * @return int|null
     */
    private function resolve_customer_id($saw_user) {
        if ($saw_user['role'] === 'super_admin') {
            return $saw_user['context_customer_id'] 
                ? absint($saw_user['context_customer_id']) 
                : ($saw_user['customer_id'] ? absint($saw_user['customer_id']) : null);
        }
        
        return $saw_user['customer_id'] ? absint($saw_user['customer_id']) : null;
    }
    
    /**
     * Resolve branch ID based on role
     *
     * @since 1.0.0
     * @param array $saw_user User data from database
     * @return int|null
     */
    private function resolve_branch_id($saw_user) {
        if (in_array($saw_user['role'], ['super_admin', 'admin', 'super_manager'])) {
            return $saw_user['context_branch_id'] ? absint($saw_user['context_branch_id']) : null;
        }
        
        return null;
    }
    
    /**
     * Auto-select first available branch if none selected
     *
     * @since 8.0.0
     * @return void
     */
    private function auto_select_branch() {
        global $wpdb;
        
        // Get first active branch for customer
        $first_branch = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM %i WHERE customer_id = %d AND is_active = 1 ORDER BY is_headquarters DESC, name ASC LIMIT 1",
            $wpdb->prefix . 'saw_branches',
            $this->customer_id
        ));
        
        if ($first_branch) {
            $branch_id = absint($first_branch);
            
            // Update database
            $wpdb->update(
                $wpdb->prefix . 'saw_users',
                ['context_branch_id' => $branch_id],
                ['id' => $this->saw_user_id],
                ['%d'],
                ['%d']
            );
            
            // Update instance
            $this->branch_id = $branch_id;
        }
    }
    
    /**
     * Load fallback customer when no user context exists
     *
     * @since 1.0.0
     */
    private function load_fallback_customer() {
        global $wpdb;
        
        $first_customer = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM %i WHERE status = %s ORDER BY id ASC LIMIT 1",
            $wpdb->prefix . 'saw_customers',
            'active'
        ));
        
        if ($first_customer) {
            $this->customer_id = absint($first_customer);
        }
    }
    
    /**
     * Get current customer ID
     *
     * @since 1.0.0
     * @return int|null
     */
    public static function get_customer_id() {
        return self::instance()->customer_id;
    }
    
    /**
     * Get current branch ID
     *
     * @since 1.0.0
     * @return int|null
     */
    public static function get_branch_id() {
        return self::instance()->branch_id;
    }
    
    /**
     * Get SAW user ID
     *
     * @since 1.0.0
     * @return int|null
     */
    public static function get_saw_user_id() {
        return self::instance()->saw_user_id;
    }
    
    /**
     * Get user role
     *
     * @since 1.0.0
     * @return string|null
     */
    public static function get_role() {
        return self::instance()->role;
    }
    
    /**
     * Set customer ID (super admin only)
     *
     * @since 1.0.0
     * @param int $customer_id Customer ID to set
     * @return bool Success status
     */
    public static function set_customer_id($customer_id) {
        $customer_id = absint($customer_id);
        
        if (!$customer_id) {
            return false;
        }
        
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        $instance = self::instance();
        
        if (!$instance->saw_user_id) {
            $instance->customer_id = $customer_id;
            
            if (is_user_logged_in()) {
                update_user_meta(get_current_user_id(), 'saw_context_customer_id', $customer_id);
            }
            
            self::handle_branch_on_customer_switch($customer_id);
            
            return true;
        }
        
        if ($instance->role !== 'super_admin') {
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
            
            self::handle_branch_on_customer_switch($customer_id);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Set branch ID
     *
     * @since 1.0.0
     * @param int|null $branch_id Branch ID to set (null to clear)
     * @return bool Success status
     */
    public static function set_branch_id($branch_id) {
        $branch_id = $branch_id ? absint($branch_id) : null;
        
        $instance = self::instance();
        
        if (!$instance->saw_user_id) {
            return false;
        }
        
        if (in_array($instance->role, ['manager', 'terminal'])) {
            return false;
        }
        
        if ($branch_id) {
            if (!self::validate_branch_access($branch_id)) {
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
            return true;
        }
        
        return false;
    }
    
    /**
     * Validate if user has access to branch
     *
     * @since 1.0.0
     * @param int $branch_id Branch ID to validate
     * @return bool
     */
    private static function validate_branch_access($branch_id) {
        global $wpdb;
        
        $instance = self::instance();
        
        if ($instance->role === 'super_admin') {
            return true;
        }
        
        $branch = $wpdb->get_row($wpdb->prepare(
            "SELECT customer_id FROM %i WHERE id = %d AND is_active = 1",
            $wpdb->prefix . 'saw_branches',
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
     * Handle branch selection when customer is switched
     *
     * @since 1.0.0
     * @param int $customer_id New customer ID
     */
    private static function handle_branch_on_customer_switch($customer_id) {
        global $wpdb;
        
        $branches = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM %i WHERE customer_id = %d AND is_active = 1 ORDER BY is_headquarters DESC, name ASC LIMIT 2",
            $wpdb->prefix . 'saw_branches',
            $customer_id
        ), ARRAY_A);
        
        if (count($branches) === 1) {
            self::set_branch_id(absint($branches[0]['id']));
        } elseif (count($branches) > 1) {
            // Auto-select first branch (headquarters first)
            self::set_branch_id(absint($branches[0]['id']));
        } else {
            self::set_branch_id(null);
        }
    }
    
    /**
     * Get current customer data
     *
     * @since 1.0.0
     * @return array|null
     */
    public static function get_customer_data() {
        $customer_id = self::get_customer_id();
        
        if (!$customer_id) {
            return null;
        }
        
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM %i WHERE id = %d",
            $wpdb->prefix . 'saw_customers',
            $customer_id
        ), ARRAY_A);
    }
    
    /**
     * Get current branch data
     *
     * @since 1.0.0
     * @return array|null
     */
    public static function get_branch_data() {
        $branch_id = self::get_branch_id();
        
        if (!$branch_id) {
            return null;
        }
        
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM %i WHERE id = %d AND is_active = 1",
            $wpdb->prefix . 'saw_branches',
            $branch_id
        ), ARRAY_A);
    }
    
    /**
     * Reload context from database
     *
     * @since 1.0.0
     * @return bool
     */
    public static function reload() {
        $instance = self::instance();
        $instance->load_from_database();
        return true;
    }
}