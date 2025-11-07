<?php
/**
 * Branches Module Configuration
 * 
 * Defines module settings including entity properties, fields configuration,
 * capabilities, list view settings, and cache configuration.
 * 
 * @package SAW_Visitors
 * @since 2.0.0
 * @version 2.1.0 - Customer Filter Fix
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Branches module configuration array
 * 
 * @since 2.0.0
 * @return array Module configuration
 */
return array(
    'entity' => 'branches',
    'table' => 'saw_branches',
    'singular' => 'Pobočka',
    'plural' => 'Pobočky',
    'route' => 'admin/branches',
    'icon' => '🏢',
    
    'has_customer_isolation' => true,
    
    'capabilities' => array(
        'list' => 'saw_view_branches',
        'view' => 'saw_view_branches',
        'create' => 'saw_manage_branches',
        'edit' => 'saw_manage_branches',
        'delete' => 'saw_manage_branches',
    ),
    
    'fields' => array(
        'customer_id' => array(
            'type' => 'hidden',
            'required' => true,
        ),
        'name' => array(
            'type' => 'text',
            'label' => 'Název pobočky',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
            'help' => 'Název pobočky (např. "Pobočka Praha")',
        ),
        'code' => array(
            'type' => 'text',
            'label' => 'Kód pobočky',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
            'help' => 'Interní kód pro identifikaci (např. "PR001")',
        ),
        'street' => array(
            'type' => 'text',
            'label' => 'Ulice a číslo',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
            'help' => 'Ulice a číslo popisné',
        ),
        'city' => array(
            'type' => 'text',
            'label' => 'Město',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
            'help' => 'Město',
        ),
        'postal_code' => array(
            'type' => 'text',
            'label' => 'PSČ',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
            'help' => 'Poštovní směrovací číslo',
        ),
        'country' => array(
            'type' => 'select',
            'label' => 'Země',
            'required' => false,
            'default' => 'CZ',
            'sanitize' => 'sanitize_text_field',
            'options' => array(
                'CZ' => 'Česká republika',
                'SK' => 'Slovensko',
                'DE' => 'Německo',
                'AT' => 'Rakousko',
                'PL' => 'Polsko',
            ),
            'help' => 'Země',
        ),
        'latitude' => array(
            'type' => 'number',
            'label' => 'Zeměpisná šířka',
            'required' => false,
            'sanitize' => 'floatval',
            'step' => '0.00000001',
            'help' => 'GPS - zeměpisná šířka (např. 50.0755)',
        ),
        'longitude' => array(
            'type' => 'number',
            'label' => 'Zeměpisná délka',
            'required' => false,
            'sanitize' => 'floatval',
            'step' => '0.00000001',
            'help' => 'GPS - zeměpisná délka (např. 14.4378)',
        ),
        'phone' => array(
            'type' => 'text',
            'label' => 'Telefon',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
            'help' => 'Telefonní číslo pobočky',
        ),
        'email' => array(
            'type' => 'email',
            'label' => 'Email',
            'required' => false,
            'sanitize' => 'sanitize_email',
            'help' => 'Emailová adresa pobočky',
        ),
        'image_url' => array(
            'type' => 'file',
            'label' => 'Obrázek pobočky',
            'required' => false,
            'sanitize' => 'esc_url_raw',
            'help' => 'Hlavní obrázek pobočky',
        ),
        'image_thumbnail' => array(
            'type' => 'hidden',
            'label' => 'Náhled obrázku',
            'required' => false,
            'sanitize' => 'esc_url_raw',
        ),
        'description' => array(
            'type' => 'textarea',
            'label' => 'Popis',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
            'help' => 'Veřejný popis pobočky',
            'rows' => 5,
        ),
        'notes' => array(
            'type' => 'textarea',
            'label' => 'Interní poznámky',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
            'help' => 'Interní poznámky (neviditelné pro návštěvníky)',
            'rows' => 3,
        ),
        'opening_hours' => array(
            'type' => 'textarea',
            'label' => 'Provozní doba',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
            'help' => 'Každý den na nový řádek (např. "Po-Pá: 8:00-16:00")',
            'rows' => 7,
        ),
        'is_active' => array(
            'type' => 'checkbox',
            'label' => 'Aktivní',
            'required' => false,
            'default' => 1,
            'sanitize' => 'absint',
            'help' => 'Pouze aktivní pobočky jsou viditelné',
        ),
        'is_headquarters' => array(
            'type' => 'checkbox',
            'label' => 'Hlavní sídlo',
            'required' => false,
            'default' => 0,
            'sanitize' => 'absint',
            'help' => 'Je toto hlavní sídlo společnosti?',
        ),
        'sort_order' => array(
            'type' => 'number',
            'label' => 'Pořadí řazení',
            'required' => false,
            'default' => 0,
            'sanitize' => 'absint',
            'help' => 'Nižší číslo = vyšší v seznamu',
        ),
    ),
    
    'list_config' => array(
        'columns' => array('name', 'code', 'city', 'phone', 'is_headquarters', 'is_active', 'sort_order'),
        'searchable' => array('name', 'code', 'city', 'street'),
        'sortable' => array('name', 'code', 'city', 'sort_order', 'created_at'),
        'filters' => array(
            'customer_id' => true,
            'is_active' => true,
            'is_headquarters' => true,
        ),
        'per_page' => 20,
        'enable_detail_modal' => true,
    ),
    
    'cache' => array(
        'enabled' => true,
        'ttl' => 1800,
        'invalidate_on' => array('save', 'delete'),
    ),
);