<?php
/**
 * Visits Module Configuration
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
 * @version     4.2.0 - TRANSLATIONS SUPPORT
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load translations for config labels
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}
$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'visits') 
    : [];

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

return array(
    'entity' => 'visits',
    'table' => 'saw_visits',
    'singular' => $tr('config_singular', 'Návštěva'),
    'plural' => $tr('config_plural', 'Návštěvy'),
    'route' => 'visits',
    'icon' => '📅',
    'has_customer_isolation' => true,
    'edit_url' => 'visits/{id}/edit',
    
    'capabilities' => array(
        'list' => 'manage_options',
        'view' => 'manage_options',
        'create' => 'manage_options',
        'edit' => 'manage_options',
        'delete' => 'manage_options',
    ),
    
    'fields' => array(
        'customer_id' => array(
            'type' => 'number',
            'label' => 'Zákazník ID',
            'required' => true,
            'hidden' => true,
            'sanitize' => 'absint',
        ),
        'branch_id' => array(
            'type' => 'select',
            'label' => 'Pobočka',
            'required' => true,
            'sanitize' => 'absint',
            'hidden' => true,
        ),
        'company_id' => array(
            'type' => 'select',
            'label' => 'Firma',
            'required' => false, // Not required for physical persons
            'sanitize' => 'absint',
        ),
        'visit_type' => array(
            'type' => 'select',
            'label' => 'Typ návštěvy',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
            'default' => 'planned',
        ),
        'status' => array(
            'type' => 'select',
            'label' => 'Stav',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
            'default' => 'pending',
            'options' => array(
                'draft' => 'Koncept',
                'pending' => 'Čekající',
                'confirmed' => 'Potvrzená',
                'in_progress' => 'Probíhající',
                'completed' => 'Dokončená',
                'cancelled' => 'Zrušená',
            ),
        ),
        'started_at' => array(
            'type' => 'datetime',
            'label' => 'Zahájeno',
            'required' => false,
            'hidden' => true,
            'sanitize' => 'sanitize_text_field',
        ),
        'completed_at' => array(
            'type' => 'datetime',
            'label' => 'Dokončeno',
            'required' => false,
            'hidden' => true,
            'sanitize' => 'sanitize_text_field',
        ),
        'invitation_email' => array(
            'type' => 'email',
            'label' => 'Email pro pozvánku',
            'required' => false,
            'sanitize' => 'sanitize_email',
        ),
        'purpose' => array(
            'type' => 'textarea',
            'label' => 'Účel návštěvy',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
        ),
        'notes' => array(
            'type' => 'textarea',
            'label' => 'Poznámky',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
        ),
        'pin_expires_at' => array(
            'type' => 'datetime',
            'label' => 'Platnost PIN',
            'required' => false,
            'hidden' => false,
            'readonly' => true,
            'sanitize' => 'sanitize_text_field',
            'help' => 'Do kdy je PIN platný (automaticky se prodlužuje při použití)',
        ),
    ),
    
    'list_config' => array(
        'columns' => array('id', 'company_id', 'schedule_dates', 'status', 'started_at'),
        'searchable' => array(),
        'sortable' => array('id', 'first_schedule_date', 'started_at'),
        'filters' => array(
            'status' => true, // Enable status filter
            'visit_type' => true, // Enable visit_type filter
        ),
        'per_page' => 20,
        'enable_detail_modal' => true,
    ),
    
    // TABS configuration - for horizontal tabs navigation
    'tabs' => array(
        'enabled' => true,
        'tab_param' => 'status', // GET parameter (?status=confirmed)
        'tabs' => array(
            'all' => array(
                'label' => 'Všechny',
                'icon' => '📋',
                'filter_value' => null, // null = no filter (all records)
                'count_query' => true,
            ),
            'draft' => array(
                'label' => 'Koncept',
                'icon' => '📝',
                'filter_value' => 'draft',
                'count_query' => true,
            ),
            'pending' => array(
                'label' => 'Čekající',
                'icon' => '⏳',
                'filter_value' => 'pending',
                'count_query' => true,
            ),
            'confirmed' => array(
                'label' => 'Potvrzená',
                'icon' => '✅',
                'filter_value' => 'confirmed',
                'count_query' => true,
            ),
            'in_progress' => array(
                'label' => 'Probíhající',
                'icon' => '🔄',
                'filter_value' => 'in_progress',
                'count_query' => true,
            ),
            'completed' => array(
                'label' => 'Dokončená',
                'icon' => '✔️',
                'filter_value' => 'completed',
                'count_query' => true,
            ),
            'cancelled' => array(
                'label' => 'Zrušená',
                'icon' => '❌',
                'filter_value' => 'cancelled',
                'count_query' => true,
            ),
        ),
        'default_tab' => 'all',
    ),
    
    'cache' => array(
        'enabled' => true,
        'ttl' => 300,
        'invalidate_on' => array('save', 'delete'),
    ),
    
    'custom_ajax_actions' => array(
        'saw_get_hosts_by_branch' => 'ajax_get_hosts_by_branch',
        'saw_create_walkin' => 'ajax_create_walkin',
        'saw_send_invitation' => 'ajax_send_invitation',
        'saw_extend_pin' => 'ajax_extend_pin',
        'saw_generate_pin' => 'ajax_generate_pin',
    ),
    
    // ============================================
    // VIRTUAL COLUMNS
    // Dynamically computed values not stored in database
    // ============================================
    'virtual_columns' => array(
    'has_risks' => array(
        'type' => 'computed',  // ← Změnil jsem na 'computed' (bez $wpdb)
        'callback' => function($item) {
            // ⚠️ TEST: Vždy vrať 'yes' abychom zjistili jestli se callback volá
            error_log("[VIRTUAL COLUMN TEST] Visit ID: " . ($item['id'] ?? 'NULL') . " - Returning: yes");
            return 'yes';
        },
    ),
),
);