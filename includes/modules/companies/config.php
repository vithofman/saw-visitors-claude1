<?php
/**
 * Companies Module Configuration
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Companies
 * @version     1.1.0
 * 
 * POZNÁMKA: Překlady jsou řešeny v list-template.php
 * Config obsahuje pouze české fallback texty.
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(
    'entity' => 'companies',
    'table' => 'saw_companies',
    'singular' => 'Firma',
    'plural' => 'Firmy',
    'route' => 'companies',
    'icon' => '🏢',
    'has_customer_isolation' => true,
    'edit_url' => 'companies/{id}/edit',
    
    'capabilities' => array(
        'list' => 'manage_options',
        'view' => 'manage_options',
        'create' => 'manage_options',
        'edit' => 'manage_options',
        'delete' => 'manage_options',
    ),
    
    'custom_ajax_actions' => array(
        'saw_inline_create_companies' => 'ajax_inline_create',
    ),
    
    'fields' => array(
        'customer_id' => array(
            'type' => 'number',
            'label' => 'Zákazník ID',
            'required' => true,
            'hidden' => true,
            'sanitize' => 'absint',
        ),
        
        'branch_id' => array(
            'type' => 'select',
            'label' => 'Pobočka',
            'required' => true,
            'sanitize' => 'absint',
            'help' => 'Pobočka ke které firma patří',
            'hidden' => true,
        ),
        
        'name' => array(
            'type' => 'text',
            'label' => 'Název firmy',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
            'help' => 'Oficiální název společnosti',
        ),
        
        'ico' => array(
            'type' => 'text',
            'label' => 'IČO',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
            'help' => 'Identifikační číslo organizace',
            'maxlength' => 20,
        ),
        
        'street' => array(
            'type' => 'text',
            'label' => 'Ulice a číslo popisné',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
            'help' => 'Adresa sídla firmy',
        ),
        
        'city' => array(
            'type' => 'text',
            'label' => 'Město',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        
        'zip' => array(
            'type' => 'text',
            'label' => 'PSČ',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
            'maxlength' => 20,
        ),
        
        'country' => array(
            'type' => 'text',
            'label' => 'Země',
            'required' => false,
            'default' => 'Česká republika',
            'sanitize' => 'sanitize_text_field',
        ),
        
        'email' => array(
            'type' => 'email',
            'label' => 'Email',
            'required' => false,
            'sanitize' => 'sanitize_email',
            'help' => 'Kontaktní email firmy',
        ),
        
        'phone' => array(
            'type' => 'text',
            'label' => 'Telefon',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
            'maxlength' => 50,
        ),
        
        'website' => array(
            'type' => 'url',
            'label' => 'Web',
            'required' => false,
            'sanitize' => 'esc_url_raw',
            'help' => 'Webová stránka firmy',
        ),
        
        'is_archived' => array(
            'type' => 'boolean',
            'label' => 'Archivováno',
            'required' => false,
            'default' => 0,
            'sanitize' => 'absint',
            'help' => 'Archivované firmy nejsou dostupné pro výběr',
        ),
    ),
    
    'list_config' => array(
        'columns' => array('name', 'ico', 'street', 'city', 'zip', 'email', 'phone', 'website', 'is_archived'),
        'searchable' => array('name', 'ico', 'street', 'city', 'email', 'phone'),
        'sortable' => array('name', 'ico', 'city', 'created_at'),
        'filters' => array(
            'is_archived' => true,
        ),
        'per_page' => 20,
        'enable_detail_modal' => true,
    ),
    
    // TABS - labels budou přepsány v list-template.php
    'tabs' => array(
        'enabled' => true,
        'tab_param' => 'is_archived',
        'tabs' => array(
            'all' => array(
                'label' => 'Všechny',
                'icon' => '📋',
                'filter_value' => null,
                'count_query' => true,
            ),
            'active' => array(
                'label' => 'Aktivní',
                'icon' => '✅',
                'filter_value' => 0,
                'count_query' => true,
            ),
            'archived' => array(
                'label' => 'Archivované',
                'icon' => '📦',
                'filter_value' => 1,
                'count_query' => true,
            ),
        ),
        'default_tab' => 'all',
    ),
    
    'cache' => array(
        'enabled' => true,
        'ttl' => 300,
        'invalidate_on' => array('save', 'delete'),
    ),
);