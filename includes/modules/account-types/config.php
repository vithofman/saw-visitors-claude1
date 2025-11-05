<?php
if (!defined('ABSPATH')) {
    exit;
}

return [
    'entity' => 'account_types',
    'table' => 'saw_account_types',
    'singular' => 'Account Type',
    'plural' => 'Account Types',
    'route' => 'admin/settings/account-types',
    'icon' => '💳',
    
    'has_customer_isolation' => false,
    
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
            'label' => 'Interní název',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
        ],
        'display_name' => [
            'type' => 'text',
            'label' => 'Zobrazovaný název',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
        ],
        'description' => [
            'type' => 'textarea',
            'label' => 'Popis',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
        ],
        'price' => [
            'type' => 'number',
            'label' => 'Cena (Kč/měsíc)',
            'required' => false,
            'default' => 0,
            'sanitize' => 'floatval',
        ],
        'color' => [
            'type' => 'color',
            'label' => 'Barva',
            'required' => false,
            'default' => '#6b7280',
            'sanitize' => 'sanitize_hex_color',
        ],
        'features' => [
            'type' => 'textarea',
            'label' => 'Seznam funkcí',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
        ],
        'sort_order' => [
            'type' => 'number',
            'label' => 'Pořadí řazení',
            'required' => false,
            'default' => 0,
            'sanitize' => 'intval',
        ],
        'is_active' => [
            'type' => 'checkbox',
            'label' => 'Aktivní',
            'required' => false,
            'default' => 1,
        ],
    ],
    
    'list_config' => [
        'columns' => ['color', 'display_name', 'name', 'price', 'is_active'],
        'searchable' => ['name', 'display_name', 'description'],
        'sortable' => ['name', 'display_name', 'price', 'sort_order'],
        'filters' => [
            'is_active' => true,
        ],
        'per_page' => 20,
        'enable_detail_modal' => true,
    ],
    
    'cache' => [
        'enabled' => true,
        'ttl' => 300,
        'invalidate_on' => ['save', 'delete'],
    ],
];