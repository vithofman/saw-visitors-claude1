<?php
/**
 * SAW Table Permissions Wrapper
 *
 * Provides permission checking for SAW Table components.
 * Integrates with SAW_Permissions system.
 *
 * @package     SAW_Visitors
 * @subpackage  Components/SAWTable
 * @version     1.0.0
 * @since       3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Table Permissions Class
 *
 * @since 3.0.0
 */
class SAW_Table_Permissions {
    
    /**
     * Current user's SAW role
     * @var string|null
     */
    private static $current_role = null;
    
    /**
     * Current user's SAW user data
     * @var array|null
     */
    private static $current_user = null;
    
    /**
     * Permission cache
     * @var array
     */
    private static $cache = [];
    
    /**
     * Available actions
     */
    const ACTION_LIST = 'list';
    const ACTION_VIEW = 'view';
    const ACTION_CREATE = 'create';
    const ACTION_EDIT = 'edit';
    const ACTION_DELETE = 'delete';
    
    /**
     * Role hierarchy (higher index = more permissions)
     */
    const ROLE_HIERARCHY = [
        'terminal' => 0,
        'manager' => 1,
        'super_manager' => 2,
        'admin' => 3,
        'super_admin' => 4,
    ];
    
    /**
     * Initialize permissions for current user
     *
     * @since 1.0.0
     */
    public static function init() {
        if (self::$current_role !== null) {
            return; // Already initialized
        }
        
        self::$current_user = self::load_current_user();
        self::$current_role = self::$current_user['role'] ?? 'admin';
    }
    
    /**
     * Load current user's SAW data
     *
     * @return array
     */
    private static function load_current_user() {
        global $wpdb;
        
        $wp_user_id = get_current_user_id();
        
        if (!$wp_user_id) {
            return [
                'id' => 0,
                'role' => 'guest',
                'customer_id' => null,
                'branch_id' => null,
            ];
        }
        
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT id, role, customer_id, branch_id 
             FROM {$wpdb->prefix}saw_users 
             WHERE wp_user_id = %d",
            $wp_user_id
        ), ARRAY_A);
        
        if (!$user) {
            return [
                'id' => 0,
                'role' => 'admin', // Default fallback
                'customer_id' => null,
                'branch_id' => null,
            ];
        }
        
        return $user;
    }
    
    /**
     * Get current user's SAW role
     *
     * @return string
     */
    public static function get_role() {
        if (self::$current_role === null) {
            self::init();
        }
        return self::$current_role;
    }
    
    /**
     * Get current user's SAW data
     *
     * @return array
     */
    public static function get_current_user() {
        if (self::$current_user === null) {
            self::init();
        }
        return self::$current_user;
    }
    
    /**
     * Get current user's customer ID
     *
     * @return int|null
     */
    public static function get_customer_id() {
        $user = self::get_current_user();
        return $user['customer_id'] ?? null;
    }
    
    /**
     * Get current user's branch ID
     *
     * @return int|null
     */
    public static function get_branch_id() {
        $user = self::get_current_user();
        return $user['branch_id'] ?? null;
    }
    
    /**
     * Check if user can perform action on module
     *
     * @param string $module Module slug
     * @param string $action Action name
     * @return bool
     */
    public static function can($module, $action) {
        $role = self::get_role();
        
        // Super admin can do everything
        if ($role === 'super_admin') {
            return true;
        }
        
        // Check cache
        $cache_key = "{$role}:{$module}:{$action}";
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }
        
        // Use SAW_Permissions class if available
        if (class_exists('SAW_Permissions') && method_exists('SAW_Permissions', 'check')) {
            $result = SAW_Permissions::check($role, $module, $action);
        } else {
            // Fallback: use function if available
            if (function_exists('saw_can')) {
                $result = saw_can($action, $module);
            } else {
                // Ultimate fallback: check against default permissions
                $result = self::check_default_permissions($role, $module, $action);
            }
        }
        
        self::$cache[$cache_key] = $result;
        return $result;
    }
    
    /**
     * Check against default permissions
     *
     * @param string $role   User role
     * @param string $module Module slug
     * @param string $action Action name
     * @return bool
     */
    private static function check_default_permissions($role, $module, $action) {
        // Default permission matrix
        $defaults = [
            self::ACTION_LIST => ['super_admin', 'admin', 'super_manager', 'manager'],
            self::ACTION_VIEW => ['super_admin', 'admin', 'super_manager', 'manager'],
            self::ACTION_CREATE => ['super_admin', 'admin', 'super_manager'],
            self::ACTION_EDIT => ['super_admin', 'admin', 'super_manager'],
            self::ACTION_DELETE => ['super_admin', 'admin'],
        ];
        
        $allowed_roles = $defaults[$action] ?? [];
        
        return in_array($role, $allowed_roles);
    }
    
    /**
     * Check if user can view module
     *
     * @param string $module Module slug
     * @return bool
     */
    public static function canView($module) {
        return self::can($module, self::ACTION_VIEW);
    }
    
    /**
     * Check if user can edit in module
     *
     * @param string $module Module slug
     * @return bool
     */
    public static function canEdit($module) {
        return self::can($module, self::ACTION_EDIT);
    }
    
    /**
     * Check if user can delete in module
     *
     * @param string $module Module slug
     * @return bool
     */
    public static function canDelete($module) {
        return self::can($module, self::ACTION_DELETE);
    }
    
    /**
     * Check if user can create in module
     *
     * @param string $module Module slug
     * @return bool
     */
    public static function canCreate($module) {
        return self::can($module, self::ACTION_CREATE);
    }
    
    /**
     * Check if user can list items in module
     *
     * @param string $module Module slug
     * @return bool
     */
    public static function canList($module) {
        return self::can($module, self::ACTION_LIST);
    }
    
    /**
     * Get allowed actions for module
     *
     * @param string $module Module slug
     * @return array
     */
    public static function getAllowedActions($module) {
        $actions = [];
        
        $all_actions = [
            self::ACTION_LIST,
            self::ACTION_VIEW,
            self::ACTION_CREATE,
            self::ACTION_EDIT,
            self::ACTION_DELETE,
        ];
        
        foreach ($all_actions as $action) {
            if (self::can($module, $action)) {
                $actions[] = $action;
            }
        }
        
        return $actions;
    }
    
    /**
     * Filter action buttons based on permissions
     *
     * @param array  $buttons Button configurations
     * @param string $module  Module slug
     * @return array Filtered buttons
     */
    public static function filterActionButtons($buttons, $module) {
        return array_filter($buttons, function($button) use ($module) {
            // If no permission specified, allow
            if (empty($button['permission'])) {
                return true;
            }
            
            $permission = $button['permission'];
            
            // Handle "action:module" format
            if (strpos($permission, ':') !== false) {
                list($action, $perm_module) = explode(':', $permission, 2);
                return self::can($perm_module, $action);
            }
            
            // Simple action format
            return self::can($module, $permission);
        });
    }
    
    /**
     * Check if current role is at least as high as required
     *
     * @param string $required_role Required minimum role
     * @return bool
     */
    public static function hasMinimumRole($required_role) {
        $current = self::get_role();
        
        $current_level = self::ROLE_HIERARCHY[$current] ?? 0;
        $required_level = self::ROLE_HIERARCHY[$required_role] ?? 0;
        
        return $current_level >= $required_level;
    }
    
    /**
     * Check if user is super admin
     *
     * @return bool
     */
    public static function isSuperAdmin() {
        return self::get_role() === 'super_admin';
    }
    
    /**
     * Check if user is admin or higher
     *
     * @return bool
     */
    public static function isAdmin() {
        return self::hasMinimumRole('admin');
    }
    
    /**
     * Check if user is manager or higher
     *
     * @return bool
     */
    public static function isManager() {
        return self::hasMinimumRole('manager');
    }
    
    /**
     * Clear permission cache
     *
     * @since 1.0.0
     */
    public static function clearCache() {
        self::$cache = [];
    }
    
    /**
     * Reset state (useful for testing)
     *
     * @since 1.0.0
     */
    public static function reset() {
        self::$current_role = null;
        self::$current_user = null;
        self::$cache = [];
    }
}
