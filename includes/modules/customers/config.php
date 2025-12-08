<?php
/**
 * Customers Module Configuration
 *
 * Module configuration for the Customers module.
 * Defines entity structure, fields, capabilities, list display, and caching.
 *
 * @package SAW_Visitors
 * @version 2.1.0 - FIXED: UTF-8 encoding
 * @since   4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// TRANSLATIONS SETUP
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}

$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'customers') 
    : array();

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// CONFIGURATION
// ============================================
return array(
    // ============================================
    // ENTITY DEFINITION
    // ============================================
    'entity' => 'customers',
    'table' => 'saw_customers',
    'singular' => $tr('singular', 'Zákazník'),
    'plural' => $tr('plural', 'Zákazníci'),
    'route' => 'customers',
    'icon' => '🏢',
    'edit_url' => 'customers/{id}/edit',
    
    // ============================================
    // CAPABILITIES
    // WordPress capability requirements for each action
    // ============================================
    'capabilities' => array(
        'list' => 'manage_options',
        'view' => 'manage_options',
        'create' => 'manage_options',
        'edit' => 'manage_options',
        'delete' => 'manage_options',
    ),
    
    // ============================================
    // STATUS OPTIONS (for dropdowns)
    // ============================================
    'status_options' => array(
        'potential' => $tr('status_potential', 'Potenciální'),
        'active' => $tr('status_active', 'Aktivní'),
        'inactive' => $tr('status_inactive', 'Neaktivní'),
    ),
    
    // ============================================
    // LANGUAGE OPTIONS (for dropdowns)
    // ============================================
    'language_options' => array(
        'cs' => $tr('lang_cs', '🇨🇿 Čeština'),
        'en' => $tr('lang_en', '🇬🇧 English'),
        'de' => $tr('lang_de', '🇩🇪 Deutsch'),
        'sk' => $tr('lang_sk', '🇸🇰 Slovenčina'),
    ),
    
    // ============================================
    // FIELD DEFINITIONS
    // Complete field structure with validation and sanitization
    // ============================================
    'fields' => array(
        // Basic Information
        'name' => array(
            'type' => 'text',
            'label' => $tr('field_name', 'Název'),
            'required' => true,
            'sanitize' => 'sanitize_text_field',
        ),
        'ico' => array(
            'type' => 'text',
            'label' => $tr('field_ico', 'IČO'),
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'dic' => array(
            'type' => 'text',
            'label' => $tr('field_dic', 'DIČ'),
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        
        // Branding
        'logo_url' => array(
            'type' => 'file',
            'label' => $tr('field_logo', 'Logo'),
            'required' => false,
        ),
        
        // Account Status
        'status' => array(
            'type' => 'select',
            'label' => $tr('field_status', 'Status'),
            'required' => true,
            'default' => 'potential',
            'sanitize' => 'sanitize_text_field',
        ),
        'account_type_id' => array(
            'type' => 'select',
            'label' => $tr('field_account_type', 'Typ účtu'),
            'required' => false,
            'sanitize' => 'absint',
        ),
        'subscription_type' => array(
            'type' => 'select',
            'label' => $tr('field_subscription_type', 'Typ předplatného'),
            'required' => false,
            'default' => 'free',
            'sanitize' => 'sanitize_text_field',
            'deprecated' => true,
        ),
        
        // Contact Information
        'contact_email' => array(
            'type' => 'email',
            'label' => $tr('field_contact_email', 'Kontaktní email'),
            'required' => false,
            'sanitize' => 'sanitize_email',
        ),
        'contact_person' => array(
            'type' => 'text',
            'label' => $tr('field_contact_person', 'Kontaktní osoba'),
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'contact_phone' => array(
            'type' => 'text',
            'label' => $tr('field_contact_phone', 'Telefon'),
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        
        // Physical Address
        'address_street' => array(
            'type' => 'text',
            'label' => $tr('field_address_street', 'Ulice'),
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'address_number' => array(
            'type' => 'text',
            'label' => $tr('field_address_number', 'Číslo popisné'),
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'address_city' => array(
            'type' => 'text',
            'label' => $tr('field_address_city', 'Město'),
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'address_zip' => array(
            'type' => 'text',
            'label' => $tr('field_address_zip', 'PSČ'),
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        
        // Billing Address
        'billing_address_street' => array(
            'type' => 'text',
            'label' => $tr('field_billing_street', 'Fakturační ulice'),
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'billing_address_number' => array(
            'type' => 'text',
            'label' => $tr('field_billing_number', 'Fakturační číslo'),
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'billing_address_city' => array(
            'type' => 'text',
            'label' => $tr('field_billing_city', 'Fakturační město'),
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'billing_address_zip' => array(
            'type' => 'text',
            'label' => $tr('field_billing_zip', 'Fakturační PSČ'),
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        
        // Settings
        'admin_language_default' => array(
            'type' => 'select',
            'label' => $tr('field_default_language', 'Výchozí jazyk'),
            'required' => false,
            'default' => 'cs',
            'sanitize' => 'sanitize_text_field',
        ),
        
        // Additional Information
        'notes' => array(
            'type' => 'textarea',
            'label' => $tr('field_notes', 'Poznámky'),
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
        ),
        
        // Timestamps (hidden from forms)
        'created_at' => array(
            'type' => 'date',
            'label' => $tr('field_created_at', 'Vytvořeno'),
            'required' => false,
            'hidden' => false,
        ),
    ),
    
    // ============================================
    // LIST CONFIGURATION
    // Columns are automatically generated from fields
    // Only module-specific filters and settings here
    // ============================================
    'list_config' => array(
        'filters' => array(
            'status' => true,
            'account_type_id' => true,
        ),
        'per_page' => 20,
        'enable_detail_modal' => true,
    ),
    
    // ============================================
    // TABS CONFIGURATION
    // Tabs by account_type_id - dynamically loaded from DB
    // ============================================
    'tabs' => array(
        'enabled' => true,
        'tab_param' => 'account_type_id',
        'default_tab' => 'all',
        'dynamic' => true,
        'dynamic_source' => array(
            'table' => 'saw_account_types',
            'id_field' => 'id',
            'label_field' => 'display_name',
            'color_field' => 'color',
            'order' => 'sort_order ASC, display_name ASC',
            'where' => 'is_active = 1',
        ),
        'tabs' => array(
            'all' => array(
                'label' => $tr('tab_all', 'Všichni'),
                'filter_value' => null,
                'icon' => '📋',
            ),
        ),
    ),
    
    // ============================================
    // INFINITE SCROLL CONFIGURATION
    // ============================================
    'infinite_scroll' => array(
        'enabled' => true,
        'initial_load' => 100,
        'per_page' => 50,
        'threshold' => 0.6,
    ),
    
    // ============================================
    // LOOKUP TABLES
    // Auto-loaded for forms, cached automatically
    // ============================================
    'lookup_tables' => array(
        'account_types' => array(
            'table' => 'saw_account_types',
            'fields' => array('id', 'name', 'display_name', 'color', 'price'),
            'where' => 'is_active = 1',
            'order' => 'name ASC',
            'display_field' => 'display_name',
            'cache_ttl' => 3600,
        ),
    ),
    
    // ============================================
    // CACHE CONFIGURATION
    // Caching strategy for performance optimization
    // ============================================
    'cache' => array(
        'enabled' => true,
        'ttl' => 300,
        'invalidate_on' => array('save', 'delete'),
    ),
);