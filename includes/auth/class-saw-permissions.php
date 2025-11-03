<?php
/**
 * SAW Permissions Manager - FIXED UPDATE LOGIC
 * 
 * @package SAW_Visitors
 * @version 1.0.1
 * @since 4.10.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Permissions {
    
    private static $cache = [];
    private static $use_object_cache = true;
    private static $cache_ttl = 3600;
    
    public static function check($role, $module, $action) {
        if (empty($role) || empty($module) || empty($action)) {
            return false;
        }
        
        if ($role === 'super_admin') {
            return true;
        }
        
        $cache_key = "{$role}:{$module}:{$action}";
        
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }
        
        if (self::$use_object_cache) {
            $cached = wp_cache_get("saw_perm_{$cache_key}");
            if ($cached !== false) {
                self::$cache[$cache_key] = $cached;
                return $cached;
            }
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'saw_permissions';
        
        $permission = $wpdb->get_var($wpdb->prepare(
            "SELECT allowed FROM {$table} 
             WHERE role = %s AND module = %s AND action = %s 
             LIMIT 1",
            $role,
            $module,
            $action
        ));
        
        if ($permission !== null) {
            $result = (bool) $permission;
            self::set_cache($cache_key, $result);
            return $result;
        }
        
        $permission = $wpdb->get_var($wpdb->prepare(
            "SELECT allowed FROM {$table} 
             WHERE role = %s AND module = %s AND action = '*' 
             LIMIT 1",
            $role,
            $module
        ));
        
        if ($permission !== null) {
            $result = (bool) $permission;
            self::set_cache($cache_key, $result);
            return $result;
        }
        
        $permission = $wpdb->get_var($wpdb->prepare(
            "SELECT allowed FROM {$table} 
             WHERE role = %s AND module = '*' AND action = %s 
             LIMIT 1",
            $role,
            $action
        ));
        
        if ($permission !== null) {
            $result = (bool) $permission;
            self::set_cache($cache_key, $result);
            return $result;
        }
        
        $permission = $wpdb->get_var($wpdb->prepare(
            "SELECT allowed FROM {$table} 
             WHERE role = %s AND module = '*' AND action = '*' 
             LIMIT 1",
            $role
        ));
        
        if ($permission !== null) {
            $result = (bool) $permission;
            self::set_cache($cache_key, $result);
            return $result;
        }
        
        self::set_cache($cache_key, false);
        return false;
    }
    
    public static function get_permission($role, $module, $action) {
        if (empty($role) || empty($module) || empty($action)) {
            return null;
        }
        
        if ($role === 'super_admin') {
            return ['allowed' => true, 'scope' => 'all'];
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'saw_permissions';
        
        $permission = $wpdb->get_row($wpdb->prepare(
            "SELECT allowed, scope FROM {$table} 
             WHERE role = %s AND module = %s AND action = %s 
             LIMIT 1",
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
        
        $permission = $wpdb->get_row($wpdb->prepare(
            "SELECT allowed, scope FROM {$table} 
             WHERE role = %s AND module = %s AND action = '*' 
             LIMIT 1",
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
    
    public static function get_all_for_role($role) {
        if (empty($role)) {
            return [];
        }
        
        if ($role === 'super_admin') {
            return ['*' => ['*' => ['allowed' => true, 'scope' => 'all']]];
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'saw_permissions';
        
        $permissions = $wpdb->get_results($wpdb->prepare(
            "SELECT module, action, allowed, scope 
             FROM {$table} 
             WHERE role = %s 
             ORDER BY module, action",
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
    
    public static function set($role, $module, $action, $allowed, $scope = 'all') {
        if (empty($role) || empty($module) || empty($action)) {
            return false;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'saw_permissions';
        
        self::clear_cache("{$role}:{$module}:{$action}");
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} 
             WHERE role = %s AND module = %s AND action = %s",
            $role,
            $module,
            $action
        ));
        
        if ($exists) {
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
            
            // CRITICAL FIX: update() returns number of rows updated (0 or 1), or false on error
            // 0 means no change needed (value already correct), which is NOT an error!
            return $result !== false;
        }
        
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
    
    public static function delete($role, $module, $action) {
        if (empty($role) || empty($module) || empty($action)) {
            return false;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'saw_permissions';
        
        self::clear_cache("{$role}:{$module}:{$action}");
        
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
    
    public static function get_allowed_modules($role) {
        if ($role === 'super_admin') {
            $all_modules = SAW_Module_Loader::get_all();
            return array_keys($all_modules);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'saw_permissions';
        
        $modules = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT module 
             FROM {$table} 
             WHERE role = %s AND allowed = 1 AND module != '*' AND action = 'list'
             ORDER BY module",
            $role
        ));
        
        return $modules;
    }
    
    private static function set_cache($key, $value) {
        self::$cache[$key] = $value;
        
        if (self::$use_object_cache) {
            wp_cache_set("saw_perm_{$key}", $value, '', self::$cache_ttl);
        }
    }
    
    public static function clear_cache($key = null) {
        if ($key === null) {
            self::$cache = [];
            
            if (self::$use_object_cache) {
                wp_cache_flush();
            }
        } else {
            unset(self::$cache[$key]);
            
            if (self::$use_object_cache) {
                wp_cache_delete("saw_perm_{$key}");
            }
        }
    }
    
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
                    $exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$table} 
                         WHERE role = %s AND module = %s AND action = %s",
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
        
        self::clear_cache();
        
        return $inserted;
    }
}