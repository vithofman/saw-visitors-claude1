<?php
/**
 * Customers Module Config
 * 
 * @package SAW_Visitors
 */

if (!defined('ABSPATH')) {
    exit;
}

return [
    'entity' => 'customers',
    'table' => 'saw_customers',
    'singular' => 'Zákazník',
    'plural' => 'Zákazníci',
    'route' => 'admin/settings/customers',
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
            'sanitize' => 'sanitize_text_field',
        ],
        'ico' => [
            'type' => 'text',
            'label' => 'IČO',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
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
            'default' => 'potential',
            'sanitize' => 'sanitize_text_field',
        ],
        'account_type_id' => [
            'type' => 'select',
            'label' => 'Typ účtu',
            'required' => false,
            'sanitize' => 'absint',
        ],
        'subscription_type' => [
            'type' => 'select',
            'label' => 'Typ předplatného',
            'required' => false,
            'default' => 'free',
            'sanitize' => 'sanitize_text_field',
        ],
        'contact_email' => [
            'type' => 'email',
            'label' => 'Kontaktní email',
            'required' => false,
            'sanitize' => 'sanitize_email',
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
        'admin_language_default' => [
            'type' => 'select',
            'label' => 'Výchozí jazyk',
            'required' => false,
            'default' => 'cs',
            'sanitize' => 'sanitize_text_field',
        ],
        'notes' => [
            'type' => 'textarea',
            'label' => 'Poznámky',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
        ],
    ],
    
    'list_config' => [
        'columns' => ['name', 'ico', 'status', 'account_type', 'created_at'],
        'searchable' => ['name', 'ico', 'contact_email'],
        'sortable' => ['name', 'ico', 'created_at'],
        'filters' => [
            'status' => true,
            'account_type' => true,
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