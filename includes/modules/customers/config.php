<?php
/**
 * Customers Module Config
 * 
 * Definice entity Customers - vše co potřebuje Base Controller/Model.
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 * @since   4.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

return [
    'entity' => 'customers',
    'table' => 'saw_customers',
    'singular' => 'Zákazník',
    'plural' => 'Zákazníci',
    'route' => '/admin/settings/customers',
    'icon' => '🏢',
    
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
            'label' => 'Název',
            'required' => true,
            'validation' => ['min' => 3, 'max' => 255],
            'sanitize' => 'sanitize_text_field',
            'searchable' => true,
            'sortable' => true,
        ],
        'ico' => [
            'type' => 'text',
            'label' => 'IČO',
            'required' => false,
            'validation' => ['regex' => '/^\d{8}$/'],
            'sanitize' => 'sanitize_text_field',
            'searchable' => true,
            'sortable' => true,
        ],
        'dic' => [
            'type' => 'text',
            'label' => 'DIČ',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ],
        'logo_url' => [
            'type' => 'file',
            'label' => 'Logo',
            'required' => false,
            'mime_types' => ['image/jpeg', 'image/png', 'image/gif'],
            'max_size' => 2097152,
            'upload_dir' => 'saw-customers',
        ],
        'primary_color' => [
            'type' => 'color',
            'label' => 'Hlavní barva',
            'required' => false,
            'default' => '#1e40af',
            'sanitize' => 'sanitize_hex_color',
        ],
        'status' => [
            'type' => 'select',
            'label' => 'Status',
            'required' => true,
            'options' => [
                'potential' => 'Potenciální',
                'active' => 'Aktivní',
                'inactive' => 'Neaktivní',
            ],
            'default' => 'potential',
            'filterable' => true,
        ],
        'subscription_type' => [
            'type' => 'select',
            'label' => 'Typ předplatného',
            'required' => false,
            'options' => [
                'free' => 'Zdarma',
                'basic' => 'Basic',
                'pro' => 'Pro',
                'enterprise' => 'Enterprise',
            ],
            'default' => 'free',
            'filterable' => true,
        ],
        'contact_email' => [
            'type' => 'email',
            'label' => 'Kontaktní email',
            'required' => false,
            'sanitize' => 'sanitize_email',
            'searchable' => true,
        ],
        'contact_person' => [
            'type' => 'text',
            'label' => 'Kontaktní osoba',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ],
        'contact_phone' => [
            'type' => 'text',
            'label' => 'Telefon',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ],
        'address_street' => [
            'type' => 'text',
            'label' => 'Ulice',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ],
        'address_number' => [
            'type' => 'text',
            'label' => 'Číslo popisné',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ],
        'address_city' => [
            'type' => 'text',
            'label' => 'Město',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ],
        'address_zip' => [
            'type' => 'text',
            'label' => 'PSČ',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ],
        'billing_address_street' => [
            'type' => 'text',
            'label' => 'Fakturační ulice',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ],
        'billing_address_number' => [
            'type' => 'text',
            'label' => 'Fakturační číslo',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ],
        'billing_address_city' => [
            'type' => 'text',
            'label' => 'Fakturační město',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ],
        'billing_address_zip' => [
            'type' => 'text',
            'label' => 'Fakturační PSČ',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ],
        'admin_language' => [
            'type' => 'select',
            'label' => 'Jazyk administrace',
            'required' => false,
            'options' => [
                'cs' => 'Čeština',
                'en' => 'English',
                'de' => 'Deutsch',
            ],
            'default' => 'cs',
        ],
        'notes' => [
            'type' => 'textarea',
            'label' => 'Poznámky',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
        ],
    ],
    
    'list_config' => [
        'columns' => ['name', 'ico', 'status', 'subscription_type', 'primary_color', 'created_at'],
        'searchable' => ['name', 'ico', 'contact_email'],
        'sortable' => ['name', 'ico', 'created_at'],
        'filters' => [
            'status' => true,
            'subscription_type' => true,
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
