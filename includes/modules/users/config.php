<?php
/**
 * Users Module Configuration
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Users
 * @version     5.2.0 - ADDED: position field (funkce)
 */

if (!defined('ABSPATH')) {
    exit;
}

return [
    'entity' => 'users',
    'table' => 'saw_users',
    'singular' => 'Uživatel',
    'plural' => 'Uživatelé',
    'route' => 'users',
    'icon' => '👤',
    'has_customer_isolation' => true,
    'edit_url' => 'admin/users/{id}/edit',
    
    'allowed_roles' => ['super_admin', 'admin'],
    
    'capabilities' => [
        'list' => 'read',
        'view' => 'read',
        'create' => 'read',
        'edit' => 'read',
        'delete' => 'read',
    ],
    
    'fields' => [
        'role' => [
            'type' => 'select',
            'label' => 'Role',
            'required' => true,
            'options' => [
                'admin' => 'Admin (všechny pobočky)',
                'super_manager' => 'Super Manager (jedna pobočka)',
                'manager' => 'Manager (oddělení)',
                'terminal' => 'Terminál'
            ]
        ],
        'email' => [
            'type' => 'email',
            'label' => 'Email',
            'required' => true,
            'sanitize' => 'sanitize_email',
        ],
        'first_name' => [
            'type' => 'text',
            'label' => 'Jméno',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
        ],
        'last_name' => [
            'type' => 'text',
            'label' => 'Příjmení',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
        ],
        'position' => [
            'type' => 'text',
            'label' => 'Funkce',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
            'placeholder' => 'např. Vedoucí výroby, BOZP technik',
        ],
        'branch_id' => [
            'type' => 'select',
            'label' => 'Pobočka',
            'required' => false,
        ],
        'department_ids' => [
            'type' => 'checkbox',
            'label' => 'Oddělení',
            'required' => false,
        ],
        'pin' => [
            'type' => 'text',
            'label' => 'PIN (4 čísla)',
            'required' => false,
            'maxlength' => 4,
        ],
        'is_active' => [
            'type' => 'checkbox',
            'label' => 'Aktivní',
            'required' => false,
            'default' => 1,
        ],
    ],
    
    'list_config' => [
        'columns' => ['name', 'email', 'position', 'role', 'branch', 'is_active'],
        'searchable' => ['first_name', 'last_name', 'email', 'position'],
        'sortable' => ['role', 'position', 'created_at'],
        'filters' => [
            'is_active' => true,
            'role' => true,
        ],
        'per_page' => 20,
        'enable_detail_modal' => true,
    ],
    
    'custom_ajax_actions' => [
        'saw_get_departments_by_branch' => 'ajax_get_departments_by_branch',
    ],
    
    'cache' => [
        'enabled' => true,
        'ttl' => 1800,
        'invalidate_on' => ['save', 'delete'],
    ],
];