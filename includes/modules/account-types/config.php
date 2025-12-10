<?php
/**
 * Account Types Module Configuration
 *
 * CLEAN config - NO CLOSURES/CALLBACKS here!
 * Callbacks are defined in list-template.php
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @version     4.2.0 - FIXED: No closures in config
 */

if (!defined('ABSPATH')) {
    exit;
}

return [
    // =========================================
    // BASIC SETTINGS
    // =========================================
    
    'entity' => 'account_types',
    'table' => 'saw_account_types',
    'singular' => 'Typ účtu',
    'plural' => 'Typy účtů',
    'route' => 'account-types',
    'icon' => '🏷️',
    'path' => __DIR__ . '/',
    
    // =========================================
    // MULTI-TENANT (global - no filtering)
    // =========================================
    
    'has_customer_isolation' => false,
    'has_branch_isolation' => false,
    'filter_by_customer' => false,
    'filter_by_branch' => false,
    
    // =========================================
    // PERMISSIONS
    // =========================================
    
    'permissions' => [
        'list' => ['super_admin'],
        'view' => ['super_admin'],
        'create' => ['super_admin'],
        'edit' => ['super_admin'],
        'delete' => ['super_admin'],
    ],
    
    'capabilities' => [
        'list' => 'manage_options',
        'view' => 'manage_options',
        'create' => 'manage_options',
        'edit' => 'manage_options',
        'delete' => 'manage_options',
    ],
    
    // =========================================
    // LIST CONFIG
    // =========================================
    
    'list_config' => [
        'per_page' => 50,
        'searchable' => ['name', 'display_name', 'description'],
        'default_orderby' => 'sort_order',
        'default_order' => 'ASC',
    ],
    
    // =========================================
    // TABS
    // =========================================
    
    'tabs' => [
        'enabled' => true,
        'tab_param' => 'tab',
        'default_tab' => 'all',
        'tabs' => [
            'all' => [
                'label' => 'Všechny',
                'filter_value' => null,
                'icon' => '📋',
            ],
            'active' => [
                'label' => 'Aktivní',
                'filter_value' => 1,
                'filter_field' => 'is_active',
                'icon' => '✓',
            ],
            'inactive' => [
                'label' => 'Neaktivní',
                'filter_value' => 0,
                'filter_field' => 'is_active',
                'icon' => '✕',
            ],
        ],
    ],
    
    // =========================================
    // TABLE COLUMNS (NO CALLBACKS - just structure)
    // =========================================
    
    'table' => [
        'columns' => [
            'color' => [
                'label' => '',
                'type' => 'custom',
                'sortable' => false,
                'width' => '50px',
            ],
            'display_name' => [
                'label' => 'Zobrazovaný název',
                'type' => 'text',
                'sortable' => true,
                'bold' => true,
            ],
            'name' => [
                'label' => 'Systémový název',
                'type' => 'code',
                'sortable' => true,
            ],
            'price' => [
                'label' => 'Cena',
                'type' => 'custom',
                'sortable' => true,
                'align' => 'right',
            ],
            'customers_count' => [
                'label' => 'Zákazníků',
                'type' => 'custom',
                'sortable' => false,
                'align' => 'center',
            ],
            'is_active' => [
                'label' => 'Status',
                'type' => 'badge',
                'map' => [
                    '1' => ['label' => 'Aktivní', 'color' => 'success'],
                    '0' => ['label' => 'Neaktivní', 'color' => 'secondary'],
                ],
            ],
        ],
    ],
    
    // =========================================
    // ACTIONS
    // =========================================
    
    'actions' => ['view', 'edit', 'delete'],
    
    // =========================================
    // DETAIL SIDEBAR
    // =========================================
    
    'detail' => [
        'title_field' => 'display_name',
        
        'header_badges' => [
            [
                'type' => 'status',
                'field' => 'is_active',
                'map' => [
                    '1' => ['label' => 'Aktivní', 'icon' => '✓', 'color' => 'success'],
                    '0' => ['label' => 'Neaktivní', 'icon' => '✕', 'color' => 'secondary'],
                ],
            ],
        ],
        
        'sections' => [
            'basic' => [
                'title' => 'Základní informace',
                'icon' => '📋',
                'type' => 'info_rows',
                'rows' => [
                    ['field' => 'name', 'label' => 'Systémový název', 'format' => 'code'],
                    ['field' => 'display_name', 'label' => 'Zobrazovaný název', 'bold' => true],
                    ['field' => 'description', 'label' => 'Popis'],
                ],
            ],
            'pricing' => [
                'title' => 'Ceník',
                'icon' => '💰',
                'type' => 'info_rows',
                'rows' => [
                    ['field' => 'price_formatted', 'label' => 'Měsíční cena', 'bold' => true],
                ],
            ],
            'statistics' => [
                'title' => 'Statistiky',
                'icon' => '📊',
                'type' => 'info_rows',
                'rows' => [
                    ['field' => 'customers_count', 'label' => 'Počet zákazníků', 'bold' => true],
                ],
            ],
            'metadata' => [
                'type' => 'metadata',
            ],
        ],
        
        'actions' => [
            'edit' => [
                'label' => 'Upravit',
                'icon' => 'edit',
                'type' => 'primary',
            ],
            'delete' => [
                'label' => 'Smazat',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => 'Opravdu chcete smazat tento typ účtu?',
            ],
        ],
    ],
    
    // =========================================
    // CACHE
    // =========================================
    
    'cache' => [
        'enabled' => true,
        'ttl' => 300,
    ],
];
