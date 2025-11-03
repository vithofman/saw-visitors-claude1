<?php
/**
 * Permissions Module Config
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 * @since 4.10.0
 */

if (!defined('ABSPATH')) {
    exit;
}

return [
    'entity' => 'permissions',
    'table' => 'saw_permissions',
    'singular' => 'Oprávnění',
    'plural' => 'Správa oprávnění',
    'route' => 'admin/permissions',
    'icon' => '🔐',
    
    'allowed_roles' => ['super_admin'],
    
    'filter_by_customer' => false,
    'filter_by_branch' => false,
    
    'capabilities' => [
        'list' => 'manage_options',
        'view' => 'manage_options',
        'create' => 'manage_options',
        'edit' => 'manage_options',
        'delete' => 'manage_options',
    ],
    
    'fields' => [
        'role' => [
            'type' => 'select',
            'label' => 'Role',
            'required' => true,
            'options' => [
                'admin' => 'Admin',
                'super_manager' => 'Super Manager',
                'manager' => 'Manager',
                'terminal' => 'Terminál',
            ],
        ],
        'module' => [
            'type' => 'text',
            'label' => 'Modul',
            'required' => true,
        ],
        'action' => [
            'type' => 'text',
            'label' => 'Akce',
            'required' => true,
        ],
        'allowed' => [
            'type' => 'checkbox',
            'label' => 'Povoleno',
            'default' => 1,
        ],
        'scope' => [
            'type' => 'select',
            'label' => 'Rozsah dat',
            'required' => true,
            'options' => [
                'all' => 'Všechna data',
                'customer' => 'Jen můj zákazník',
                'branch' => 'Jen má pobočka',
                'department' => 'Jen má oddělení',
                'own' => 'Jen já',
            ],
        ],
    ],
    
    'list_config' => [
        'columns' => ['role', 'module', 'action', 'allowed', 'scope'],
        'searchable' => ['role', 'module', 'action'],
        'sortable' => ['role', 'module', 'action'],
        'filters' => [
            'role' => true,
            'allowed' => true,
        ],
        'per_page' => 50,
        'enable_detail_modal' => false,
    ],
    
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'invalidate_on' => ['save', 'delete'],
    ],
];