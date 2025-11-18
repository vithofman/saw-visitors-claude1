<?php
/**
 * Account Types Module Configuration
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @version     3.0.0 - REFACTORED: New architecture compatibility
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(
    'entity' => 'account_types',
    'table' => 'saw_account_types',
    'singular' => 'Typ účtu',
    'plural' => 'Typy účtů',
    'route' => 'account-types',
    'icon' => '💳',
    
    'has_customer_isolation' => false,
    'has_branch_isolation' => false,
    
    'capabilities' => array(
        'list' => 'manage_options',
        'view' => 'manage_options',
        'create' => 'manage_options',
        'edit' => 'manage_options',
        'delete' => 'manage_options',
    ),
    
    'fields' => array(
        'name' => array(
            'type' => 'text',
            'label' => 'Interní název',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
        ),
        'display_name' => array(
            'type' => 'text',
            'label' => 'Zobrazovaný název',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
        ),
        'price' => array(
            'type' => 'number',
            'label' => 'Cena (Kč/měsíc)',
            'required' => false,
            'default' => 0.00,
            'sanitize' => 'floatval',
        ),
        'color' => array(
            'type' => 'color',
            'label' => 'Barva',
            'required' => false,
            'default' => '#6b7280',
            'sanitize' => 'sanitize_hex_color',
        ),
        'features' => array(
            'type' => 'textarea',
            'label' => 'Seznam funkcí',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
        ),
        'sort_order' => array(
            'type' => 'number',
            'label' => 'Pořadí řazení',
            'required' => false,
            'default' => 0,
            'sanitize' => 'intval',
        ),
        'is_active' => array(
            'type' => 'checkbox',
            'label' => 'Aktivní typ účtu',
            'required' => false,
            'default' => 1,
        ),
    ),
    
    'list_config' => array(
        'per_page' => 20,
        'searchable' => array('name', 'display_name', 'description'),
        'sortable' => array('name', 'display_name', 'price', 'sort_order'),
        'filters' => array(
            'is_active' => true,
        ),
        'enable_detail_modal' => true,
    ),
    
    'cache' => array(
        'enabled' => true,
        'ttl' => 300,
        'invalidate_on' => array('save', 'delete'),
    ),
);