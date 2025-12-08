<?php
/**
 * Permissions Module Configuration
 * 
 * Defines all settings, fields, capabilities, and behavior for the Permissions module.
 * This module manages role-based access control (RBAC) across all modules.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Permissions
 * @since       4.10.0
 * @version     2.0.0 - FIXED: Added translation support
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// TRANSLATIONS SETUP
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}

$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'permissions') 
    : array();

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// CONFIGURATION
// ============================================
return array(
    // ================================================
    // BASIC MODULE INFO
    // ================================================
    
    'entity' => 'permissions',
    'table' => 'saw_permissions',
    'singular' => $tr('singular', 'Oprávnění'),
    'plural' => $tr('plural', 'Správa oprávnění'),
    'route' => 'admin/permissions',
    'icon' => '🔐',
    
    // ================================================
    // ACCESS CONTROL
    // ================================================
    
    // Only super_admin can manage permissions
    'allowed_roles' => array('super_admin'),
    
    // No customer/branch filtering (global module)
    'filter_by_customer' => false,
    'filter_by_branch' => false,
    
    // ================================================
    // CAPABILITIES (WordPress permissions)
    // ================================================
    
    'capabilities' => array(
        'list' => 'manage_options',
        'view' => 'manage_options',
        'create' => 'manage_options',
        'edit' => 'manage_options',
        'delete' => 'manage_options',
    ),
    
    // ================================================
    // ROLE OPTIONS (for dropdowns)
    // ================================================
    'role_options' => array(
        'admin' => $tr('role_admin', 'Admin'),
        'super_manager' => $tr('role_super_manager', 'Super Manager'),
        'manager' => $tr('role_manager', 'Manager'),
        'terminal' => $tr('role_terminal', 'Terminál'),
    ),
    
    // ================================================
    // SCOPE OPTIONS (for dropdowns)
    // ================================================
    'scope_options' => array(
        'all' => $tr('scope_all', 'Všechna data'),
        'customer' => $tr('scope_customer', 'Jen můj zákazník'),
        'branch' => $tr('scope_branch', 'Jen má pobočka'),
        'department' => $tr('scope_department', 'Jen má oddělení'),
        'own' => $tr('scope_own', 'Jen já'),
    ),
    
    // ================================================
    // FIELD DEFINITIONS
    // ================================================
    
    'fields' => array(
        
        // Role
        'role' => array(
            'type' => 'select',
            'label' => $tr('field_role', 'Role'),
            'required' => true,
        ),
        
        // Module
        'module' => array(
            'type' => 'text',
            'label' => $tr('field_module', 'Modul'),
            'required' => true,
        ),
        
        // Action
        'action' => array(
            'type' => 'text',
            'label' => $tr('field_action', 'Akce'),
            'required' => true,
        ),
        
        // Allowed
        'allowed' => array(
            'type' => 'checkbox',
            'label' => $tr('field_allowed', 'Povoleno'),
            'default' => 1,
        ),
        
        // Scope (data visibility)
        'scope' => array(
            'type' => 'select',
            'label' => $tr('field_scope', 'Rozsah dat'),
            'required' => true,
        ),
    ),
    
    // ================================================
    // LIST VIEW CONFIGURATION
    // ================================================
    
    'list_config' => array(
        'columns' => array('role', 'module', 'action', 'allowed', 'scope'),
        'searchable' => array('role', 'module', 'action'),
        'sortable' => array('role', 'module', 'action'),
        'filters' => array(
            'role' => true,
            'allowed' => true,
        ),
        'per_page' => 50,
        'enable_detail_modal' => false,
    ),
    
    // ================================================
    // CACHING CONFIGURATION
    // ================================================
    
    'cache' => array(
        'enabled' => true,
        'ttl' => 3600, // 1 hour (permissions change rarely)
        'invalidate_on' => array('save', 'delete'),
    ),
);