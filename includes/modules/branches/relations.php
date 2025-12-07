<?php
/**
 * Branches Relations Configuration
 * 
 * Defines related data that should be displayed in branch detail view.
 * Supports translations via translation keys.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches
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
    ? saw_get_translations($lang, 'admin', 'branches') 
    : array();

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// RELATIONS CONFIGURATION
// ============================================
return array(
    'departments' => array(
        'label' => $tr('rel_departments', 'Oddělení'),
        'icon' => '🏭',
        'entity' => 'departments',
        'foreign_key' => 'branch_id',
        'display_fields' => array('department_number', 'name'),
        'route' => 'admin/departments/{id}/',
        'order_by' => 'name ASC',
        'fields' => array(
            'department_number' => array(
                'label' => $tr('rel_dept_number', 'Číslo'),
                'type' => 'text',
            ),
            'name' => array(
                'label' => $tr('rel_dept_name', 'Název'),
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
    
    'visits' => array(
        'label' => $tr('rel_visits', 'Návštěvy'),
        'icon' => '📋',
        'entity' => 'visits',
        'foreign_key' => 'branch_id',
        'display_fields' => array('visit_date', 'status'),
        'route' => 'admin/visits/{id}/',
        'order_by' => 'visit_date DESC',
        'limit' => 5,
        'fields' => array(
            'visit_date' => array(
                'label' => $tr('rel_visit_date', 'Datum'),
                'type' => 'date',
            ),
            'status' => array(
                'label' => $tr('rel_visit_status', 'Status'),
                'type' => 'status',
            ),
        ),
    ),
);