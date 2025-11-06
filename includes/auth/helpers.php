<?php
/**
 * SAW Permissions Helper Functions
 *
 * Convenient helper functions for checking permissions in templates and code.
 * Provides simplified API for common permission checks.
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
 * Get cached SAW_Auth instance and role
 *
 * Helper to avoid repeated initialization across helper functions.
 *
 * @since 1.1.0
 * @return array [auth_instance, current_role]
 */
function saw_get_auth_context() {
    static $auth = null;
    static $role = null;
    
    if ($auth === null) {
        $auth = new SAW_Auth();
    }
    
    if ($role === null) {
        $role = $auth->get_current_user_role();
    }
    
    return [$auth, $role];
}

/**
 * Check if current user can perform action on module
 *
 * Primary permission check helper. Auto-detects module if not provided.
 *
 * @since 1.0.0
 * @param string      $action Action (list, view, create, edit, delete)
 * @param string|null $module Module slug (auto-detect if null)
 * @return bool True if user has permission
 */
function saw_can($action, $module = null) {
    list($auth, $role) = saw_get_auth_context();
    
    if (empty($role)) {
        return false;
    }
    
    // Auto-detect module from global if not provided
    if ($module === null) {
        global $saw_current_module;
        $module = $saw_current_module ?? '';
    }
    
    if (empty($module)) {
        return false;
    }
    
    return SAW_Permissions::check($role, $module, $action);
}

/**
 * Check if current user can list/access module
 *
 * Shorthand for checking 'list' permission.
 *
 * @since 1.0.0
 * @param string $module Module slug
 * @return bool True if user can access module
 */
function saw_can_access_module($module) {
    return saw_can('list', $module);
}

/**
 * Check if current user can view items in module
 *
 * Shorthand for checking 'view' permission.
 *
 * @since 1.1.0
 * @param string|null $module Module slug (auto-detect if null)
 * @return bool True if user can view
 */
function saw_can_view($module = null) {
    return saw_can('view', $module);
}

/**
 * Check if current user can create items in module
 *
 * Shorthand for checking 'create' permission.
 *
 * @since 1.1.0
 * @param string|null $module Module slug (auto-detect if null)
 * @return bool True if user can create
 */
function saw_can_create($module = null) {
    return saw_can('create', $module);
}

/**
 * Check if current user can edit items in module
 *
 * Shorthand for checking 'edit' permission.
 *
 * @since 1.1.0
 * @param string|null $module Module slug (auto-detect if null)
 * @return bool True if user can edit
 */
function saw_can_edit($module = null) {
    return saw_can('edit', $module);
}

/**
 * Check if current user can delete items in module
 *
 * Shorthand for checking 'delete' permission.
 *
 * @since 1.1.0
 * @param string|null $module Module slug (auto-detect if null)
 * @return bool True if user can delete
 */
function saw_can_delete($module = null) {
    return saw_can('delete', $module);
}

/**
 * Get current user's data scope for module
 *
 * Returns scope that determines what data user can access.
 * Possible values: 'all', 'customer', 'branch', 'department', 'own'
 *
 * @since 1.0.0
 * @param string $module Module slug
 * @return string|null Scope or null if no permission
 */
function saw_get_scope($module) {
    list($auth, $role) = saw_get_auth_context();
    
    if (empty($role)) {
        return null;
    }
    
    $permission = SAW_Permissions::get_permission($role, $module, 'list');
    
    return $permission['scope'] ?? null;
}

/**
 * Get modules that current user can access
 *
 * Returns array of module slugs user has 'list' permission for.
 *
 * @since 1.0.0
 * @return array Module slugs
 */
function saw_get_allowed_modules() {
    list($auth, $role) = saw_get_auth_context();
    
    if (empty($role)) {
        return [];
    }
    
    return SAW_Permissions::get_allowed_modules($role);
}

/**
 * Check if user is super admin
 *
 * Super admins have WordPress 'manage_options' capability.
 *
 * @since 1.0.0
 * @return bool True if super admin
 */
function saw_is_super_admin() {
    return current_user_can('manage_options');
}

/**
 * Get current SAW role
 *
 * Returns role slug for current user.
 * Possible values: 'super_admin', 'admin', 'super_manager', 'manager', 'terminal'
 *
 * @since 1.0.0
 * @return string|null Role slug or null
 */
function saw_get_current_role() {
    list($auth, $role) = saw_get_auth_context();
    return $role;
}

/**
 * Check if current user has admin role
 *
 * @since 1.1.0
 * @return bool True if admin
 */
function saw_is_admin() {
    $role = saw_get_current_role();
    return $role === 'admin';
}

/**
 * Check if current user has manager role
 *
 * Includes both 'manager' and 'super_manager' roles.
 *
 * @since 1.1.0
 * @return bool True if manager or super_manager
 */
function saw_is_manager() {
    $role = saw_get_current_role();
    return $role === 'manager' || $role === 'super_manager';
}

/**
 * Check if current user has terminal role
 *
 * @since 1.1.0
 * @return bool True if terminal
 */
function saw_is_terminal() {
    $role = saw_get_current_role();
    return $role === 'terminal';
}

/**
 * Get all permissions for current user
 *
 * Returns nested array: [module][action] => [allowed, scope]
 *
 * @since 1.1.0
 * @return array Permissions array
 */
function saw_get_all_permissions() {
    list($auth, $role) = saw_get_auth_context();
    
    if (empty($role)) {
        return [];
    }
    
    return SAW_Permissions::get_all_for_role($role);
}

/**
 * Check if current user can perform any action on module
 *
 * Checks if user has at least one allowed permission for module.
 *
 * @since 1.1.0
 * @param string $module Module slug
 * @return bool True if user has any permission
 */
function saw_has_module_access($module) {
    $actions = ['list', 'view', 'create', 'edit', 'delete'];
    
    foreach ($actions as $action) {
        if (saw_can($action, $module)) {
            return true;
        }
    }
    
    return false;
}