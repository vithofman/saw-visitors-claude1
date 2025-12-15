<?php
/**
 * Visitors Module Configuration
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visitors
 * @version     4.1.0 - FIXED: Added custom_ajax_actions for checkout/checkin
 * 
 * CHANGELOG:
 * 4.1.0 - Added custom_ajax_actions to register AJAX handlers through AJAX Registry
 * 4.0.0 - Multi-language support for singular/plural
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
    
    // ============================================
    // CUSTOM AJAX ACTIONS
    // ✅ ADDED v4.1.0 - Required for AJAX Registry to register these handlers
    // Without this, saw_checkout/saw_checkin would only be registered when
    // visiting /admin/visitors page, NOT during AJAX requests!
    // ============================================
    'custom_ajax_actions' => array(
        'saw_checkout' => 'ajax_checkout',
        'saw_checkin' => 'ajax_checkin',
        'saw_add_adhoc_visitor' => 'ajax_add_adhoc_visitor',
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
            'label' => $tr('form_email', 'E-mail'),
            'required' => false,
            'sanitize' => 'sanitize_email',
        ),
        'phone' => array(
            'type' => 'tel',
            'label' => $tr('form_phone', 'Telefon'),
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'training_status' => array(
            'type' => 'select',
            'label' => $tr('form_training_status', 'Stav školení'),
            'required' => false,
            'options' => array(
                'not_started' => $tr('training_not_started', 'Nespuštěno'),
                'in_progress' => $tr('training_in_progress', 'Probíhá'),
                'completed' => $tr('training_completed', 'Dokončeno'),
                'skipped' => $tr('training_skipped', 'Přeskočeno'),
            ),
            'sanitize' => 'sanitize_text_field',
        ),
        'participation_status' => array(
            'type' => 'select',
            'label' => $tr('form_participation_status', 'Stav účasti'),
            'required' => false,
            'options' => array(
                'planned' => $tr('participation_planned', 'Plánovaný'),
                'confirmed' => $tr('participation_confirmed', 'Potvrzený'),
                'no_show' => $tr('participation_no_show', 'Nedostavil se'),
            ),
            'sanitize' => 'sanitize_text_field',
        ),
    ),
    
    // ============================================
    // TABS CONFIGURATION
    // For filtering by current_status (computed, not in DB)
    // ============================================
    'tabs' => array(
        'enabled' => true,
        'tab_param' => 'current_status',
        'default_tab' => 'all',
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
            'planned' => array(
                'label' => $tr('tab_planned', 'Plánovaní'),
                'icon' => '📅',
                'filter_value' => 'planned',
                'count_query' => true,
            ),
        ),
    ),
    
    // ============================================
    // INFINITE SCROLL CONFIGURATION
    // ============================================
    'infinite_scroll' => array(
        'enabled' => true,
        'per_page' => 50,
        'initial_load' => 100,
        'threshold' => 200,
    ),
    
    // ============================================
    // LIST CONFIGURATION
    // ============================================
    'list_config' => array(
        'per_page' => 20,
        'default_orderby' => 'vis.id',
        'default_order' => 'DESC',
        'searchable_fields' => array('first_name', 'last_name', 'email', 'phone', 'position'),
        'filters' => array(
            'visit_id' => true,
            'training_status' => true,
            'participation_status' => true,
            'current_status' => true,
        ),
    ),
    
    // ============================================
    // DETAIL SIDEBAR CONFIGURATION
    // ============================================
    'detail_sidebar' => array(
        'enabled' => true,
        'width' => '500px',
        'default_tab' => 'overview',
        'tabs' => array(
            'overview' => array(
                'label' => $tr('detail_tab_overview', 'Přehled'),
                'icon' => '📋',
            ),
            'activity' => array(
                'label' => $tr('detail_tab_activity', 'Aktivita'),
                'icon' => '📊',
            ),
        ),
    ),
    
    // ============================================
    // CACHE CONFIGURATION
    // ============================================
    'cache' => array(
        'enabled' => true,
        'ttl' => 300,
    ),
    
    // ============================================
    // ACTIONS (for list rows)
    // ============================================
    'actions' => array('view', 'edit', 'delete'),
);