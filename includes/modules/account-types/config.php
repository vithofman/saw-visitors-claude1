<?php
/**
 * Account Types Module Configuration
 * 
 * Defines complete module structure including:
 * - Database table and entity name
 * - Field definitions with validation rules
 * - List view configuration (columns, filters, sorting)
 * - Cache settings
 * - Capabilities and permissions
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @since       1.0.0
 * @version     2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(
    // Basic module identification
    'entity' => 'account_types',
    'table' => 'saw_account_types',
    'singular' => __('Typ účtu', 'saw-visitors'),
    'plural' => __('Typy účtů', 'saw-visitors'),
    'route' => 'admin/settings/account-types',
    'icon' => '💳',
    
    // Customer isolation disabled (account types are global)
    'has_customer_isolation' => false,
    
    // Capabilities required for each action
    'capabilities' => array(
        'list' => 'manage_options',
        'view' => 'manage_options',
        'create' => 'manage_options',
        'edit' => 'manage_options',
        'delete' => 'manage_options',
    ),
    
    // Field definitions
    'fields' => array(
        'name' => array(
            'type' => 'text',
            'label' => __('Interní název', 'saw-visitors'),
            'required' => true,
            'sanitize' => 'sanitize_text_field',
            'help' => __('Unikátní slug (jen malá písmena, číslice a pomlčky)', 'saw-visitors'),
        ),
        'display_name' => array(
            'type' => 'text',
            'label' => __('Zobrazovaný název', 'saw-visitors'),
            'required' => true,
            'sanitize' => 'sanitize_text_field',
            'help' => __('Název který uvidí uživatelé', 'saw-visitors'),
        ),
        'description' => array(
            'type' => 'textarea',
            'label' => __('Popis', 'saw-visitors'),
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
            'help' => __('Volitelný popis typu účtu', 'saw-visitors'),
        ),
        'price' => array(
            'type' => 'number',
            'label' => __('Cena (Kč/měsíc)', 'saw-visitors'),
            'required' => false,
            'default' => 0.00,
            'sanitize' => 'floatval',
            'help' => __('Měsíční cena v Kč (0 = zdarma)', 'saw-visitors'),
        ),
        'color' => array(
            'type' => 'color',
            'label' => __('Barva', 'saw-visitors'),
            'required' => false,
            'default' => '#6b7280',
            'sanitize' => 'sanitize_hex_color',
            'help' => __('Barva pro vizuální označení typu účtu', 'saw-visitors'),
        ),
        'features' => array(
            'type' => 'textarea',
            'label' => __('Seznam funkcí', 'saw-visitors'),
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
            'help' => __('Každá funkce na nový řádek', 'saw-visitors'),
        ),
        'sort_order' => array(
            'type' => 'number',
            'label' => __('Pořadí řazení', 'saw-visitors'),
            'required' => false,
            'default' => 0,
            'sanitize' => 'intval',
            'help' => __('Nižší číslo = vyšší v seznamu', 'saw-visitors'),
        ),
        'is_active' => array(
            'type' => 'checkbox',
            'label' => __('Aktivní typ účtu', 'saw-visitors'),
            'required' => false,
            'default' => 1,
            'help' => __('Pouze aktivní typy jsou dostupné pro výběr', 'saw-visitors'),
        ),
    ),
    
    // List view configuration
    'list_config' => array(
        'columns' => array('color', 'display_name', 'name', 'price', 'is_active'),
        'searchable' => array('name', 'display_name', 'description'),
        'sortable' => array('name', 'display_name', 'price', 'sort_order'),
        'filters' => array(
            'is_active' => true,
        ),
        'per_page' => 20,
        'enable_detail_modal' => true,
    ),
    
    // Cache configuration
    'cache' => array(
        'enabled' => true,
        'ttl' => 300,
        'invalidate_on' => array('save', 'delete'),
    ),
);