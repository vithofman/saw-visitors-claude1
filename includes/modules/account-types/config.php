<?php
/**
 * Account Types Module Config
 * 
 * @package SAW_Visitors
 * @version 2.0.0 - PRODUCTION: Complete fields definition
 */

if (!defined('ABSPATH')) {
    exit;
}

return [
    'entity' => 'account_types',
    'table' => 'saw_account_types',
    'singular' => 'Typ účtu',
    'plural' => 'Typy účtů',
    'route' => 'admin/settings/account-types',
    'icon' => '💳',
    
    'has_customer_isolation' => false,
    
    'capabilities' => [
        'list' => 'manage_options',
        'view' => 'manage_options',
        'create' => 'manage_options',
        'edit' => 'manage_options',
        'delete' => 'manage_options',
    ],
    
    'fields' => [
        'name' => [
            'type' => 'text',
            'label' => 'Interní název',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
            'help' => 'Unikátní slug (jen malá písmena, číslice a pomlčky)',
        ],
        'display_name' => [
            'type' => 'text',
            'label' => 'Zobrazovaný název',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
            'help' => 'Název který uvidí uživatelé',
        ],
        'description' => [
            'type' => 'textarea',
            'label' => 'Popis',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
            'help' => 'Volitelný popis typu účtu',
        ],
        'price' => [
            'type' => 'number',
            'label' => 'Cena (Kč/měsíc)',
            'required' => false,
            'default' => 0.00,
            'sanitize' => 'floatval',
            'help' => 'Měsíční cena v Kč (0 = zdarma)',
        ],
        'color' => [
            'type' => 'color',
            'label' => 'Barva',
            'required' => false,
            'default' => '#6b7280',
            'sanitize' => 'sanitize_hex_color',
            'help' => 'Barva pro vizuální označení typu účtu',
        ],
        'features' => [
            'type' => 'textarea',
            'label' => 'Seznam funkcí',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
            'help' => 'Každá funkce na nový řádek',
        ],
        'sort_order' => [
            'type' => 'number',
            'label' => 'Pořadí řazení',
            'required' => false,
            'default' => 0,
            'sanitize' => 'intval',
            'help' => 'Nižší číslo = vyšší v seznamu',
        ],
        'is_active' => [
            'type' => 'checkbox',
            'label' => 'Aktivní typ účtu',
            'required' => false,
            'default' => 1,
            'help' => 'Pouze aktivní typy jsou dostupné pro výběr',
        ],
    ],
    
    'list_config' => [
        'columns' => ['color', 'display_name', 'name', 'price', 'is_active'],
        'searchable' => ['name', 'display_name', 'description'],
        'sortable' => ['name', 'display_name', 'price', 'sort_order'],
        'filters' => [
            'is_active' => true,
        ],
        'per_page' => 20,
        'enable_detail_modal' => true,
    ],
    
    'cache' => [
        'enabled' => true,
        'ttl' => 300,
        'invalidate_on' => ['save', 'delete'],
    ],
];