<?php
/**
 * Users Module Configuration
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Users
 * @version     6.2.0 - FIXED: Added custom_ajax_actions for departments AJAX
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
    ? saw_get_translations($lang, 'admin', 'users') 
    : array();

$tr = function($key, $fallback = null) use ($t) {
    return isset($t[$key]) ? $t[$key] : ($fallback !== null ? $fallback : $key);
};

// ============================================
// CONFIGURATION
// ============================================
return array(
    // Entity definition
    'entity' => 'users',
    'table' => 'saw_users',
    'singular' => $tr('entity_singular', 'Uživatel'),
    'plural' => $tr('entity_plural', 'Uživatelé'),
    'route' => 'users',
    'icon' => '👤',
    'has_customer_isolation' => true,
    'edit_url' => 'users/{id}/edit',

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
            'required' => false,
            'hidden' => true,
            'sanitize' => 'absint',
        ),
        'branch_id' => array(
            'type' => 'select',
            'label' => $tr('field_branch', 'Pobočka'),
            'required' => false,
            'sanitize' => 'absint',
            'help' => $tr('field_branch_help', 'Pobočka uživatele (pro Super Manager, Manager, Terminál)'),
        ),
        'role' => array(
            'type' => 'select',
            'label' => $tr('field_role', 'Role'),
            'required' => true,
            'sanitize' => 'sanitize_text_field',
            'options' => array(
                'admin' => $tr('role_admin', 'Admin'),
                'super_manager' => $tr('role_super_manager', 'Super Manager'),
                'manager' => $tr('role_manager', 'Manager'),
                'terminal' => $tr('role_terminal', 'Terminál'),
            ),
            'help' => $tr('field_role_help', 'Role určuje oprávnění uživatele'),
        ),
        'email' => array(
            'type' => 'email',
            'label' => $tr('field_email', 'Email'),
            'required' => true,
            'sanitize' => 'sanitize_email',
            'help' => $tr('field_email_help', 'Email pro přihlášení do systému'),
        ),
        'first_name' => array(
            'type' => 'text',
            'label' => $tr('field_first_name', 'Jméno'),
            'required' => true,
            'sanitize' => 'sanitize_text_field',
        ),
        'last_name' => array(
            'type' => 'text',
            'label' => $tr('field_last_name', 'Příjmení'),
            'required' => true,
            'sanitize' => 'sanitize_text_field',
        ),
        'position' => array(
            'type' => 'text',
            'label' => $tr('field_position', 'Funkce'),
            'required' => false,
            'sanitize' => 'sanitize_text_field',
            'help' => $tr('field_position_help', 'Pracovní pozice'),
        ),
        'pin' => array(
            'type' => 'password',
            'label' => $tr('field_pin', 'PIN'),
            'required' => false,
            'sanitize' => 'sanitize_text_field',
            'help' => $tr('field_pin_help', '4místný PIN pro terminálové uživatele'),
        ),
        'is_active' => array(
            'type' => 'boolean',
            'label' => $tr('field_is_active', 'Aktivní'),
            'required' => false,
            'default' => 1,
            'sanitize' => 'absint',
            'help' => $tr('field_is_active_help', 'Pouze aktivní uživatelé se mohou přihlásit'),
        ),
    ),

    // List configuration
    // ✅ CRITICAL: filters here are used by base controller to collect GET params!
    'list_config' => array(
        'columns' => array('name', 'email', 'role', 'branch', 'is_active', 'last_login'),
        'searchable' => array('first_name', 'last_name', 'position'),
        'sortable' => array('last_name', 'first_name', 'role', 'created_at', 'last_login'),
        'per_page' => 20,
        'enable_detail_modal' => true,
        'default_sort' => array(
            'orderby' => 'last_name',
            'order' => 'ASC',
        ),
        // ✅ FILTERS - base controller reads these to collect from $_GET
        'filters' => array(
            'branch_id' => true,
            'department_id' => true,
            'is_active' => true,
        ),
    ),

    // Tabs configuration - BY ROLE (not status)
    'tabs' => array(
        'enabled' => true,
        'tab_param' => 'tab',
        'tabs' => array(
            'all' => array(
                'label' => $tr('tab_all', 'Všichni'),
                'icon' => '📋',
                'filter_value' => null,
                'count_query' => true,
            ),
            'admin' => array(
                'label' => $tr('tab_admin', 'Admini'),
                'icon' => '👔',
                'filter_value' => 'admin',
                'count_query' => true,
            ),
            'super_manager' => array(
                'label' => $tr('tab_super_manager', 'Super Manageři'),
                'icon' => '🎯',
                'filter_value' => 'super_manager',
                'count_query' => true,
            ),
            'manager' => array(
                'label' => $tr('tab_manager', 'Manageři'),
                'icon' => '👥',
                'filter_value' => 'manager',
                'count_query' => true,
            ),
            'terminal' => array(
                'label' => $tr('tab_terminal', 'Terminály'),
                'icon' => '🖥️',
                'filter_value' => 'terminal',
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

    // ============================================
    // CUSTOM AJAX ACTIONS
    // ============================================
    // These are registered globally by AJAX Registry at plugin init
    // Required because controller is not instantiated for AJAX requests
    'custom_ajax_actions' => array(
        'saw_get_departments_by_branch' => 'ajax_get_departments_by_branch',
    ),

    // Cache
    'cache' => array(
        'enabled' => true,
        'ttl' => 300,
        'invalidate_on' => array('save', 'delete'),
    ),
);