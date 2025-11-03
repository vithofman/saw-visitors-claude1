<?php
/**
 * SAW Permissions Schema
 * 
 * Default permissions configuration loaded on plugin activation.
 * Defines which roles have access to which modules and actions.
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 * @since 4.10.0
 */

if (!defined('ABSPATH')) {
    exit;
}

return [
    'super_admin' => [
        '*' => [
            'actions' => ['*'],
            'scope' => 'all',
        ],
    ],
    
    'admin' => [
        'users' => [
            'actions' => ['list', 'view', 'create', 'edit', 'delete'],
            'scope' => 'customer',
        ],
        'branches' => [
            'actions' => ['list', 'view', 'create', 'edit', 'delete'],
            'scope' => 'customer',
        ],
        'departments' => [
            'actions' => ['list', 'view', 'create', 'edit', 'delete'],
            'scope' => 'customer',
        ],
        'contact_persons' => [
            'actions' => ['list', 'view', 'create', 'edit', 'delete'],
            'scope' => 'customer',
        ],
        'companies' => [
            'actions' => ['list', 'view', 'create', 'edit', 'delete'],
            'scope' => 'customer',
        ],
        'invitations' => [
            'actions' => ['list', 'view', 'create', 'edit', 'delete'],
            'scope' => 'customer',
        ],
        'visitors' => [
            'actions' => ['list', 'view', 'create', 'edit', 'delete'],
            'scope' => 'customer',
        ],
        'visits' => [
            'actions' => ['list', 'view', 'create', 'edit', 'delete'],
            'scope' => 'customer',
        ],
        'materials' => [
            'actions' => ['list', 'view', 'create', 'edit', 'delete'],
            'scope' => 'customer',
        ],
        'documents' => [
            'actions' => ['list', 'view', 'create', 'edit', 'delete'],
            'scope' => 'customer',
        ],
        'training_languages' => [
            'actions' => ['list', 'view', 'create', 'edit', 'delete'],
            'scope' => 'customer',
        ],
        'customers' => [
            'actions' => [],
            'scope' => 'none',
        ],
        'account-types' => [
            'actions' => [],
            'scope' => 'none',
        ],
    ],
    
    'super_manager' => [
        'users' => [
            'actions' => ['list', 'view', 'create', 'edit'],
            'scope' => 'branch',
        ],
        'branches' => [
            'actions' => ['list', 'view'],
            'scope' => 'branch',
        ],
        'departments' => [
            'actions' => ['list', 'view', 'create', 'edit'],
            'scope' => 'branch',
        ],
        'contact_persons' => [
            'actions' => ['list', 'view', 'create', 'edit'],
            'scope' => 'branch',
        ],
        'companies' => [
            'actions' => ['list', 'view', 'create', 'edit'],
            'scope' => 'branch',
        ],
        'invitations' => [
            'actions' => ['list', 'view', 'create', 'edit'],
            'scope' => 'branch',
        ],
        'visitors' => [
            'actions' => ['list', 'view', 'create', 'edit'],
            'scope' => 'branch',
        ],
        'visits' => [
            'actions' => ['list', 'view', 'create', 'edit'],
            'scope' => 'branch',
        ],
        'materials' => [
            'actions' => ['list', 'view'],
            'scope' => 'branch',
        ],
        'documents' => [
            'actions' => ['list', 'view'],
            'scope' => 'branch',
        ],
    ],
    
    'manager' => [
        'users' => [
            'actions' => ['list', 'view'],
            'scope' => 'department',
        ],
        'branches' => [
            'actions' => ['list', 'view'],
            'scope' => 'department',
        ],
        'departments' => [
            'actions' => ['list', 'view'],
            'scope' => 'department',
        ],
        'contact_persons' => [
            'actions' => ['list', 'view'],
            'scope' => 'department',
        ],
        'companies' => [
            'actions' => ['list', 'view', 'create', 'edit'],
            'scope' => 'department',
        ],
        'invitations' => [
            'actions' => ['list', 'view', 'create', 'edit'],
            'scope' => 'department',
        ],
        'visitors' => [
            'actions' => ['list', 'view', 'create', 'edit'],
            'scope' => 'department',
        ],
        'visits' => [
            'actions' => ['list', 'view', 'create', 'edit'],
            'scope' => 'department',
        ],
        'materials' => [
            'actions' => ['list', 'view'],
            'scope' => 'department',
        ],
        'documents' => [
            'actions' => ['list', 'view'],
            'scope' => 'department',
        ],
    ],
    
    'terminal' => [
        'visits' => [
            'actions' => ['list', 'view', 'create', 'edit'],
            'scope' => 'own',
        ],
    ],
];
