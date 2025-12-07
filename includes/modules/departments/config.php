<?php
/**
 * Departments Module Configuration
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Departments
 * @version     6.0.0 - REFACTORED: Added translations, tabs, infinite scroll
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// TRANSLATIONS
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
// CONFIGURATION
// ============================================
return array(
    // Entity definition
    'entity' => 'departments',
    'table' => 'saw_departments',
    'singular' => $tr('singular', 'Oddělení'),
    'plural' => $tr('plural', 'Oddělení'),
    'route' => 'departments',
    'icon' => '🏭',
    'has_customer_isolation' => true,
    'edit_url' => 'departments/{id}/edit',

    // Capabilities
    'capabilities' => array(
        'list' => 'manage_options',
        'view' => 'manage_options',
        'create' => 'manage_options',
        'edit' => 'manage_options',
        'delete' => 'manage_options',
    ),

    // Field definitions
    'fields' => array(
        'customer_id' => array(
            'type' => 'number',
            'label' => $tr('field_customer_id', 'Zákazník ID'),
            'required' => true,
            'hidden' => true,
            'sanitize' => 'absint',
        ),
        'branch_id' => array(
            'type' => 'select',
            'label' => $tr('field_branch', 'Pobočka'),
            'required' => true,
            'hidden' => true,
            'sanitize' => 'absint',
            'help' => $tr('field_branch_help', 'Pobočka ke které oddělení patří'),
        ),
        'department_number' => array(
            'type' => 'text',
            'label' => $tr('field_department_number', 'Číslo oddělení'),
            'required' => false,
            'sanitize' => 'sanitize_text_field',
            'help' => $tr('field_department_number_help', 'Interní číslo oddělení (volitelné)'),
        ),
        'name' => array(
            'type' => 'text',
            'label' => $tr('field_name', 'Název oddělení'),
            'required' => true,
            'sanitize' => 'sanitize_text_field',
            'help' => $tr('field_name_help', 'Název oddělení'),
        ),
        'description' => array(
            'type' => 'textarea',
            'label' => $tr('field_description', 'Popis'),
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
            'help' => $tr('field_description_help', 'Volitelný popis oddělení'),
        ),
        'is_active' => array(
            'type' => 'boolean',
            'label' => $tr('field_is_active', 'Aktivní'),
            'required' => false,
            'default' => 1,
            'sanitize' => 'absint',
            'help' => $tr('field_is_active_help', 'Pouze aktivní oddělení jsou dostupná pro výběr'),
        ),
    ),

    // List configuration
    'list_config' => array(
        'columns' => array('department_number', 'name', 'is_active'),
        'searchable' => array('name', 'department_number', 'description'),
        'sortable' => array('name', 'department_number', 'created_at'),
        'per_page' => 20,
        'enable_detail_modal' => true,
        'default_sort' => array(
            'orderby' => 'name',
            'order' => 'ASC',
        ),
    ),

    // Tabs configuration
    'tabs' => array(
        'enabled' => true,
        'tab_param' => 'tab',
        'tabs' => array(
            'all' => array(
                'label' => $tr('tab_all', 'Všechna'),
                'icon' => '📋',
                'filter_value' => null,
                'count_query' => true,
            ),
            'active' => array(
                'label' => $tr('tab_active', 'Aktivní'),
                'icon' => '✅',
                'filter_value' => 'active',
                'count_query' => true,
            ),
            'inactive' => array(
                'label' => $tr('tab_inactive', 'Neaktivní'),
                'icon' => '⏸️',
                'filter_value' => 'inactive',
                'count_query' => true,
            ),
        ),
        'default_tab' => 'all',
    ),

    // Infinite scroll
    'infinite_scroll' => array(
        'enabled' => true,
        'initial_load' => 100,
        'per_page' => 50,
        'threshold' => 0.6,
    ),

    // Cache
    'cache' => array(
        'enabled' => true,
        'ttl' => 300,
        'invalidate_on' => array('save', 'delete'),
    ),
);