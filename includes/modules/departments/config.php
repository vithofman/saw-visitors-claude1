<?php
if (!defined('ABSPATH')) {
    exit;
}

return [
    'entity' => 'departments',
    'table' => 'saw_departments',
    'singular' => 'Oddělení',
    'plural' => 'Oddělení',
    'route' => 'admin/departments',
    'icon' => '🏢',
    'filter_by_customer' => true,
    
    'capabilities' => [
        'list' => 'manage_options',
        'view' => 'manage_options',
        'create' => 'manage_options',
        'edit' => 'manage_options',
        'delete' => 'manage_options',
    ],
    
    'fields' => [
        'branch_id' => [
            'type' => 'number',
            'label' => 'Pobočka',
            'required' => true,
            'sanitize' => 'absint',
            'help' => 'Pod kterou pobočku oddělení spadá',
        ],
        
        'department_number' => [
            'type' => 'text',
            'label' => 'Číslo oddělení',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
            'help' => 'Interní číslo oddělení (volitelné)',
        ],
        
        'name' => [
            'type' => 'text',
            'label' => 'Název oddělení',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
            'help' => 'Název oddělení (např. "IT", "Marketing")',
        ],
        
        'description' => [
            'type' => 'textarea',
            'label' => 'Popis',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
            'help' => 'Popis oddělení a jeho náplně práce',
            'rows' => 5,
        ],
        
        'training_version' => [
            'type' => 'number',
            'label' => 'Verze školení',
            'required' => true,
            'default' => 1,
            'sanitize' => 'absint',
            'min' => 1,
            'help' => 'Verze aktuálního bezpečnostního školení',
        ],
        
        'is_active' => [
            'type' => 'checkbox',
            'label' => 'Aktivní',
            'required' => false,
            'default' => 1,
            'sanitize' => 'absint',
            'help' => 'Pouze aktivní oddělení jsou viditelná',
        ],
    ],
    
    'list_config' => [
        'columns' => ['name', 'description', 'training_version', 'is_active'],
        'searchable' => ['name', 'description'],
        'sortable' => ['name', 'training_version', 'created_at'],
        'filters' => [
            'is_active' => true,
            'customer_id' => true,
        ],
        'per_page' => 20,
        'enable_detail_modal' => true,
    ],
    
    'cache' => [
        'enabled' => true,
        'ttl' => 1800,
        'invalidate_on' => ['save', 'delete'],
    ],
];