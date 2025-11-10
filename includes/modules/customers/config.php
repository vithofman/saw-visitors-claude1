<?php
/**
 * Customers Module Configuration
 *
 * Module configuration for the Customers module.
 * Defines entity structure, fields, capabilities, list display, and caching.
 *
 * Configuration Structure:
 * - Entity: Basic module identification (name, table, labels, route)
 * - Capabilities: WordPress capability checks for each action
 * - Fields: Complete field definitions with types, labels, validation
 * - List Config: Filters and pagination (columns auto-generated from fields)
 * - Cache: Caching strategy and TTL settings
 *
 * Field Types Supported:
 * - text: Single-line text input
 * - email: Email input with validation
 * - textarea: Multi-line text input
 * - select: Dropdown selection
 * - file: File upload (logo)
 *
 * @package SAW_Visitors
 * @version 11.0.0 - REFACTORED: Removed redundant list_config (auto-generated)
 * @since   4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(
    // ============================================
    // ENTITY DEFINITION
    // ============================================
    'entity' => 'customers',
    'table' => 'saw_customers',
    'singular' => 'Zákazník',
    'plural' => 'Zákazníci',
    'route' => 'admin/customers',
    'icon' => '🏢',
'edit_url' => 'admin/customers/{id}/edit',
    
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
    // FIELD DEFINITIONS
    // Complete field structure with validation and sanitization
    // ============================================
    'fields' => array(
        // Basic Information
        'name' => array(
            'type' => 'text',
            'label' => 'Název',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
        ),
        'ico' => array(
            'type' => 'text',
            'label' => 'IČO',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'dic' => array(
            'type' => 'text',
            'label' => 'DIČ',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        
        // Branding
        'logo_url' => array(
            'type' => 'file',
            'label' => 'Logo',
            'required' => false,
        ),
        
        // Account Status
        'status' => array(
            'type' => 'select',
            'label' => 'Status',
            'required' => true,
            'default' => 'potential',
            'sanitize' => 'sanitize_text_field',
        ),
        'account_type_id' => array(
            'type' => 'select',
            'label' => 'Typ účtu',
            'required' => false,
            'sanitize' => 'absint',
        ),
        'subscription_type' => array(
            'type' => 'select',
            'label' => 'Typ předplatného',
            'required' => false,
            'default' => 'free',
            'sanitize' => 'sanitize_text_field',
            'deprecated' => true,
        ),
        
        // Contact Information
        'contact_email' => array(
            'type' => 'email',
            'label' => 'Kontaktní email',
            'required' => false,
            'sanitize' => 'sanitize_email',
        ),
        'contact_person' => array(
            'type' => 'text',
            'label' => 'Kontaktní osoba',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'contact_phone' => array(
            'type' => 'text',
            'label' => 'Telefon',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        
        // Physical Address
        'address_street' => array(
            'type' => 'text',
            'label' => 'Ulice',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'address_number' => array(
            'type' => 'text',
            'label' => 'Číslo popisné',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'address_city' => array(
            'type' => 'text',
            'label' => 'Město',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'address_zip' => array(
            'type' => 'text',
            'label' => 'PSČ',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        
        // Billing Address
        'billing_address_street' => array(
            'type' => 'text',
            'label' => 'Fakturační ulice',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'billing_address_number' => array(
            'type' => 'text',
            'label' => 'Fakturační číslo',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'billing_address_city' => array(
            'type' => 'text',
            'label' => 'Fakturační město',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'billing_address_zip' => array(
            'type' => 'text',
            'label' => 'Fakturační PSČ',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        
        // Settings
        'admin_language_default' => array(
            'type' => 'select',
            'label' => 'Výchozí jazyk',
            'required' => false,
            'default' => 'cs',
            'sanitize' => 'sanitize_text_field',
        ),
        
        // Additional Information
        'notes' => array(
            'type' => 'textarea',
            'label' => 'Poznámky',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
        ),
        
        // Timestamps (hidden from forms)
        'created_at' => array(
            'type' => 'date',
            'label' => 'Vytvořeno',
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
        // Columns automatically generated from 'fields'
        // To override, uncomment and specify:
        // 'columns' => array('logo_url', 'name', 'ico', 'status', 'created_at'),
        
        'filters' => array(
            'status' => true,
        ),
        'per_page' => 20,
        'enable_detail_modal' => true,
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