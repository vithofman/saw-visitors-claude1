<?php
/**
 * Visitors Module Configuration
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visitors
 * @version     4.0.0 - Multi-language support for singular/plural
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
    ? saw_get_translations($lang, 'admin', 'visitors') 
    : [];

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// MODULE CONFIGURATION
// ============================================
return array(
    'entity' => 'visitors',
    'table' => 'saw_visitors',
    'singular' => $tr('config_singular', 'Návštěvník'),
    'plural' => $tr('config_plural', 'Návštěvníci'),
    'route' => 'visitors',
    'icon' => '👤',
    'has_customer_isolation' => true,
    'edit_url' => 'visitors/{id}/edit',
    
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
            'label' => 'Customer ID',
            'required' => true,
            'hidden' => true,
            'sanitize' => 'absint',
        ),
        'branch_id' => array(
            'type' => 'number',
            'label' => 'Branch ID',
            'required' => true,
            'hidden' => true,
            'sanitize' => 'absint',
        ),
        'visit_id' => array(
            'type' => 'number',
            'label' => 'Visit ID',
            'required' => true,
            'sanitize' => 'absint',
        ),
        'first_name' => array(
            'type' => 'text',
            'label' => $tr('form_first_name', 'Jméno'),
            'required' => true,
            'sanitize' => 'sanitize_text_field',
        ),
        'last_name' => array(
            'type' => 'text',
            'label' => $tr('form_last_name', 'Příjmení'),
            'required' => true,
            'sanitize' => 'sanitize_text_field',
        ),
        'position' => array(
            'type' => 'text',
            'label' => $tr('form_position', 'Pozice'),
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'email' => array(
            'type' => 'email',
            'label' => $tr('form_email', 'Email'),
            'required' => false,
            'sanitize' => 'sanitize_email',
        ),
        'phone' => array(
            'type' => 'text',
            'label' => $tr('form_phone', 'Telefon'),
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'participation_status' => array(
            'type' => 'select',
            'label' => $tr('form_participation_status', 'Stav účasti'),
            'required' => true,
            'sanitize' => 'sanitize_text_field',
            'default' => 'planned',
            'options' => array(
                'planned' => $tr('status_planned_short', 'Plánovaný'),
                'confirmed' => $tr('status_confirmed_short', 'Potvrzený'),
                'no_show' => $tr('status_no_show_short', 'Nedostavil se'),
            ),
        ),
        'training_skipped' => array(
            'type' => 'checkbox',
            'label' => $tr('form_training_skipped', 'Školení absolvováno do 1 roku'),
            'required' => false,
            'sanitize' => 'absint',
            'default' => 0,
        ),
        'current_status' => array(
            'type' => 'select',
            'label' => $tr('col_current_status', 'Aktuální stav'),
            'required' => false,
            'hidden' => true,
            'sanitize' => 'sanitize_text_field',
            'default' => 'planned',
            'options' => array(
                'planned' => $tr('status_planned_short', 'Plánovaný'),
                'confirmed' => $tr('status_confirmed_short', 'Potvrzený'),
                'present' => $tr('status_present_short', 'Přítomen'),
                'checked_out' => $tr('status_checked_out_short', 'Odhlášen'),
                'no_show' => $tr('status_no_show_short', 'Nedostavil se'),
            ),
        ),
        'training_status' => array(
            'type' => 'select',
            'label' => $tr('col_training', 'Stav školení'),
            'required' => false,
            'hidden' => true,
            'sanitize' => 'sanitize_text_field',
            'default' => 'pending',
            'options' => array(
                'pending' => $tr('training_pending', 'Čeká na check-in'),
                'not_available' => $tr('training_not_available', 'Nebylo k dispozici'),
                'skipped' => $tr('training_skipped_short', 'Přeskočeno (1 rok)'),
                'in_progress' => $tr('training_in_progress_short', 'Probíhá'),
                'completed' => $tr('training_completed_short', 'Dokončeno'),
            ),
        ),
    ),
    
    'list_config' => array(
        'columns' => array(
            'id',
            'first_name',
            'last_name',
            'visit_id',
            'current_status',
            'training_status',
            'first_checkin_at',
            'last_checkout_at'
        ),
        'searchable' => array('first_name', 'last_name', 'email'),
        'sortable' => array(
            'id', 
            'first_name', 
            'last_name', 
            'created_at',
            'current_status',
            'training_status',
        ),
        'filters' => array(
            'training_status' => true,
        ),
        'per_page' => 20,
        'enable_detail_modal' => true,
    ),
    
    'tabs' => array(
        'enabled' => true,
        'tab_param' => 'current_status',
        'tabs' => array(
            'all' => array(
                'label' => $tr('tab_all', 'Všichni'),
                'icon' => '📋',
                'filter_value' => null,
                'count_query' => true,
            ),
            'present' => array(
                'label' => $tr('tab_present', 'Přítomní'),
                'icon' => '✅',
                'filter_value' => 'present',
                'count_query' => true,
            ),
            'checked_out' => array(
                'label' => $tr('tab_checked_out', 'Odhlášení'),
                'icon' => '🚪',
                'filter_value' => 'checked_out',
                'count_query' => true,
            ),
            'confirmed' => array(
                'label' => $tr('tab_confirmed', 'Potvrzení'),
                'icon' => '⏳',
                'filter_value' => 'confirmed',
                'count_query' => true,
            ),
            'planned' => array(
                'label' => $tr('tab_planned', 'Plánovaní'),
                'icon' => '📅',
                'filter_value' => 'planned',
                'count_query' => true,
            ),
            'no_show' => array(
                'label' => $tr('tab_no_show', 'Nedostavili se'),
                'icon' => '❌',
                'filter_value' => 'no_show',
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
);