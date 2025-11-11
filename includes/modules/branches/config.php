<?php
/**
 * Branches Module Configuration
 *
 * REFACTORED to new config-driven architecture.
 * UPDATED to match 'schema-branches.php' column names.
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches
 * @since       9.0.0 (Refactored)
 * @version     12.0.1 (Schema-Fix)
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
    // FIELD DEFINITIONS (Matches schema-branches.php)
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
            'hidden' => true, // Managed by controller
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
        'code' => array( // formerly 'branch_code'
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
        'image_url' => array( // formerly 'thumbnail_url'
            'type' => 'file',
            'label' => 'Obrázek (Logo)',
            'required' => false,
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
        'street' => array( // formerly 'address_street'
            'type' => 'text',
            'label' => 'Ulice a č.p.',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'city' => array( // formerly 'address_city'
            'type' => 'text',
            'label' => 'Město',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'postal_code' => array( // formerly 'address_zip'
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

        // GPS
        'latitude' => array( // formerly 'gps_lat'
            'type' => 'text',
            'label' => 'GPS Lat',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'longitude' => array( // formerly 'gps_lng'
            'type' => 'text',
            'label' => 'GPS Lng',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),

        // Data
        'opening_hours' => array(
            'type' => 'textarea',
            'label' => 'Otevírací doba (JSON)',
            'required' => false,
            'hidden' => true,
            'sanitize' => 'sanitize_text_field', // Special sanitize in controller
        ),
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
        'search_fields' => array('name', 'code', 'city', 'email'),
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
    // CACHE CONFIGURATION
    // ============================================
    'cache' => array(
        'enabled' => true,
        'ttl' => 300, // 5 minutes
        'invalidate_on' => array('save', 'delete'),
    ),
);