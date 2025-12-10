<?php
/**
 * Account Types Module Configuration
 *
 * Complete configuration for SAW Table component.
 * Includes table, tabs, detail sidebar, and form definitions.
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @version     4.0.0 - SAW Table Integration
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
    'table' => 'saw_account_types',
    'singular' => $tr('singular', 'Typ účtu'),
    'plural' => $tr('plural', 'Typy účtů'),
    'route' => 'account-types',
    'icon' => '🏷️',
    'path' => __DIR__ . '/',
    
    // =========================================
    // MULTI-TENANT FILTERING
    // Account types jsou globální - bez izolace
    // =========================================
    
    'has_customer_isolation' => false,
    'has_branch_isolation' => false,
    'filter_by_customer' => false,
    'filter_by_branch' => false,
    
    // =========================================
    // PERMISSIONS (super_admin only)
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
    // TABS
    // =========================================
    
    'tabs' => [
        'enabled' => true,
        'tab_param' => 'is_active',
        'default_tab' => 'all',
        'tabs' => [
            'all' => [
                'label' => $tr('tab_all', 'Všechny'),
                'filter_value' => null,
                'icon' => '📋',
                'count_query' => true,
            ],
            'active' => [
                'label' => $tr('tab_active', 'Aktivní'),
                'filter_value' => 1,
                'icon' => '✓',
                'count_query' => true,
            ],
            'inactive' => [
                'label' => $tr('tab_inactive', 'Neaktivní'),
                'filter_value' => 0,
                'icon' => '✕',
                'count_query' => true,
            ],
        ],
    ],
    
    // =========================================
    // SEARCH
    // =========================================
    
    'search' => [
        'enabled' => true,
        'placeholder' => $tr('search_placeholder', 'Hledat typ účtu...'),
        'fields' => ['name', 'display_name', 'description'],
    ],
    
    // =========================================
    // TABLE COLUMNS
    // =========================================
    
    'table' => [
        'entity' => 'account_types',
        'table_name' => 'saw_account_types',
        
        'columns' => [
            'color' => [
                'label' => '',
                'type' => 'custom',
                'sortable' => false,
                'width' => '50px',
                'callback' => function($value, $item) {
                    if (empty($value)) {
                        return '<span class="sawt-text-muted">—</span>';
                    }
                    return sprintf(
                        '<span class="sawt-color-swatch" style="background-color: %s;" title="%s"></span>',
                        esc_attr($value),
                        esc_attr($value)
                    );
                },
            ],
            'display_name' => [
                'label' => $tr('field_display_name', 'Zobrazovaný název'),
                'type' => 'text',
                'sortable' => true,
                'bold' => true,
            ],
            'name' => [
                'label' => $tr('field_name', 'Systémový název'),
                'type' => 'code',
                'sortable' => true,
            ],
            'price' => [
                'label' => $tr('field_price', 'Cena'),
                'type' => 'currency',
                'currency' => 'Kč',
                'decimals' => 0,
                'sortable' => true,
                'align' => 'right',
            ],
            'customers_count' => [
                'label' => $tr('field_customers', 'Zákazníků'),
                'type' => 'number',
                'sortable' => true,
                'align' => 'center',
            ],
            'is_active' => [
                'label' => $tr('field_status', 'Status'),
                'type' => 'badge',
                'map' => [
                    '1' => ['label' => $tr('status_active', 'Aktivní'), 'color' => 'success'],
                    '0' => ['label' => $tr('status_inactive', 'Neaktivní'), 'color' => 'secondary'],
                ],
                'sortable' => true,
            ],
        ],
        
        'default_order' => 'sort_order',
        'default_order_dir' => 'ASC',
    ],
    
    // =========================================
    // LIST CONFIG (legacy compatibility)
    // =========================================
    
    'list_config' => [
        'per_page' => 50,
        'searchable' => ['name', 'display_name', 'description'],
        'sortable' => ['name', 'display_name', 'price', 'sort_order', 'created_at'],
        'default_orderby' => 'sort_order',
        'default_order' => 'ASC',
        'filters' => [
            'is_active' => true,
        ],
        'enable_detail_modal' => true,
    ],
    
    // =========================================
    // INFINITE SCROLL
    // =========================================
    
    'infinite_scroll' => [
        'enabled' => false, // Account types jsou málo, nepotřebujeme
        'initial_load' => 50,
        'per_page' => 25,
    ],
    
    // =========================================
    // ACTIONS (table row actions)
    // =========================================
    
    'actions' => ['view', 'edit', 'delete'],
    
    // =========================================
    // DETAIL SIDEBAR
    // =========================================
    
    'detail' => [
        // Title field
        'title_field' => 'display_name',
        
        // Header image (color badge instead)
        'header_image' => [
            'enabled' => false,
        ],
        
        // Header badges
        'header_badges' => [
            [
                'type' => 'color',
                'field' => 'color',
                'show_value' => true,
            ],
            [
                'type' => 'status',
                'field' => 'is_active',
                'map' => [
                    '1' => [
                        'label' => $tr('status_active', 'Aktivní'),
                        'icon' => '✓',
                        'color' => 'success',
                    ],
                    '0' => [
                        'label' => $tr('status_inactive', 'Neaktivní'),
                        'icon' => '✕',
                        'color' => 'secondary',
                    ],
                ],
            ],
        ],
        
        // Sections
        'sections' => [
            // Základní info
            'basic' => [
                'title' => $tr('section_basic', 'Základní informace'),
                'icon' => '📋',
                'type' => 'info_rows',
                'rows' => [
                    [
                        'field' => 'name',
                        'label' => $tr('field_name', 'Systémový název'),
                        'format' => 'code',
                    ],
                    [
                        'field' => 'display_name',
                        'label' => $tr('field_display_name', 'Zobrazovaný název'),
                        'bold' => true,
                    ],
                    [
                        'field' => 'description',
                        'label' => $tr('field_description', 'Popis'),
                        'condition' => '!empty($item["description"])',
                    ],
                ],
            ],
            
            // Ceník
            'pricing' => [
                'title' => $tr('section_pricing', 'Ceník'),
                'icon' => '💰',
                'type' => 'info_rows',
                'rows' => [
                    [
                        'field' => 'price',
                        'label' => $tr('field_price', 'Měsíční cena'),
                        'format' => 'currency',
                        'currency' => 'Kč',
                        'decimals' => 0,
                        'bold' => true,
                        'highlight' => true,
                    ],
                    [
                        'field' => 'price_yearly',
                        'label' => $tr('field_price_yearly', 'Roční cena'),
                        'format' => 'currency',
                        'currency' => 'Kč',
                        'decimals' => 0,
                        'condition' => '!empty($item["price_yearly"])',
                    ],
                ],
            ],
            
            // Limity
            'limits' => [
                'title' => $tr('section_limits', 'Limity'),
                'icon' => '📊',
                'type' => 'info_rows',
                'condition' => '!empty($item["max_users"]) || !empty($item["max_branches"]) || !empty($item["max_visitors_monthly"])',
                'rows' => [
                    [
                        'field' => 'max_users',
                        'label' => $tr('field_max_users', 'Max uživatelů'),
                        'format' => 'number',
                        'condition' => '!empty($item["max_users"])',
                    ],
                    [
                        'field' => 'max_branches',
                        'label' => $tr('field_max_branches', 'Max poboček'),
                        'format' => 'number',
                        'condition' => '!empty($item["max_branches"])',
                    ],
                    [
                        'field' => 'max_visitors_monthly',
                        'label' => $tr('field_max_visitors', 'Max návštěvníků/měsíc'),
                        'format' => 'number',
                        'condition' => '!empty($item["max_visitors_monthly"])',
                    ],
                ],
            ],
            
            // Funkce
            'features' => [
                'title' => $tr('section_features', 'Funkce'),
                'icon' => '⚡',
                'type' => 'feature_list',
                'features' => [
                    ['field' => 'has_api_access', 'label' => $tr('feature_api', 'API přístup')],
                    ['field' => 'has_custom_branding', 'label' => $tr('feature_branding', 'Vlastní branding')],
                    ['field' => 'has_advanced_reports', 'label' => $tr('feature_reports', 'Pokročilé reporty')],
                    ['field' => 'has_sso', 'label' => $tr('feature_sso', 'SSO integrace')],
                    ['field' => 'has_priority_support', 'label' => $tr('feature_support', 'Prioritní podpora')],
                ],
            ],
            
            // Statistiky
            'statistics' => [
                'title' => $tr('section_statistics', 'Využití'),
                'icon' => '📈',
                'type' => 'stat_grid',
                'stats' => [
                    [
                        'field' => 'customers_count',
                        'label' => $tr('stat_customers', 'Zákazníků'),
                        'color' => 'primary',
                    ],
                    [
                        'field' => 'sort_order',
                        'label' => $tr('stat_sort_order', 'Pořadí'),
                    ],
                ],
            ],
            
            // Zákazníci s tímto typem
            'customers' => [
                'title' => $tr('section_customers', 'Zákazníci'),
                'icon' => '👥',
                'type' => 'related_list',
                'show_count' => true,
                'data_key' => 'customers',
                'max_items' => 5,
                'permission' => 'view:customers',
                'item' => [
                    'icon' => '🏢',
                    'name_field' => 'name',
                    'subtitle_field' => 'status',
                    'link' => '/admin/customers/{id}/',
                ],
                'show_all_link' => '/admin/customers/?account_type_id={id}',
                'empty_text' => $tr('no_customers', 'Žádní zákazníci'),
                'empty_icon' => '👥',
            ],
            
            // Metadata
            'metadata' => [
                'type' => 'metadata',
            ],
        ],
        
        // Action buttons
        'actions' => [
            'edit' => [
                'label' => $tr('btn_edit', 'Upravit'),
                'icon' => 'edit',
                'type' => 'primary',
                'permission' => 'edit',
            ],
            'delete' => [
                'label' => $tr('btn_delete', 'Smazat'),
                'icon' => 'trash',
                'type' => 'danger',
                'permission' => 'delete',
                'confirm' => $tr('confirm_delete', 'Opravdu chcete smazat tento typ účtu? Tato akce je nevratná.'),
            ],
        ],
    ],
    
    // =========================================
    // FORM SIDEBAR
    // =========================================
    
    'form' => [
        'fields' => [
            // Základní
            'display_name' => [
                'type' => 'text',
                'label' => $tr('field_display_name', 'Zobrazovaný název'),
                'required' => true,
                'placeholder' => $tr('placeholder_display_name', 'např. Premium'),
                'help' => $tr('help_display_name', 'Název zobrazovaný zákazníkům'),
            ],
            'name' => [
                'type' => 'text',
                'label' => $tr('field_name', 'Systémový název'),
                'required' => true,
                'placeholder' => $tr('placeholder_name', 'např. premium'),
                'help' => $tr('help_name', 'Interní identifikátor (malá písmena, bez mezer)'),
            ],
            'description' => [
                'type' => 'textarea',
                'label' => $tr('field_description', 'Popis'),
                'rows' => 3,
                'placeholder' => $tr('placeholder_description', 'Stručný popis typu účtu...'),
            ],
            'color' => [
                'type' => 'color',
                'label' => $tr('field_color', 'Barva'),
                'default' => '#3b82f6',
                'help' => $tr('help_color', 'Barva pro vizuální rozlišení'),
            ],
            
            // Sekce: Ceník
            '_section_pricing' => [
                'type' => 'section',
                'label' => $tr('section_pricing', 'Ceník'),
                'icon' => '💰',
            ],
            'price' => [
                'type' => 'number',
                'label' => $tr('field_price', 'Měsíční cena (Kč)'),
                'min' => 0,
                'step' => 1,
                'default' => 0,
            ],
            'price_yearly' => [
                'type' => 'number',
                'label' => $tr('field_price_yearly', 'Roční cena (Kč)'),
                'min' => 0,
                'step' => 1,
                'help' => $tr('help_price_yearly', 'Volitelné - pro roční fakturaci'),
            ],
            
            // Sekce: Limity
            '_section_limits' => [
                'type' => 'section',
                'label' => $tr('section_limits', 'Limity'),
                'icon' => '📊',
            ],
            'max_users' => [
                'type' => 'number',
                'label' => $tr('field_max_users', 'Max uživatelů'),
                'min' => 0,
                'placeholder' => $tr('placeholder_unlimited', 'Neomezeno'),
                'help' => $tr('help_max_users', '0 = neomezeno'),
            ],
            'max_branches' => [
                'type' => 'number',
                'label' => $tr('field_max_branches', 'Max poboček'),
                'min' => 0,
                'placeholder' => $tr('placeholder_unlimited', 'Neomezeno'),
            ],
            'max_visitors_monthly' => [
                'type' => 'number',
                'label' => $tr('field_max_visitors', 'Max návštěvníků/měsíc'),
                'min' => 0,
                'placeholder' => $tr('placeholder_unlimited', 'Neomezeno'),
            ],
            
            // Sekce: Funkce
            '_section_features' => [
                'type' => 'section',
                'label' => $tr('section_features', 'Funkce'),
                'icon' => '⚡',
            ],
            'has_api_access' => [
                'type' => 'checkbox',
                'label' => $tr('feature_api', 'API přístup'),
                'checkbox_label' => $tr('feature_api_enabled', 'Povolit API přístup'),
            ],
            'has_custom_branding' => [
                'type' => 'checkbox',
                'label' => $tr('feature_branding', 'Vlastní branding'),
                'checkbox_label' => $tr('feature_branding_enabled', 'Povolit vlastní logo a barvy'),
            ],
            'has_advanced_reports' => [
                'type' => 'checkbox',
                'label' => $tr('feature_reports', 'Pokročilé reporty'),
                'checkbox_label' => $tr('feature_reports_enabled', 'Povolit pokročilé reporty'),
            ],
            'has_sso' => [
                'type' => 'checkbox',
                'label' => $tr('feature_sso', 'SSO integrace'),
                'checkbox_label' => $tr('feature_sso_enabled', 'Povolit SSO'),
            ],
            'has_priority_support' => [
                'type' => 'checkbox',
                'label' => $tr('feature_support', 'Prioritní podpora'),
                'checkbox_label' => $tr('feature_support_enabled', 'Prioritní podpora zákazníků'),
            ],
            
            // Sekce: Nastavení
            '_section_settings' => [
                'type' => 'section',
                'label' => $tr('section_settings', 'Nastavení'),
                'icon' => '⚙️',
            ],
            'sort_order' => [
                'type' => 'number',
                'label' => $tr('field_sort_order', 'Pořadí'),
                'min' => 0,
                'default' => 0,
                'help' => $tr('help_sort_order', 'Pro řazení v seznamech'),
            ],
            'is_active' => [
                'type' => 'checkbox',
                'label' => $tr('field_is_active', 'Status'),
                'checkbox_label' => $tr('is_active_label', 'Typ účtu je aktivní'),
                'default' => true,
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
