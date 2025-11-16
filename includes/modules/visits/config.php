<?php
/**
 * Visits Module Configuration
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
 * @version     2.0.0 - REFACTORED: Multiple day schedules support
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(
    'entity' => 'visits',
    'table' => 'saw_visits',
    'singular' => 'Návštěva',
    'plural' => 'Návštěvy',
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
            'required' => true,
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
    ),
    
    'list_config' => array(
        'columns' => array('id', 'company_id', 'schedule_dates', 'status'),
        'searchable' => array(),
        'sortable' => array('id'),
        'filters' => array(),
        'per_page' => 20,
        'enable_detail_modal' => true,
    ),
    
    'cache' => array(
        'enabled' => true,
        'ttl' => 300,
        'invalidate_on' => array('save', 'delete'),
    ),
    
    'custom_ajax_actions' => array(
        'saw_get_hosts_by_branch' => 'ajax_get_hosts_by_branch',
    ),
);