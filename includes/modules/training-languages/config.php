<?php
if (!defined('ABSPATH')) {
    exit;
}

return [
    'entity' => 'training_languages',
    'table' => 'saw_training_languages',
    'singular' => 'Jazyk',
    'plural' => 'Jazyky školení',
    'route' => 'admin/training-languages',
    'icon' => '🌐',
    'filter_by_customer' => true,
    
    'capabilities' => [
        'list' => 'manage_options',
        'view' => 'manage_options',
        'create' => 'manage_options',
        'edit' => 'manage_options',
        'delete' => 'manage_options',
    ],
    
    'fields' => [
        'language_code' => [
            'type' => 'select',
            'label' => 'Kód jazyka',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
            'help' => 'ISO 639-1 kód (cs, en, sk, de, pl, uk, ru)',
            'options' => [
                'cs' => 'cs - Čeština',
                'en' => 'en - English',
                'sk' => 'sk - Slovenčina',
                'de' => 'de - Deutsch',
                'pl' => 'pl - Polski',
                'uk' => 'uk - Українська',
                'ru' => 'ru - Русский',
            ],
        ],
        
        'language_name' => [
            'type' => 'text',
            'label' => 'Název jazyka',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
            'help' => 'Plný název (např. "Čeština")',
        ],
        
        'flag_emoji' => [
            'type' => 'text',
            'label' => 'Vlajka (emoji)',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
            'help' => 'Emoji vlajky (např. 🇨🇿)',
            'maxlength' => 10,
        ],
    ],
    
    'list_config' => [
        'columns' => ['flag_emoji', 'language_name', 'language_code', 'branches_count', 'created_at'],
        'searchable' => ['language_name', 'language_code'],
        'sortable' => ['language_name', 'language_code', 'created_at'],
        'filters' => [
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
