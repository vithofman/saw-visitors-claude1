<?php
/**
 * OOPP Module Configuration
 * 
 * Osobní ochranné pracovní prostředky - konfigurace modulu.
 * OOPP jsou globální pro zákazníka (customer_id), volitelně omezené na pobočky.
 *
 * @package SAW_Visitors
 * @version 1.2.0 - FIXED: Tabs configuration matching companies module
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(
    // ============================================
    // ENTITY DEFINITION
    // ============================================
    'entity' => 'oopp',
    'table' => 'saw_oopp',
    'singular' => 'OOPP',
    'plural' => 'Osobní ochranné pracovní prostředky',
    'route' => 'oopp',
    'icon' => '🦺',
    'edit_url' => 'oopp/{id}/edit',
    
    // OOPP jsou globální pro zákazníka, NE pro pobočku
    'has_customer_isolation' => true,
    'has_branch_isolation' => false,
    
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
    // TABS CONFIGURATION - MATCHING COMPANIES FORMAT
    // ============================================
    'tabs' => array(
        'enabled' => true,
        'tab_param' => 'is_active',
        'default_tab' => 'all',
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
                'filter_value' => 1,
                'count_query' => true,
            ),
            'inactive' => array(
                'label' => 'Neaktivní',
                'icon' => '❌',
                'filter_value' => 0,
                'count_query' => true,
            ),
        ),
    ),
    
    // ============================================
    // FIELD DEFINITIONS
    // ============================================
    'fields' => array(
        'customer_id' => array(
            'type' => 'number',
            'label' => 'Zákazník ID',
            'required' => true,
            'hidden' => true,
            'sanitize' => 'absint',
        ),
        'group_id' => array(
            'type' => 'select',
            'label' => 'Skupina OOPP',
            'required' => true,
            'sanitize' => 'absint',
            'lookup' => 'oopp_groups',
            'placeholder' => 'Vyberte skupinu...',
        ),
        'name' => array(
            'type' => 'text',
            'label' => 'Název',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
            'placeholder' => 'např. Ochranné brýle proti UV záření',
            'max_length' => 255,
        ),
        'image_path' => array(
            'type' => 'file',
            'label' => 'Fotografie',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
            'accept' => 'image/jpeg,image/png,image/gif,image/webp',
            'max_size' => 2097152,
            'context' => 'oopp',
        ),
        'standards' => array(
            'type' => 'textarea',
            'label' => 'Související předpisy / normy',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
            'placeholder' => 'např. ČSN EN 166, EN 172...',
            'rows' => 3,
        ),
        'risk_description' => array(
            'type' => 'textarea',
            'label' => 'Popis rizik, proti kterým OOPP chrání',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
            'placeholder' => 'Popište rizika, před kterými tento prostředek chrání...',
            'rows' => 4,
        ),
        'protective_properties' => array(
            'type' => 'textarea',
            'label' => 'Ochranné vlastnosti',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
            'placeholder' => 'Popište ochranné vlastnosti prostředku...',
            'rows' => 4,
        ),
        'usage_instructions' => array(
            'type' => 'textarea',
            'label' => 'Pokyny pro použití',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
            'placeholder' => 'Jak správně používat tento prostředek...',
            'rows' => 4,
        ),
        'maintenance_instructions' => array(
            'type' => 'textarea',
            'label' => 'Pokyny pro údržbu',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
            'placeholder' => 'Jak správně udržovat a čistit prostředek...',
            'rows' => 3,
        ),
        'storage_instructions' => array(
            'type' => 'textarea',
            'label' => 'Pokyny pro skladování',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
            'placeholder' => 'Jak správně skladovat prostředek...',
            'rows' => 3,
        ),
        'is_active' => array(
            'type' => 'checkbox',
            'label' => 'Aktivní',
            'required' => false,
            'sanitize' => 'absint',
            'default' => 1,
        ),
        'display_order' => array(
            'type' => 'number',
            'label' => 'Pořadí zobrazení',
            'required' => false,
            'sanitize' => 'absint',
            'default' => 0,
        ),
    ),
    
    // ============================================
    // LOOKUP TABLES (auto-loaded with caching)
    // ============================================
    'lookups' => array(
        'oopp_groups' => array(
            'table' => 'saw_oopp_groups',
            'id_field' => 'id',
            'name_field' => 'name',
            'code_field' => 'code',
            'order_by' => 'display_order ASC',
            'cache_ttl' => 3600,
            'format' => '{code}. {name}',
        ),
    ),
    
    // ============================================
    // LIST CONFIGURATION
    // ============================================
    'list_config' => array(
        'default_orderby' => 'display_order',
        'default_order' => 'ASC',
        'per_page' => 25,
        'searchable' => array('name', 'standards'),
        'filters' => array(
            'group_id' => true,
            'is_active' => true,
        ),
        'enable_detail_modal' => true,
    ),
    
    // ============================================
    // CACHE CONFIGURATION
    // ============================================
    'cache' => array(
        'enabled' => true,
        'ttl' => 600,
        'group' => 'oopp',
    ),
    
    // ============================================
    // CUSTOM AJAX ACTIONS
    // ============================================
    'custom_ajax_actions' => array(
        'saw_get_oopp_groups' => 'ajax_get_oopp_groups',
        'saw_save_oopp_branches' => 'ajax_save_branches',
        'saw_save_oopp_departments' => 'ajax_save_departments',
        'saw_get_oopp_for_department' => 'ajax_get_for_department',
    ),
);