<?php
/**
 * Visitors Module Configuration
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visitors
 * @version     3.0.0 - FIXED: Removed custom_ajax_actions (now handled in controller)
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(
    'entity' => 'visitors',
    'table' => 'saw_visitors',
    'singular' => 'Návštěvník',
    'plural' => 'Návštěvníci',
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
        'visit_id' => array(
            'type' => 'number',
            'label' => 'Návštěva ID',
            'required' => true,
            'sanitize' => 'absint',
        ),
        'first_name' => array(
            'type' => 'text',
            'label' => 'Jméno',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
        ),
        'last_name' => array(
            'type' => 'text',
            'label' => 'Příjmení',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
        ),
        'position' => array(
            'type' => 'text',
            'label' => 'Pozice',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'email' => array(
            'type' => 'email',
            'label' => 'Email',
            'required' => false,
            'sanitize' => 'sanitize_email',
        ),
        'phone' => array(
            'type' => 'text',
            'label' => 'Telefon',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'participation_status' => array(
            'type' => 'select',
            'label' => 'Stav účasti',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
            'default' => 'planned',
            'options' => array(
                'planned' => 'Plánovaný',
                'confirmed' => 'Potvrzený',
                'no_show' => 'Nedostavil se',
            ),
        ),
        'training_skipped' => array(
            'type' => 'checkbox',
            'label' => 'Školení absolvováno do 1 roku',
            'required' => false,
            'sanitize' => 'absint',
            'default' => 0,
        ),
    ),
    
    'list_config' => array(
        'columns' => array(
            'id',
            'first_name',
            'last_name',
            'visit_id',
            'current_status',
            'first_checkin_at',
            'last_checkout_at'
        ),
        'searchable' => array('first_name', 'last_name', 'email'),
        'sortable' => array('id', 'first_name', 'last_name', 'created_at'),
        'filters' => array(
            'training_status' => true, // Filter by training status instead of training_required
        ),
        'per_page' => 20,
        'enable_detail_modal' => true,
    ),
    
    // TABS configuration - for horizontal tabs navigation
    'tabs' => array(
        'enabled' => true,
        'tab_param' => 'current_status', // GET parameter (?current_status=present)
        'tabs' => array(
            'all' => array(
                'label' => 'Všechny',
                'icon' => '📋',
                'filter_value' => null, // null = no filter (all records)
                'count_query' => true,
            ),
            'present' => array(
                'label' => 'Přítomen',
                'icon' => '✅',
                'filter_value' => 'present',
                'count_query' => true,
            ),
            'checked_out' => array(
                'label' => 'Odhlášen',
                'icon' => '🚪',
                'filter_value' => 'checked_out',
                'count_query' => true,
            ),
            'confirmed' => array(
                'label' => 'Potvrzený',
                'icon' => '⏳',
                'filter_value' => 'confirmed',
                'count_query' => true,
            ),
            'planned' => array(
                'label' => 'Plánovaný',
                'icon' => '📅',
                'filter_value' => 'planned',
                'count_query' => true,
            ),
            'no_show' => array(
                'label' => 'Nedostavil se',
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