<?php
/**
 * Account Types Module Configuration
 * 
 * SAW TABLE COMPLETE IMPLEMENTATION
 * 
 * @version 12.0.0 - Added custom_ajax_actions for proper AJAX routing
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(
    // =========================================
    // BASIC SETTINGS
    // =========================================
    
    'entity' => 'account_types',           // Used in JS (underscore)
    'table_name' => 'saw_account_types',   // Database table
    'singular' => 'Typ účtu',
    'plural' => 'Typy účtů',
    'route' => 'account-types',            // URL slug (hyphen)
    'icon' => '🏷️',
    'path' => __DIR__ . '/',
    
    // =========================================
    // CUSTOM AJAX ACTIONS - CRITICAL!
    // =========================================
    // Because slug is 'account-types' (hyphen) but entity is 'account_types' (underscore)
    // AJAX Registry registers: saw_load_sidebar_account-types
    // But JS sends: saw_load_sidebar_account_types
    // These custom actions bridge the gap
    
    'custom_ajax_actions' => array(
        'saw_load_sidebar_account_types' => 'ajax_load_sidebar',
        'saw_get_account_types_detail' => 'ajax_get_detail',
        'saw_create_account_types' => 'ajax_create',
        'saw_update_account_types' => 'ajax_update',
        'saw_delete_account_types' => 'ajax_delete',
        'saw_search_account_types' => 'ajax_search',
    ),
    
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
    
    // =========================================
    // LIST CONFIG
    // =========================================
    
    'list_config' => array(
        'per_page' => 50,
        'searchable' => array('name', 'display_name', 'description'),
        'default_orderby' => 'sort_order',
        'default_order' => 'ASC',
    ),
    
    // =========================================
    // TABS
    // =========================================
    
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
    
    // =========================================
    // TABLE COLUMNS
    // =========================================
    
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
    
    // =========================================
    // ACTIONS
    // =========================================
    
    'actions' => array('view', 'edit', 'delete'),
    
    // =========================================
    // DETAIL SIDEBAR
    // =========================================
    
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
    
    // =========================================
    // FORM SIDEBAR
    // =========================================
    
    'form' => array(
        'fields' => array(
            'name' => array(
                'type' => 'text',
                'label' => 'Systémový název',
                'required' => true,
                'help' => 'Unikátní identifikátor (bez mezer a diakritiky)',
            ),
            'display_name' => array(
                'type' => 'text',
                'label' => 'Zobrazovaný název',
                'help' => 'Název zobrazený uživatelům',
            ),
            'description' => array(
                'type' => 'textarea',
                'label' => 'Popis',
                'rows' => 3,
            ),
            'color' => array(
                'type' => 'color',
                'label' => 'Barva',
                'default' => '#3b82f6',
            ),
            'price' => array(
                'type' => 'number',
                'label' => 'Měsíční cena',
                'min' => 0,
                'step' => 0.01,
            ),
            'sort_order' => array(
                'type' => 'number',
                'label' => 'Pořadí řazení',
                'default' => 0,
                'min' => 0,
            ),
            'is_active' => array(
                'type' => 'checkbox',
                'label' => 'Status',
                'checkbox_label' => 'Aktivní',
                'default' => true,
            ),
        ),
    ),
    
    // =========================================
    // CACHE
    // =========================================
    
    'cache' => array(
        'enabled' => true,
        'ttl' => 300,
        'invalidate_on' => array('save', 'delete'),
    ),
);
