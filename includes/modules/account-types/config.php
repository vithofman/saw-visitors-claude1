<?php
if (!defined('ABSPATH')) {
    exit;
}

return [
    'entity' => 'account-types',
    'table' => 'saw_account_types',
    'singular' => 'Account Type',
    'plural' => 'Account Types',
    'route' => 'admin/settings/account-types',
    'icon' => '💳',
    
    'capabilities' => [
        'list' => 'manage_options',
        'view' => 'manage_options',
        'create' => 'manage_options',
        'edit' => 'manage_options',
        'delete' => 'manage_options',
    ],
    
    'fields' => [
        'name' => [
            'type' => 'text',
            'label' => 'Name',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
        ],
        'display_name' => [
            'type' => 'text',
            'label' => 'Display Name',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
        ],
    ],
    
    'list_config' => [
        'columns' => ['name', 'display_name'],
        'searchable' => ['name', 'display_name'],
        'sortable' => ['name', 'display_name'],
        'filters' => [],
        'per_page' => 20,
        'enable_detail_modal' => true,  // ✅ ZMĚNĚNO NA TRUE!
    ],
    
    'cache' => [
        'enabled' => true,
        'ttl' => 300,
        'invalidate_on' => ['save', 'delete'],
    ],
];