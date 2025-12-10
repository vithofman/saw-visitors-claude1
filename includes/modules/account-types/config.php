<?php
/**
 * Account Types Module Configuration
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @version     3.2.0 - FIXED: tab_param = 'is_active' (DB column name!)
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(
    // ==========================================
    // ENTITY IDENTIFICATION
    // ==========================================
    'entity' => 'account_types',
    'table' => 'saw_account_types',
    'singular' => 'Typ účtu',
    'plural' => 'Typy účtů',
    'route' => 'account-types',
    'icon' => '💳',
    
    // ==========================================
    // DATA ISOLATION (none - super_admin only)
    // ==========================================
    'has_customer_isolation' => false,
    'has_branch_isolation' => false,
    
    // ==========================================
    // CAPABILITIES (super_admin only)
    // ==========================================
    'capabilities' => array(
        'list' => 'manage_options',
        'view' => 'manage_options',
        'create' => 'manage_options',
        'edit' => 'manage_options',
        'delete' => 'manage_options',
    ),
    
    // ==========================================
    // TABS CONFIGURATION
    // CRITICAL: tab_param MUST be the DB column name!
    // ==========================================
    'tabs' => array(
        'enabled' => true,
        'tab_param' => 'is_active',  // ← MUSÍ BÝT NÁZEV DB SLOUPCE!
        'default_tab' => 'all',
        'tabs' => array(
            'all' => array(
                'label' => 'Všechny',
                'filter_value' => null,
                'icon' => '📋',
                'count_query' => true,
            ),
            'active' => array(
                'label' => 'Aktivní',
                'filter_value' => 1,  // ← DB hodnota pro is_active=1
                'icon' => '✓',
                'count_query' => true,
            ),
            'inactive' => array(
                'label' => 'Neaktivní',
                'filter_value' => 0,  // ← DB hodnota pro is_active=0
                'icon' => '✕',
                'count_query' => true,
            ),
        ),
    ),
    
    // ==========================================
    // LIST CONFIGURATION
    // ==========================================
    'list_config' => array(
        'per_page' => 50,
        'searchable' => array('name', 'display_name'),
        'sortable' => array('name', 'display_name', 'price', 'sort_order', 'created_at'),
        'default_orderby' => 'sort_order',
        'default_order' => 'ASC',
        'filters' => array(
            'is_active' => true,
        ),
        'enable_detail_modal' => true,
    ),
    
    // ==========================================
    // CACHE CONFIGURATION
    // ==========================================
    'cache' => array(
        'enabled' => true,
        'ttl' => 300,
        'invalidate_on' => array('save', 'delete'),
    ),
);
