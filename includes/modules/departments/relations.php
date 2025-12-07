<?php
/**
 * Departments Relations Configuration
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Departments
 * @version     2.0.0 - REFACTORED: Added translations
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// LOAD TRANSLATIONS
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}

$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'departments') 
    : array();

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// RELATIONS CONFIGURATION
// ============================================
return array(
    'users' => array(
        'label' => $tr('rel_users', 'Uživatelé'),
        'icon' => '👥',
        'entity' => 'users',
        'junction_table' => 'saw_user_departments',
        'foreign_key' => 'department_id',
        'local_key' => 'user_id',
        'display_fields' => array('first_name', 'last_name', 'email'),
        'display_format' => '{last_name} {first_name}',
        'route' => 'admin/users/{id}/',
        'order_by' => 'last_name ASC, first_name ASC',
        'limit' => 5,
        'fields' => array(
            'first_name' => array(
                'label' => $tr('rel_first_name', 'Jméno'),
                'type' => 'text',
            ),
            'last_name' => array(
                'label' => $tr('rel_last_name', 'Příjmení'),
                'type' => 'text',
            ),
            'email' => array(
                'label' => $tr('rel_email', 'Email'),
                'type' => 'text',
            ),
            'role' => array(
                'label' => $tr('rel_role', 'Role'),
                'type' => 'text',
                'format' => function($value) use ($tr) {
                    $roles = array(
                        'super_admin' => $tr('role_super_admin', 'Super Admin'),
                        'admin' => $tr('role_admin', 'Admin'),
                        'super_manager' => $tr('role_super_manager', 'Super Manager'),
                        'manager' => $tr('role_manager', 'Manager'),
                        'terminal' => $tr('role_terminal', 'Terminál'),
                    );
                    return $roles[$value] ?? $value;
                },
            ),
            'is_active' => array(
                'label' => $tr('rel_is_active', 'Aktivní'),
                'type' => 'boolean',
                'format' => function($value) use ($tr) {
                    return $value 
                        ? '✓ ' . $tr('yes', 'Ano') 
                        : '✗ ' . $tr('no', 'Ne');
                },
            ),
        ),
    ),
);