<?php
/**
 * Visitors Module Configuration
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visitors
 * @version     2.0.0 - UPDATED: Added check-in/out AJAX actions
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
        'columns' => array('id', 'first_name', 'last_name', 'visit_id', 'current_status', 'first_checkin_at', 'last_checkout_at'),
        'searchable' => array('first_name', 'last_name', 'email'),
        'sortable' => array('id', 'first_name', 'last_name', 'created_at'),
        'filters' => array(
            'participation_status' => array(
                'label' => 'Stav účasti',
                'type' => 'select',
                'options' => array(
                    '' => 'Všechny',
                    'planned' => 'Plánovaný',
                    'confirmed' => 'Potvrzený',
                    'no_show' => 'Nedostavil se',
                ),
            ),
        ),
        'per_page' => 20,
        'enable_detail_modal' => true,
    ),
    
    'cache' => array(
        'enabled' => true,
        'ttl' => 300,
        'invalidate_on' => array('save', 'delete'),
    ),
    
    'custom_ajax_actions' => array(
        // Existing
        'saw_get_visits_for_select' => 'ajax_get_visits_for_select',
        
        // ⭐ NEW: Check-in/out actions
        'saw_checkin' => 'ajax_checkin',
        'saw_checkout' => 'ajax_checkout',
        'saw_add_adhoc_visitor' => 'ajax_add_adhoc_visitor',
    ),
);