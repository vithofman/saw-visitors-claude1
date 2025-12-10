<?php
/**
 * Account Types Module Configuration
 *
 * EXAMPLE configuration demonstrating the new SAW Table config format.
 * This is a PILOT module for the refactoring.
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @version     2.0.0 - NEW FORMAT with detail config
 * @since       1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// TRANSLATIONS SETUP
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}

$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'account-types') 
    : [];

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// MODULE CONFIGURATION
// ============================================

return [
    // =========================================
    // ZÁKLADNÍ NASTAVENÍ
    // =========================================
    
    'entity' => 'account_types',
    'singular' => $tr('singular', 'Typ účtu'),
    'plural' => $tr('plural', 'Typy účtů'),
    'route' => 'account-types',
    'icon' => '🏷️',
    'path' => __DIR__ . '/',
    
    // =========================================
    // MULTI-TENANT FILTERING
    // =========================================
    
    'filter_by_customer' => true,
    'filter_by_branch' => false,
    
    // =========================================
    // PERMISSIONS
    // =========================================
    
    'permissions' => [
        'list' => ['super_admin', 'admin', 'super_manager'],
        'view' => ['super_admin', 'admin', 'super_manager'],
        'create' => ['super_admin', 'admin'],
        'edit' => ['super_admin', 'admin'],
        'delete' => ['super_admin'],
    ],
    
    // =========================================
    // TABULKA
    // =========================================
    
    'table' => [
        'entity' => 'account_types',
        'table_name' => 'saw_account_types',
        
        'columns' => [
            'name' => [
                'label' => $tr('field_name', 'Název'),
                'sortable' => true,
                'type' => 'text',
                'bold' => true,
            ],
            'color' => [
                'label' => $tr('field_color', 'Barva'),
                'type' => 'color_badge',
                'sortable' => false,
            ],
            'description' => [
                'label' => $tr('field_description', 'Popis'),
                'type' => 'text',
                'sortable' => false,
            ],
            'customers_count' => [
                'label' => $tr('field_customers_count', 'Zákazníků'),
                'type' => 'text',
                'sortable' => true,
                'align' => 'center',
            ],
        ],
        
        'default_order' => 'name',
        'default_order_dir' => 'ASC',
        'per_page' => 25,
    ],
    
    // =========================================
    // TABS - not needed for this module
    // =========================================
    
    'tabs' => [
        'enabled' => false,
    ],
    
    // =========================================
    // INFINITE SCROLL
    // =========================================
    
    'infinite_scroll' => [
        'enabled' => true,
        'initial_load' => 50,
        'per_page' => 25,
        'threshold' => 0.6,
    ],
    
    // =========================================
    // DETAIL SIDEBAR - NEW CONFIG FORMAT
    // =========================================
    
    'detail' => [
        // Header image - not used for this module
        'header_image' => [
            'enabled' => false,
        ],
        
        // Header badges
        'header_badges' => [
            [
                'type' => 'code',
                'field' => 'id',
                'prefix' => 'ID: ',
            ],
            [
                'type' => 'icon_text',
                'field' => 'color',
                'icon' => '🎨',
            ],
        ],
        
        // Display name field
        'display_name_field' => 'name',
        
        // Sekce
        'sections' => [
            // Basic info section
            'basic' => [
                'title' => $tr('section_basic', 'Základní informace'),
                'title_key' => 'section_basic',
                'icon' => '📋',
                'type' => 'info_rows',
                'rows' => [
                    [
                        'field' => 'name',
                        'label' => $tr('field_name', 'Název'),
                        'label_key' => 'field_name',
                        'bold' => true,
                    ],
                    [
                        'field' => 'description',
                        'label' => $tr('field_description', 'Popis'),
                        'label_key' => 'field_description',
                        'empty_text' => '—',
                    ],
                    [
                        'field' => 'color',
                        'label' => $tr('field_color', 'Barva'),
                        'label_key' => 'field_color',
                        'format' => 'code',
                    ],
                ],
            ],
            
            // Statistics section
            'statistics' => [
                'title' => $tr('section_statistics', 'Statistiky'),
                'title_key' => 'section_statistics',
                'icon' => '📊',
                'type' => 'info_rows',
                'rows' => [
                    [
                        'field' => 'customers_count',
                        'label' => $tr('field_customers_count', 'Počet zákazníků'),
                        'label_key' => 'field_customers_count',
                        'bold' => true,
                    ],
                ],
            ],
            
            // Related customers section
            'customers' => [
                'title' => $tr('section_customers', 'Zákazníci s tímto typem'),
                'title_key' => 'section_customers',
                'icon' => '👥',
                'type' => 'related_list',
                'show_count' => true,
                'data_key' => 'customers',
                'max_items' => 5,
                'permission' => 'view:customers',
                'item' => [
                    'icon' => '🏢',
                    'name_field' => 'name',
                    'link' => '/admin/customers/{id}/',
                ],
                'show_all_link' => '/admin/customers/?account_type_id={id}',
                'empty_text' => $tr('no_customers', 'Žádní zákazníci s tímto typem'),
            ],
            
            // Metadata section
            'metadata' => [
                'type' => 'metadata',
            ],
        ],
        
        // Akční tlačítka
        'actions' => [
            'edit' => [
                'label' => $tr('btn_edit', 'Upravit'),
                'label_key' => 'btn_edit',
                'icon' => 'edit',
                'type' => 'primary',
                'permission' => 'edit',
            ],
            'delete' => [
                'label' => $tr('btn_delete', 'Smazat'),
                'label_key' => 'btn_delete',
                'icon' => 'trash',
                'type' => 'danger',
                'permission' => 'delete',
                'confirm' => $tr('confirm_delete', 'Opravdu chcete smazat tento typ účtu?'),
            ],
        ],
    ],
    
    // =========================================
    // FORM SIDEBAR - for future implementation
    // =========================================
    
    'form' => [
        'fields' => [
            'name' => [
                'type' => 'text',
                'label' => $tr('field_name', 'Název'),
                'required' => true,
                'validation' => 'required|min:2|max:255',
            ],
            'color' => [
                'type' => 'color',
                'label' => $tr('field_color', 'Barva'),
                'default' => '#3b82f6',
            ],
            'description' => [
                'type' => 'textarea',
                'label' => $tr('field_description', 'Popis'),
                'rows' => 3,
            ],
        ],
    ],
    
    // =========================================
    // CACHE
    // =========================================
    
    'cache' => [
        'enabled' => true,
        'ttl' => 300,
        'invalidate_on' => ['save', 'delete'],
    ],
];
