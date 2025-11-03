<?php
/**
 * SAW Permissions Helper Functions
 * 
 * Convenient helper functions for checking permissions in templates and code.
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 * @since 4.10.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if current user can perform action on module
 * 
 * @param string $action Action (list, view, create, edit, delete)
 * @param string|null $module Module slug (auto-detect if null)
 * @return bool
 */
function saw_can($action, $module = null) {
    static $saw_role = null;
    static $auth = null;
    
    if ($auth === null) {
        $auth = new SAW_Auth();
    }
    
    if ($saw_role === null) {
        $saw_role = $auth->get_current_user_role();
    }
    
    if (empty($saw_role)) {
        return false;
    }
    
    if ($module === null) {
        global $saw_current_module;
        $module = $saw_current_module ?? '';
    }
    
    if (empty($module)) {
        return false;
    }
    
    return SAW_Permissions::check($saw_role, $module, $action);
}

/**
 * Check if current user has any permission on module
 * 
 * @param string $module Module slug
 * @return bool
 */
function saw_can_access_module($module) {
    return saw_can('list', $module);
}

/**
 * Get current user's data scope for module
 * 
 * @param string $module Module slug
 * @return string|null Scope (all, customer, branch, department, own)
 */
function saw_get_scope($module) {
    static $saw_role = null;
    static $auth = null;
    
    if ($auth === null) {
        $auth = new SAW_Auth();
    }
    
    if ($saw_role === null) {
        $saw_role = $auth->get_current_user_role();
    }
    
    if (empty($saw_role)) {
        return null;
    }
    
    $permission = SAW_Permissions::get_permission($saw_role, $module, 'list');
    
    return $permission['scope'] ?? null;
}

/**
 * Get modules that current user can access
 * 
 * @return array Module slugs
 */
function saw_get_allowed_modules() {
    static $saw_role = null;
    static $auth = null;
    
    if ($auth === null) {
        $auth = new SAW_Auth();
    }
    
    if ($saw_role === null) {
        $saw_role = $auth->get_current_user_role();
    }
    
    if (empty($saw_role)) {
        return [];
    }
    
    return SAW_Permissions::get_allowed_modules($saw_role);
}

/**
 * Check if user is super admin
 * 
 * @return bool
 */
function saw_is_super_admin() {
    return current_user_can('manage_options');
}

/**
 * Get current SAW role
 * 
 * @return string|null
 */
function saw_get_current_role() {
    static $saw_role = null;
    static $auth = null;
    
    if ($auth === null) {
        $auth = new SAW_Auth();
    }
    
    if ($saw_role === null) {
        $saw_role = $auth->get_current_user_role();
    }
    
    return $saw_role;
}
