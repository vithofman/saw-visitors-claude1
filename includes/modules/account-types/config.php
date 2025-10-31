<?php
/**
 * Account Types Module Config
 * 
 * Konfigurace pro správu typů účtů (free, basic, pro, enterprise).
 * Obsahuje fields definition, list config, cache settings a capabilities.
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 * @since   4.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

return [
    // === ZÁKLADNÍ KONFIGURACE ===
    'entity' => 'account-types',
    'table' => 'saw_account_types',
    'singular' => 'Typ účtu',
    'plural' => 'Typy účtů',
    'route' => 'admin/settings/account-types',
    'icon' => '💳',
    
    // === CAPABILITIES (kdo může dělat co) ===
    'capabilities' => [
        'list' => 'manage_options',
        'view' => 'manage_options',
        'create' => 'manage_options',
        'edit' => 'manage_options',
        'delete' => 'manage_options',
    ],
    
    // === FIELDS DEFINITION ===
    // Všechna pole která entita má, včetně validace a sanitizace
    'fields' => [
        // Interní název (slug) - např. "free", "basic", "pro"
        'name' => [
            'type' => 'text',
            'label' => 'Interní název',
            'required' => true,
            'sanitize' => 'sanitize_title',
            'help' => 'Unikátní slug bez mezer (např. "free", "basic")',
        ],
        
        // Zobrazovaný název - např. "Free", "Basic", "Pro"
        'display_name' => [
            'type' => 'text',
            'label' => 'Zobrazovaný název',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
            'help' => 'Název který uvidí uživatelé',
        ],
        
        // Barva pro vizuální rozlišení
        'color' => [
            'type' => 'color',
            'label' => 'Barva',
            'required' => false,
            'default' => '#6b7280',
            'sanitize' => 'sanitize_hex_color',
            'help' => 'Barva pro vizuální označení typu',
        ],
        
        // Cena (měsíčně)
        'price' => [
            'type' => 'number',
            'label' => 'Cena (Kč/měsíc)',
            'required' => false,
            'default' => 0.00,
            'sanitize' => 'floatval',
            'step' => '0.01',
            'min' => '0',
            'help' => 'Měsíční cena v Kč',
        ],
        
        // Features (JSON string s funkcemi)
        'features' => [
            'type' => 'textarea',
            'label' => 'Funkce',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
            'help' => 'Každá funkce na nový řádek',
            'rows' => 8,
        ],
        
        // Pořadí řazení
        'sort_order' => [
            'type' => 'number',
            'label' => 'Pořadí řazení',
            'required' => false,
            'default' => 0,
            'sanitize' => 'absint',
            'help' => 'Nižší číslo = vyšší v seznamu',
        ],
        
        // Aktivní / neaktivní
        'is_active' => [
            'type' => 'checkbox',
            'label' => 'Aktivní',
            'required' => false,
            'default' => 1,
            'sanitize' => 'absint',
            'help' => 'Pouze aktivní typy jsou viditelné pro výběr',
        ],
    ],
    
    // === LIST CONFIGURATION ===
    // Jak se zobrazuje seznam v tabulce
    'list_config' => [
        // Které sloupce se zobrazují v tabulce
        'columns' => ['display_name', 'name', 'price', 'color', 'is_active', 'sort_order'],
        
        // Ve kterých sloupcích lze vyhledávat
        'searchable' => ['name', 'display_name'],
        
        // Které sloupce lze řadit (klikem na header)
        'sortable' => ['name', 'display_name', 'price', 'sort_order', 'created_at'],
        
        // Filtry v list view
        'filters' => [
            'is_active' => true, // Filtr aktivní/neaktivní
        ],
        
        // Kolik položek na stránku
        'per_page' => 20,
        
        // Povolit modal detail při kliknutí na řádek
        'enable_detail_modal' => true,
    ],
    
    // === CACHE SETTINGS ===
    // Cachování pro rychlejší načítání
    'cache' => [
        'enabled' => true,
        'ttl' => 3600, // 1 hodina (account types se mění málokdy)
        'invalidate_on' => ['save', 'delete'], // Kdy smazat cache
    ],
];
