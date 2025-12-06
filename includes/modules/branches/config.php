<?php
/**
 * Branches Module Configuration
 *
 * MODERNIZOVANÁ VERZE s tabs, překlady a infinite scroll podporou
 * Struktura shodná s companies modulem
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches
 * @version     15.0.0
 * 
 * POZNÁMKA: Překlady jsou řešeny v list-template.php
 * Config obsahuje pouze české fallback texty.
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(
    // ============================================
    // ENTITY DEFINITION
    // ============================================
    'entity' => 'branches',
    'table' => 'saw_branches',
    'singular' => 'Pobočka',
    'plural' => 'Pobočky',
    'route' => 'branches',
    'icon' => '🏢',
    'has_customer_isolation' => true,
    'edit_url' => 'branches/{id}/edit',

    // ============================================
    // CAPABILITIES
    // ============================================
    'capabilities' => array(
        'list' => 'manage_options',
        'view' => 'manage_options',
        'create' => 'manage_options',
        'edit' => 'manage_options',
        'delete' => 'manage_options',
    ),

    // ============================================
    // FIELD DEFINITIONS
    // ============================================
    'fields' => array(
        // Core Fields
        'name' => array(
            'type' => 'text',
            'label' => 'Název pobočky',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
        ),
        'customer_id' => array(
            'type' => 'number',
            'label' => 'Zákazník ID',
            'required' => true,
            'hidden' => true,
            'sanitize' => 'absint',
        ),
        'is_headquarters' => array(
            'type' => 'boolean',
            'label' => 'Sídlo firmy',
            'default' => 0,
            'sanitize' => 'absint',
        ),
        'is_active' => array(
            'type' => 'boolean',
            'label' => 'Aktivní',
            'default' => 1,
            'sanitize' => 'absint',
        ),
        'code' => array(
            'type' => 'text',
            'label' => 'Kód pobočky',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'sort_order' => array(
            'type' => 'number',
            'label' => 'Pořadí',
            'default' => 10,
            'sanitize' => 'absint',
        ),

        // Branding
        'image_url' => array(
            'type' => 'file',
            'label' => 'Obrázek (Logo)',
            'required' => false,
        ),
        'image_thumbnail' => array(
            'type' => 'text',
            'label' => 'Náhled',
            'required' => false,
            'hidden' => true,
        ),

        // Contact
        'phone' => array(
            'type' => 'text',
            'label' => 'Telefon',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'email' => array(
            'type' => 'email',
            'label' => 'Email',
            'required' => false,
            'sanitize' => 'sanitize_email',
        ),

        // Address
        'street' => array(
            'type' => 'text',
            'label' => 'Ulice a č.p.',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'city' => array(
            'type' => 'text',
            'label' => 'Město',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'postal_code' => array(
            'type' => 'text',
            'label' => 'PSČ',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'country' => array(
            'type' => 'text',
            'label' => 'Země (kód)',
            'default' => 'CZ',
            'sanitize' => 'sanitize_text_field',
        ),

        // Data
        'notes' => array(
            'type' => 'textarea',
            'label' => 'Poznámky',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
        ),
        'description' => array(
            'type' => 'textarea',
            'label' => 'Popis',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
        ),
        'metadata' => array(
            'type' => 'textarea',
            'label' => 'Metadata (JSON)',
            'required' => false,
            'hidden' => true,
            'sanitize' => 'sanitize_text_field',
        ),

        // Timestamps
        'created_at' => array(
            'type' => 'date',
            'label' => 'Vytvořeno',
            'required' => false,
        ),
        'updated_at' => array(
            'type' => 'date',
            'label' => 'Aktualizováno',
            'required' => false,
        ),
    ),

    // ============================================
    // LIST CONFIGURATION
    // ============================================
    'list_config' => array(
        'columns' => array('image_url', 'name', 'code', 'is_headquarters', 'city', 'phone', 'is_active'),
        'searchable' => array('name', 'code', 'city', 'email', 'phone'),
        'sortable' => array('name', 'code', 'city', 'sort_order', 'is_headquarters'),
        'filters' => array(
            'is_active' => true,
        ),
        'per_page' => 20,
        'enable_detail_modal' => true,
        'default_sort' => array(
            'orderby' => 'is_headquarters',
            'order' => 'DESC',
            'secondary_orderby' => 'name',
            'secondary_order' => 'ASC',
        ),
    ),

    // ============================================
    // TABS CONFIGURATION
    // Kombinované tabs: Sídla / Ostatní / Neaktivní
    // Labels budou přepsány v list-template.php pro překlady
    // ============================================
    'tabs' => array(
        'enabled' => true,
        'tab_param' => 'tab',
        'tabs' => array(
            'all' => array(
                'label' => 'Všechny',
                'icon' => '📋',
                'filter_value' => null,
                'count_query' => true,
            ),
            'headquarters' => array(
                'label' => 'Sídla',
                'icon' => '🏛️',
                'filter_value' => 'headquarters',
                'count_query' => true,
            ),
            'other' => array(
                'label' => 'Ostatní',
                'icon' => '🏢',
                'filter_value' => 'other',
                'count_query' => true,
            ),
            'inactive' => array(
                'label' => 'Neaktivní',
                'icon' => '⏸️',
                'filter_value' => 'inactive',
                'count_query' => true,
            ),
        ),
        'default_tab' => 'all',
    ),

    // ============================================
    // LOOKUP TABLES
    // ============================================
    'lookup_tables' => array(),

    // ============================================
    // CACHE CONFIGURATION
    // ============================================
    'cache' => array(
        'enabled' => true,
        'ttl' => 300,
        'invalidate_on' => array('save', 'delete'),
    ),
);