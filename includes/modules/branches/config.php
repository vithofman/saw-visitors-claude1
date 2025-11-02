<?php
if (!defined('ABSPATH')) {
    exit;
}

return [
    'entity' => 'branches',
    'table' => 'saw_branches',
    'singular' => 'Pobočka',
    'plural' => 'Pobočky',
    'route' => 'admin/branches',
    'icon' => '🏢',
    'filter_by_customer' => true,
    
    'capabilities' => [
        'list' => 'read',
        'view' => 'read',
        'create' => 'read',
        'edit' => 'read',
        'delete' => 'read',
    ],
    
    'fields' => [
        'name' => [
            'type' => 'text',
            'label' => 'Název pobočky',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
            'help' => 'Název pobočky (např. "Pobočka Praha")',
        ],
        
        'code' => [
            'type' => 'text',
            'label' => 'Kód pobočky',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
            'help' => 'Interní kód pro identifikaci (např. "PR001")',
        ],
        
        'street' => [
            'type' => 'text',
            'label' => 'Ulice a číslo',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
            'help' => 'Ulice a číslo popisné',
        ],
        
        'city' => [
            'type' => 'text',
            'label' => 'Město',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
            'help' => 'Město',
        ],
        
        'postal_code' => [
            'type' => 'text',
            'label' => 'PSČ',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
            'help' => 'Poštovní směrovací číslo',
        ],
        
        'country' => [
            'type' => 'select',
            'label' => 'Země',
            'required' => false,
            'default' => 'CZ',
            'sanitize' => 'sanitize_text_field',
            'options' => [
                'CZ' => 'Česká republika',
                'SK' => 'Slovensko',
                'DE' => 'Německo',
                'AT' => 'Rakousko',
                'PL' => 'Polsko',
            ],
            'help' => 'Země',
        ],
        
        'latitude' => [
            'type' => 'number',
            'label' => 'Zeměpisná šířka',
            'required' => false,
            'sanitize' => 'floatval',
            'step' => '0.00000001',
            'help' => 'GPS - zeměpisná šířka (např. 50.0755)',
        ],
        
        'longitude' => [
            'type' => 'number',
            'label' => 'Zeměpisná délka',
            'required' => false,
            'sanitize' => 'floatval',
            'step' => '0.00000001',
            'help' => 'GPS - zeměpisná délka (např. 14.4378)',
        ],
        
        'phone' => [
            'type' => 'text',
            'label' => 'Telefon',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
            'help' => 'Telefonní číslo pobočky',
        ],
        
        'email' => [
            'type' => 'email',
            'label' => 'Email',
            'required' => false,
            'sanitize' => 'sanitize_email',
            'help' => 'Emailová adresa pobočky',
        ],
        
        'image_url' => [
            'type' => 'file',
            'label' => 'Obrázek pobočky',
            'required' => false,
            'sanitize' => 'esc_url_raw',
            'help' => 'Hlavní obrázek pobočky',
        ],
        
        'image_thumbnail' => [
            'type' => 'hidden',
            'label' => 'Náhled obrázku',
            'required' => false,
            'sanitize' => 'esc_url_raw',
        ],
        
        'description' => [
            'type' => 'textarea',
            'label' => 'Popis',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
            'help' => 'Veřejný popis pobočky',
            'rows' => 5,
        ],
        
        'notes' => [
            'type' => 'textarea',
            'label' => 'Interní poznámky',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
            'help' => 'Interní poznámky (neviditelné pro návštěvníky)',
            'rows' => 3,
        ],
        
        'opening_hours' => [
            'type' => 'textarea',
            'label' => 'Provozní doba',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
            'help' => 'Každý den na nový řádek (např. "Po-Pá: 8:00-16:00")',
            'rows' => 7,
        ],
        
        'is_active' => [
            'type' => 'checkbox',
            'label' => 'Aktivní',
            'required' => false,
            'default' => 1,
            'sanitize' => 'absint',
            'help' => 'Pouze aktivní pobočky jsou viditelné',
        ],
        
        'is_headquarters' => [
            'type' => 'checkbox',
            'label' => 'Hlavní sídlo',
            'required' => false,
            'default' => 0,
            'sanitize' => 'absint',
            'help' => 'Je toto hlavní sídlo společnosti?',
        ],
        
        'sort_order' => [
            'type' => 'number',
            'label' => 'Pořadí řazení',
            'required' => false,
            'default' => 0,
            'sanitize' => 'absint',
            'help' => 'Nižší číslo = vyšší v seznamu',
        ],
    ],
    
    'list_config' => [
        'columns' => ['name', 'code', 'city', 'phone', 'is_headquarters', 'is_active', 'sort_order'],
        'searchable' => ['name', 'code', 'city', 'street'],
        'sortable' => ['name', 'code', 'city', 'sort_order', 'created_at'],
        'filters' => [
            'is_active' => true,
            'is_headquarters' => true,
            'customer_id' => true,
        ],
        'per_page' => 20,
        'enable_detail_modal' => true,
    ],
    
    'cache' => [
        'enabled' => true,
        'ttl' => 1800,
        'invalidate_on' => ['save', 'delete'],
    ],
];