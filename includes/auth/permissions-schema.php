<?php
/**
 * SAW Permissions Schema
 *
 * Default permissions configuration loaded on plugin activation.
 * Defines which roles have access to which modules and actions.
 *
 * Structure:
 * [role] => [
 *     [module] => [
 *         'actions' => ['list', 'view', 'create', 'edit', 'delete'],
 *         'scope' => 'all|customer|branch|department|own'
 *     ]
 * ]
 *
 * Scope Values:
 * - 'all'        : Access to all data across all customers (super_admin only)
 * - 'customer'   : Access to data within user's customer (admin)
 * - 'branch'     : Access to data within user's branch (super_manager)
 * - 'department' : Access to data within user's department (manager)
 * - 'own'        : Access only to own records (terminal)
 * - 'none'       : No access (explicitly blocked)
 *
 * Actions:
 * - 'list'   : View list of items
 * - 'view'   : View item details
 * - 'create' : Create new items
 * - 'edit'   : Edit existing items
 * - 'delete' : Delete items
 * - '*'      : All actions (wildcard)
 *
 * @package    SAW_Visitors
 * @subpackage Permissions
 * @version    1.1.0
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

return [
    /**
     * Super Admin (WordPress Administrator)
     * 
     * Full access to everything including:
     * - Customer management
     * - Account type management
     * - System settings
     * - All modules across all customers
     */
    'super_admin' => [
        '*' => [
            'actions' => ['*'],
            'scope' => 'all',
        ],
    ],
    
    /**
     * Admin (Customer Administrator)
     * 
     * Full control within their customer:
     * - User management
     * - Branch/department management
     * - All operational modules
     * 
     * Cannot access:
     * - Other customers' data
     * - Customer management
     * - Account types
     */
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
        'oopp' => [
            'actions' => ['list', 'view', 'create', 'edit', 'delete'],
            'scope' => 'customer',
        ],
        // Explicitly blocked modules
        'customers' => [
            'actions' => [],
            'scope' => 'none',
        ],
        'account-types' => [
            'actions' => [],
            'scope' => 'none',
        ],
    ],
    
    /**
     * Super Manager (Branch Manager)
     * 
     * Manages operations within their branch:
     * - Can create/edit users in their branch
     * - Can manage departments
     * - Full operational control within branch
     * - Cannot delete users
     * 
     * Scope limited to assigned branch(es)
     */
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
        'oopp' => [
            'actions' => ['list', 'view', 'create', 'edit'],
            'scope' => 'branch',
        ],
    ],
    
    /**
     * Manager (Department Manager)
     * 
     * Manages day-to-day operations within department:
     * - Read-only access to structure (users/branches/departments)
     * - Can manage visitors, visits, companies
     * - Read-only access to materials/documents
     * 
     * Scope limited to assigned department(s)
     */
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
        'oopp' => [
            'actions' => ['list', 'view'],
            'scope' => 'department',
        ],
    ],
    
    /**
     * Terminal (Reception/Kiosk)
     * 
     * Limited access for check-in/out:
     * - Can only manage visits
     * - Can only see own terminal's visits
     * - Minimal permissions for security
     * 
     * Typically used for self-service kiosks
     */
    'terminal' => [
        'visits' => [
            'actions' => ['list', 'view', 'create', 'edit'],
            'scope' => 'own',
        ],
    ],
];