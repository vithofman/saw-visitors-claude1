<?php
/**
 * SAW Permissions Manager
 *
 * Manages role-based access control (RBAC) for modules and actions.
 * Implements hierarchical permission checking with caching for performance.
 *
 * @package    SAW_Visitors
 * @subpackage Permissions
 * @version    1.1.0
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW_Permissions Class
 *
 * Handles permission checking, storage, and cache management.
 * Supports wildcard permissions and scope-based access control.
 *
 * @since 1.0.0
 */
class SAW_Permissions {
    
    /**
     * Runtime permission cache
     *
     * @since 1.0.0
     * @var array
     */
    private static $cache = [];
    
    /**
     * Use WordPress object cache
     *
     * @since 1.0.0
     * @var bool
     */
    private static $use_object_cache = true;
    
    /**
     * Cache time-to-live in seconds (1 hour)
     *
     * @since 1.0.0
     * @var int
     */
    private static $cache_ttl = 3600;
    
    /**
     * Cache key prefix
     *
     * @since 1.1.0
     * @var string
     */
    const CACHE_PREFIX = 'saw_perm_';
    
    /**
     * Transient prefix for SAW cache
     *
     * @since 1.1.0
     * @var string
     */
    const TRANSIENT_PREFIX = 'saw_cache_';
    
    /**
     * Check if role has permission for module action
     *
     * Implements hierarchical permission checking:
     * 1. Exact match (role, module, action)
     * 2. Module wildcard (role, module, *)
     * 3. Action wildcard (role, *, action)
     * 4. Full wildcard (role, *, *)
     *
     * @since 1.0.0
     * @param string $role   User role
     * @param string $module Module name
     * @param string $action Action name
     * @return bool True if allowed
     */
    public static function check($role, $module, $action) {
        if (empty($role) || empty($module) || empty($action)) {
            return false;
        }
        
        // Super admin has all permissions
        if ($role === 'super_admin') {
            return true;
        }
        
        // Check runtime cache
        $cache_key = "{$role}:{$module}:{$action}";
        
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }
        
        // Check object cache
        if (self::$use_object_cache) {
            $cached = wp_cache_get(self::CACHE_PREFIX . $cache_key);
            if ($cached !== false) {
                self::$cache[$cache_key] = $cached;
                return $cached;
            }
        }
        
        // Check database with hierarchical fallback
        $result = self::check_database($role, $module, $action);
        
        self::set_cache($cache_key, $result);
        return $result;
    }
    
    /**
     * Check permission in database with hierarchical fallback
     *
     * @since 1.1.0
     * @param string $role   User role
     * @param string $module Module name
     * @param string $action Action name
     * @return bool True if allowed
     */
    private static function check_database($role, $module, $action) {
        global $wpdb;
        $table = $wpdb->prefix . 'saw_permissions';
        
        // Priority order: exact → module wildcard → action wildcard → full wildcard
        $checks = [
            [$role, $module, $action],
            [$role, $module, '*'],
            [$role, '*', $action],
            [$role, '*', '*'],
        ];
        
        foreach ($checks as $check) {
            $permission = $wpdb->get_var($wpdb->prepare(
                "SELECT allowed FROM %i 
                 WHERE role = %s AND module = %s AND action = %s 
                 LIMIT 1",
                $table,
                $check[0],
                $check[1],
                $check[2]
            ));
            
            if ($permission !== null) {
                return (bool) $permission;
            }
        }
        
        return false;
    }
    
    /**
     * Get permission details for role, module, and action
     *
     * Returns both allowed status and scope.
     *
     * @since 1.0.0
     * @param string $role   User role
     * @param string $module Module name
     * @param string $action Action name
     * @return array|null Permission data or null
     */
    public static function get_permission($role, $module, $action) {
        if (empty($role) || empty($module) || empty($action)) {
            return null;
        }
        
        // Super admin has all permissions
        if ($role === 'super_admin') {
            return ['allowed' => true, 'scope' => 'all'];
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'saw_permissions';
        
        // Check exact match first
        $permission = $wpdb->get_row($wpdb->prepare(
            "SELECT allowed, scope FROM %i 
             WHERE role = %s AND module = %s AND action = %s 
             LIMIT 1",
            $table,
            $role,
            $module,
            $action
        ), ARRAY_A);
        
        if ($permission) {
            return [
                'allowed' => (bool) $permission['allowed'],
                'scope' => $permission['scope'] ?? 'all',
            ];
        }
        
        // Check module wildcard
        $permission = $wpdb->get_row($wpdb->prepare(
            "SELECT allowed, scope FROM %i 
             WHERE role = %s AND module = %s AND action = '*' 
             LIMIT 1",
            $table,
            $role,
            $module
        ), ARRAY_A);
        
        if ($permission) {
            return [
                'allowed' => (bool) $permission['allowed'],
                'scope' => $permission['scope'] ?? 'all',
            ];
        }
        
        return null;
    }
    
    /**
     * Get all permissions for role
     *
     * Returns nested array: [module][action] => [allowed, scope]
     *
     * @since 1.0.0
     * @param string $role User role
     * @return array Permissions array
     */
    public static function get_all_for_role($role) {
        if (empty($role)) {
            return [];
        }
        
        // Super admin has all permissions
        if ($role === 'super_admin') {
            return ['*' => ['*' => ['allowed' => true, 'scope' => 'all']]];
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'saw_permissions';
        
        $permissions = $wpdb->get_results($wpdb->prepare(
            "SELECT module, action, allowed, scope 
             FROM %i 
             WHERE role = %s 
             ORDER BY module, action",
            $table,
            $role
        ), ARRAY_A);
        
        $result = [];
        foreach ($permissions as $perm) {
            if (!isset($result[$perm['module']])) {
                $result[$perm['module']] = [];
            }
            $result[$perm['module']][$perm['action']] = [
                'allowed' => (bool) $perm['allowed'],
                'scope' => $perm['scope'] ?? 'all',
            ];
        }
        
        return $result;
    }
    
    /**
     * Set permission for role, module, and action
     *
     * Updates existing permission or creates new one.
     * Clears all permission cache on success.
     *
     * @since 1.0.0
     * @param string $role    User role
     * @param string $module  Module name
     * @param string $action  Action name
     * @param bool   $allowed Whether action is allowed
     * @param string $scope   Access scope (default: 'all')
     * @return bool Success
     */
    public static function set($role, $module, $action, $allowed, $scope = 'all') {
        if (empty($role) || empty($module) || empty($action)) {
            return false;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'saw_permissions';
        
        // Clear cache before modification
        self::clear_cache();
        
        // Check if permission exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM %i 
             WHERE role = %s AND module = %s AND action = %s",
            $table,
            $role,
            $module,
            $action
        ));
        
        if ($exists) {
            // Update existing permission
            $result = $wpdb->update(
                $table,
                [
                    'allowed' => $allowed ? 1 : 0,
                    'scope' => $scope,
                ],
                [
                    'role' => $role,
                    'module' => $module,
                    'action' => $action,
                ],
                ['%d', '%s'],
                ['%s', '%s', '%s']
            );
            
            // Returns false on error, 0 or 1 on success
            return $result !== false;
        }
        
        // Insert new permission
        return $wpdb->insert(
            $table,
            [
                'role' => $role,
                'module' => $module,
                'action' => $action,
                'allowed' => $allowed ? 1 : 0,
                'scope' => $scope,
            ],
            ['%s', '%s', '%s', '%d', '%s']
        ) !== false;
    }
    
    /**
     * Delete permission
     *
     * Removes permission record and clears cache.
     *
     * @since 1.0.0
     * @param string $role   User role
     * @param string $module Module name
     * @param string $action Action name
     * @return bool Success
     */
    public static function delete($role, $module, $action) {
        if (empty($role) || empty($module) || empty($action)) {
            return false;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'saw_permissions';
        
        // Clear cache before deletion
        self::clear_cache();
        
        return $wpdb->delete(
            $table,
            [
                'role' => $role,
                'module' => $module,
                'action' => $action,
            ],
            ['%s', '%s', '%s']
        ) !== false;
    }
    
    /**
     * Get allowed modules for role
     *
     * Returns list of module slugs that role has 'list' permission for.
     *
     * @since 1.0.0
     * @param string $role User role
     * @return array Module slugs
     */
    public static function get_allowed_modules($role) {
        // Super admin has access to all modules
        if ($role === 'super_admin') {
            $all_modules = SAW_Module_Loader::get_all();
            return array_keys($all_modules);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'saw_permissions';
        
        $modules = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT module 
             FROM %i 
             WHERE role = %s AND allowed = 1 AND module != '*' AND action = 'list'
             ORDER BY module",
            $table,
            $role
        ));
        
        return $modules;
    }
    
    /**
     * Set cache value
     *
     * @since 1.0.0
     * @param string $key   Cache key
     * @param mixed  $value Cache value
     * @return void
     */
    private static function set_cache($key, $value) {
        self::$cache[$key] = $value;
        
        if (self::$use_object_cache) {
            wp_cache_set(self::CACHE_PREFIX . $key, $value, '', self::$cache_ttl);
        }
    }
    
    /**
     * Clear permission cache
     *
     * Clears runtime cache, object cache, and related transients.
     * Safe implementation that only clears SAW-specific cache.
     *
     * @since 1.0.0
     * @param string|null $key Specific key to clear, or null for all
     * @return void
     */
    public static function clear_cache($key = null) {
        if ($key === null) {
            // Clear all SAW permission cache
            self::$cache = [];
            
            if (self::$use_object_cache) {
                global $wpdb;
                $table = $wpdb->prefix . 'saw_permissions';
                
                // Get all unique cache keys and delete them
                $permissions = $wpdb->get_results($wpdb->prepare(
                    "SELECT DISTINCT role, module, action FROM %i",
                    $table
                ), ARRAY_A);
                
                foreach ($permissions as $perm) {
                    $cache_key = "{$perm['role']}:{$perm['module']}:{$perm['action']}";
                    wp_cache_delete(self::CACHE_PREFIX . $cache_key);
                }
            }
            
            // Clear SAW-specific transients safely
            self::clear_saw_transients();
            
        } else {
            // Clear specific key
            unset(self::$cache[$key]);
            
            if (self::$use_object_cache) {
                wp_cache_delete(self::CACHE_PREFIX . $key);
            }
        }
    }
    
    /**
     * Clear SAW-specific transients
     *
     * Safely removes only SAW cache transients without affecting other plugins.
     *
     * @since 1.1.0
     * @return void
     */
    private static function clear_saw_transients() {
        global $wpdb;
        
        // Clear known SAW entity caches
        $known_transients = [
            'saw_cache_users_list',
            'saw_cache_branches_list',
            'saw_cache_departments_list',
            'saw_cache_customers_list',
        ];
        
        foreach ($known_transients as $transient) {
            delete_transient($transient);
        }
        
        // Clear all SAW transients using safe pattern matching
        $wpdb->query($wpdb->prepare(
            "DELETE FROM %i 
             WHERE option_name LIKE %s 
             OR option_name LIKE %s",
            $wpdb->options,
            $wpdb->esc_like('_transient_' . self::TRANSIENT_PREFIX) . '%',
            $wpdb->esc_like('_transient_timeout_' . self::TRANSIENT_PREFIX) . '%'
        ));
    }
    
    /**
     * Bulk insert permissions from schema
     *
     * Inserts multiple permissions at once from configuration array.
     * Skips existing permissions.
     *
     * @since 1.0.0
     * @param array $schema Permission schema array
     * @return int Number of permissions inserted
     */
    public static function bulk_insert_from_schema($schema) {
        if (empty($schema)) {
            return 0;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'saw_permissions';
        
        $inserted = 0;
        
        foreach ($schema as $role => $modules) {
            foreach ($modules as $module => $config) {
                $actions = $config['actions'] ?? [];
                $scope = $config['scope'] ?? 'all';
                
                if (empty($actions)) {
                    continue;
                }
                
                foreach ($actions as $action) {
                    // Check if permission already exists
                    $exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM %i 
                         WHERE role = %s AND module = %s AND action = %s",
                        $table,
                        $role,
                        $module,
                        $action
                    ));
                    
                    if (!$exists) {
                        $result = $wpdb->insert(
                            $table,
                            [
                                'role' => $role,
                                'module' => $module,
                                'action' => $action,
                                'allowed' => 1,
                                'scope' => $scope,
                            ],
                            ['%s', '%s', '%s', '%d', '%s']
                        );
                        
                        if ($result) {
                            $inserted++;
                        }
                    }
                }
            }
        }
        
        // Clear cache after bulk insert
        self::clear_cache();
        
        return $inserted;
    }
}