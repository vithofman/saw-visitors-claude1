<?php
/**
 * Users Relations Configuration
 * 
 * Defines related data that should be displayed in user detail view.
 * Shows branch and departments associated with the user.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Users
 * @version     2.0.0 - ADDED: Translation support
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
    ? saw_get_translations($lang, 'admin', 'users') 
    : array();

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// RELATIONS CONFIGURATION
// ============================================
return array(
    'branches' => array(
        'label' => $tr('rel_branch', 'Pobočka'),
        'icon' => '🏢',
        'entity' => 'branches',
        'foreign_key' => 'id',
        'local_key' => 'branch_id',
        'display_fields' => array('name', 'code'),
        'route' => 'admin/branches/{id}/',
        'order_by' => 'name ASC',
        'custom_query' => true,
        'fields' => array(
            'name' => array(
                'label' => $tr('rel_branch_name', 'Název'),
                'type' => 'text',
            ),
            'code' => array(
                'label' => $tr('rel_branch_code', 'Kód'),
                'type' => 'text',
            ),
            'city' => array(
                'label' => $tr('rel_branch_city', 'Město'),
                'type' => 'text',
            ),
            'is_active' => array(
                'label' => $tr('rel_branch_active', 'Aktivní'),
                'type' => 'boolean',
                'format' => function($value) use ($tr) {
                    return $value 
                        ? '✓ ' . $tr('yes', 'Ano') 
                        : '✗ ' . $tr('no', 'Ne');
                },
            ),
        ),
    ),
    'departments' => array(
        'label' => $tr('rel_departments', 'Oddělení'),
        'icon' => '🏭',
        'entity' => 'departments',
        'junction_table' => 'saw_user_departments',
        'foreign_key' => 'user_id',
        'local_key' => 'department_id',
        'display_fields' => array('department_number', 'name'),
        'route' => 'admin/departments/{id}/',
        'order_by' => 'name ASC',
        'fields' => array(
            'department_number' => array(
                'label' => $tr('rel_dept_number', 'Číslo'),
                'type' => 'text',
            ),
            'name' => array(
                'label' => $tr('rel_dept_name', 'Název oddělení'),
                'type' => 'text',
            ),
            'training_version' => array(
                'label' => $tr('rel_dept_training_version', 'Verze školení'),
                'type' => 'number',
            ),
            'is_active' => array(
                'label' => $tr('rel_dept_active', 'Aktivní'),
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