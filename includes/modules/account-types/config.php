<?php
/**
 * Account Types Module Configuration
 * 
 * FIXED: table_name for DB, table for columns config
 * 
 * @version 4.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(
    // Basic settings
    'entity' => 'account_types',
    'table_name' => 'saw_account_types',  // ← OPRAVENO: table_name pro DB
    'singular' => 'Typ účtu',
    'plural' => 'Typy účtů',
    'route' => 'account-types',
    'icon' => '🏷️',
    'path' => __DIR__ . '/',
    
    // Multi-tenant (global - no filtering)
    'has_customer_isolation' => false,
    'has_branch_isolation' => false,
    'filter_by_customer' => false,
    'filter_by_branch' => false,
    
    // Permissions
    'permissions' => array(
        'list' => array('super_admin'),
        'view' => array('super_admin'),
        'create' => array('super_admin'),
        'edit' => array('super_admin'),
        'delete' => array('super_admin'),
    ),
    
    'capabilities' => array(
        'list' => 'manage_options',
        'view' => 'manage_options',
        'create' => 'manage_options',
        'edit' => 'manage_options',
        'delete' => 'manage_options',
    ),
    
    // List config
    'list_config' => array(
        'per_page' => 50,
        'searchable' => array('name', 'display_name', 'description'),
        'default_orderby' => 'sort_order',
        'default_order' => 'ASC',
    ),
    
    // Tabs
    'tabs' => array(
        'enabled' => true,
        'tab_param' => 'tab',
        'default_tab' => 'all',
        'tabs' => array(
            'all' => array(
                'label' => 'Všechny',
                'filter_value' => null,
                'icon' => '📋',
            ),
            'active' => array(
                'label' => 'Aktivní',
                'filter_value' => 1,
                'filter_field' => 'is_active',
                'icon' => '✓',
            ),
            'inactive' => array(
                'label' => 'Neaktivní',
                'filter_value' => 0,
                'filter_field' => 'is_active',
                'icon' => '✕',
            ),
        ),
    ),
    
    // Table columns config - NO CALLBACKS HERE
    'table' => array(
        'columns' => array(
            'color' => array(
                'label' => 'Barva',
                'type' => 'color',
                'sortable' => false,
                'width' => '50px',
            ),
            'display_name' => array(
                'label' => 'Název',
                'type' => 'text',
                'sortable' => true,
                'bold' => true,
            ),
            'name' => array(
                'label' => 'Systémový název',
                'type' => 'code',
                'sortable' => true,
            ),
            'price' => array(
                'label' => 'Cena',
                'type' => 'currency',
                'sortable' => true,
                'align' => 'right',
            ),
            'customers_count' => array(
                'label' => 'Zákazníků',
                'type' => 'number',
                'sortable' => false,
                'align' => 'center',
            ),
            'is_active' => array(
                'label' => 'Status',
                'type' => 'badge',
                'map' => array(
                    '1' => array('label' => 'Aktivní', 'color' => 'success'),
                    '0' => array('label' => 'Neaktivní', 'color' => 'secondary'),
                ),
            ),
        ),
    ),
    
    // Actions
    'actions' => array('view', 'edit', 'delete'),
    
    // Detail sidebar
    'detail' => array(
        'title_field' => 'display_name',
        
        'header_badges' => array(
            array(
                'type' => 'status',
                'field' => 'is_active',
                'map' => array(
                    '1' => array('label' => 'Aktivní', 'icon' => '✓', 'color' => 'success'),
                    '0' => array('label' => 'Neaktivní', 'icon' => '✕', 'color' => 'secondary'),
                ),
            ),
        ),
        
        'sections' => array(
            'basic' => array(
                'title' => 'Základní informace',
                'icon' => '📋',
                'type' => 'info_rows',
                'rows' => array(
                    array('field' => 'name', 'label' => 'Systémový název', 'format' => 'code'),
                    array('field' => 'display_name', 'label' => 'Zobrazovaný název', 'bold' => true),
                    array('field' => 'description', 'label' => 'Popis'),
                ),
            ),
            'pricing' => array(
                'title' => 'Ceník',
                'icon' => '💰',
                'type' => 'info_rows',
                'rows' => array(
                    array('field' => 'price_formatted', 'label' => 'Měsíční cena', 'bold' => true),
                ),
            ),
            'statistics' => array(
                'title' => 'Statistiky',
                'icon' => '📊',
                'type' => 'info_rows',
                'rows' => array(
                    array('field' => 'customers_count', 'label' => 'Počet zákazníků', 'bold' => true),
                ),
            ),
            'metadata' => array(
                'type' => 'metadata',
            ),
        ),
        
        'actions' => array(
            'edit' => array(
                'label' => 'Upravit',
                'icon' => 'edit',
                'type' => 'primary',
            ),
            'delete' => array(
                'label' => 'Smazat',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => 'Opravdu chcete smazat tento typ účtu?',
            ),
        ),
    ),
    
    // Cache
    'cache' => array(
        'enabled' => true,
        'ttl' => 300,
    ),
);
