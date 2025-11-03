<?php
if (!defined('ABSPATH')) {
    exit;
}

return [
    'entity' => 'users',
    'table' => 'saw_users',
    'singular' => 'Uživatel',
    'plural' => 'Uživatelé',
    'route' => 'admin/users',
    'icon' => '👤',
    
    'allowed_roles' => ['super_admin', 'admin'],
    
    'filter_by_customer' => true,
    'filter_by_branch' => false,
    
    // ✅ OPRAVENO: 'read' místo 'saw_manage_users'
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
        'columns' => ['name', 'email', 'role', 'branch', 'is_active'],
        'searchable' => ['first_name', 'last_name', 'email'],
        'sortable' => ['role', 'created_at'],
        'filters' => [
            'is_active' => true,
            'role' => true,
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