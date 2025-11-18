<?php
/**
 * SAW Context - Database-First Architecture
 *
 * Customer & Branch context stored in DATABASE (saw_users table).
 * NO sessions for customer/branch (only for user_id & role).
 * Single source of truth: Database.
 *
 * @package    SAW_Visitors
 * @subpackage Core
 * @since      1.0.0
 * @version    6.1.0 - FIXED: Super Admin Branch Loading
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
    
    private function __construct() {
        // Defer loading until we are sure user is available
        if (did_action('init')) {
            $this->load_from_database();
            $this->initialized = true;
        }
    }
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        // Lazy initialization if not done yet
        if (!self::$instance->initialized) {
             // Only try to load if WP environment is ready
             if (function_exists('is_user_logged_in')) {
                 self::$instance->load_from_database();
                 self::$instance->initialized = true;
             }
        }
        
        return self::$instance;
    }
    
    private function load_from_database() {
        if (!is_user_logged_in()) {
            $this->load_fallback_customer();
            return;
        }
        
        global $wpdb;
        $wp_user_id = get_current_user_id();
        
        $saw_user = $wpdb->get_row($wpdb->prepare(
            "SELECT id, customer_id, context_customer_id, context_branch_id, role 
             FROM %i 
             WHERE wp_user_id = %d AND is_active = 1",
            $wpdb->prefix . 'saw_users',
            $wp_user_id
        ), ARRAY_A);
        
        if (!$saw_user) {
            // Super Admin Fallback (když nemá záznam v saw_users)
            if (current_user_can('manage_options')) {
                $this->role = 'super_admin';
                // Zkusíme načíst kontext z user meta (pokud se tam ukládá)
                $this->customer_id = (int) get_user_meta($wp_user_id, 'saw_context_customer_id', true);
                $this->branch_id = (int) get_user_meta($wp_user_id, 'saw_context_branch_id', true);
                
                if (!$this->customer_id) {
                    $this->load_fallback_customer();
                }
                return;
            }
            
            $this->load_fallback_customer();
            return;
        }
        
        $this->saw_user_id = absint($saw_user['id']);
        
        // Přebití role pro Super Admina (WP admin má vždy super_admin práva)
        if (current_user_can('manage_options')) {
            $this->role = 'super_admin';
        } else {
            $this->role = $saw_user['role'];
        }
        
        $this->customer_id = $this->resolve_customer_id($saw_user);
        $this->branch_id = $this->resolve_branch_id($saw_user);
        
        // AUTO-SELECT: Pokud není vybraná pobočka a uživatel může vybírat pobočky
        if (!$this->branch_id && $this->customer_id && in_array($this->role, ['super_admin', 'admin', 'super_manager'])) {
            $this->auto_select_branch();
        }
    }
    
    private function resolve_customer_id($saw_user) {
        if ($this->role === 'super_admin') {
            return $saw_user['context_customer_id'] 
                ? absint($saw_user['context_customer_id']) 
                : ($saw_user['customer_id'] ? absint($saw_user['customer_id']) : null);
        }
        
        return $saw_user['customer_id'] ? absint($saw_user['customer_id']) : null;
    }
    
    private function resolve_branch_id($saw_user) {
        // Super Admin a Admin používají context_branch_id (ze switcheru)
        if ($this->role === 'super_admin' || $this->role === 'admin') {
            return $saw_user['context_branch_id'] ? absint($saw_user['context_branch_id']) : null;
        }
        
        // Super Manager používá pevné branch_id (pokud nemá switcher)
        if ($this->role === 'super_manager') {
             // Pokud má super manager povolený switcher (což v SAW obvykle nemá),
             // použil by context. Zde bereme pevné.
             return $saw_user['branch_id'] ? absint($saw_user['branch_id']) : null;
        }
        
        // Ostatní (Manager, Terminal) mají pevné branch_id
        return $saw_user['branch_id'] ? absint($saw_user['branch_id']) : null;
    }
    
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
            
            // Update database only if we have a SAW user record
            if ($this->saw_user_id) {
                $wpdb->update(
                    $wpdb->prefix . 'saw_users',
                    ['context_branch_id' => $branch_id],
                    ['id' => $this->saw_user_id],
                    ['%d'],
                    ['%d']
                );
            } elseif (current_user_can('manage_options')) {
                 // Update meta for super admin without saw_user record
                 update_user_meta(get_current_user_id(), 'saw_context_branch_id', $branch_id);
            }
            
            $this->branch_id = $branch_id;
        }
    }
    
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
    
    public static function get_customer_id() {
        return self::instance()->customer_id;
    }
    
    public static function get_branch_id() {
        return self::instance()->branch_id;
    }
    
    public static function get_saw_user_id() {
        return self::instance()->saw_user_id;
    }
    
    public static function get_role() {
        return self::instance()->role;
    }
    
    public static function set_customer_id($customer_id) {
        $customer_id = absint($customer_id);
        
        if (!$customer_id || !current_user_can('manage_options')) {
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
    
    public static function set_branch_id($branch_id) {
        $branch_id = $branch_id ? absint($branch_id) : null;
        $instance = self::instance();
        
        if (!$instance->saw_user_id) {
             // Fallback for super admin without record
             if (current_user_can('manage_options')) {
                 update_user_meta(get_current_user_id(), 'saw_context_branch_id', $branch_id);
                 $instance->branch_id = $branch_id;
                 return true;
             }
             return false;
        }
        
        // Manager restrictions removed from here to allow flexibility
        
        if ($branch_id && !self::validate_branch_access($branch_id)) {
             return false;
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
    
    private static function validate_branch_access($branch_id) {
        global $wpdb;
        $instance = self::instance();
        
        if ($instance->role === 'super_admin') return true;
        
        $branch = $wpdb->get_row($wpdb->prepare(
            "SELECT customer_id FROM %i WHERE id = %d AND is_active = 1",
            $wpdb->prefix . 'saw_branches',
            $branch_id
        ), ARRAY_A);
        
        if (!$branch || $branch['customer_id'] != $instance->customer_id) return false;
        
        if ($instance->role === 'admin') return true;
        
        if ($instance->role === 'super_manager' && class_exists('SAW_User_Branches')) {
            return SAW_User_Branches::is_user_allowed_branch($instance->saw_user_id, $branch_id);
        }
        
        return false;
    }
    
    private static function handle_branch_on_customer_switch($customer_id) {
        global $wpdb;
        
        $branches = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM %i WHERE customer_id = %d AND is_active = 1 ORDER BY is_headquarters DESC, name ASC LIMIT 2",
            $wpdb->prefix . 'saw_branches',
            $customer_id
        ), ARRAY_A);
        
        if (count($branches) >= 1) {
            self::set_branch_id(absint($branches[0]['id']));
        } else {
            self::set_branch_id(null);
        }
    }
    
    public static function get_customer_data() {
        $id = self::get_customer_id();
        if (!$id) return null;
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id = %d", $wpdb->prefix . 'saw_customers', $id), ARRAY_A);
    }
    
    public static function get_branch_data() {
        $id = self::get_branch_id();
        if (!$id) return null;
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id = %d AND is_active = 1", $wpdb->prefix . 'saw_branches', $id), ARRAY_A);
    }
    
    public static function reload() {
        self::instance()->load_from_database();
        return true;
    }
}