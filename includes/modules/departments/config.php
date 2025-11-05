<?php
/**
 * Departments Module Config
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

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
    
    'has_customer_isolation' => true,
    
    'capabilities' => [
        'list' => 'saw_view_departments',
        'view' => 'saw_view_departments',
        'create' => 'saw_manage_departments',
        'edit' => 'saw_manage_departments',
        'delete' => 'saw_manage_departments',
    ],
    
    'fields' => [
        'customer_id' => [
            'type' => 'hidden',
            'required' => true,
        ],
        'branch_id' => [
            'type' => 'select',
            'label' => 'Pobočka',
            'required' => true,
            'help' => 'Pobočka ke které oddělení patří',
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
            'help' => 'Název oddělení',
        ],
        'description' => [
            'type' => 'textarea',
            'label' => 'Popis',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
            'help' => 'Volitelný popis oddělení',
        ],
        'training_version' => [
            'type' => 'number',
            'label' => 'Verze školení',
            'required' => false,
            'default' => 1,
            'sanitize' => 'intval',
            'help' => 'Aktuální verze školení pro oddělení',
        ],
        'is_active' => [
            'type' => 'checkbox',
            'label' => 'Aktivní oddělení',
            'required' => false,
            'default' => 1,
            'help' => 'Pouze aktivní oddělení jsou dostupná pro výběr',
        ],
    ],
    
    'list_config' => [
        'columns' => ['department_number', 'name', 'branch_id', 'training_version', 'is_active'],
        'searchable' => ['name', 'department_number', 'description'],
        'sortable' => ['name', 'department_number', 'training_version', 'created_at'],
        'filters' => [
            'is_active' => true,
            'branch_id' => true,
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
