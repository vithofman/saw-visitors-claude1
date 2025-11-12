<?php
/**
 * Departments Module Configuration
 * 
 * Defines all settings, fields, capabilities, and behavior for the Departments module.
 * Departments represent organizational units within branches (e.g., Sales, IT, HR).
 * Each department belongs to a specific branch and has its own training version.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Departments
 * @since       1.0.0
 * @author      SAW Visitors Dev Team
 * @version     2.0.0 - FIXED: Changed capabilities to manage_options
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(
    // ============================================
    // ENTITY DEFINITION
    // ============================================
    'entity' => 'departments',
    'table' => 'saw_departments',
    'singular' => 'Oddělení',
    'plural' => 'Oddělení',
    'route' => 'departments',
    'icon' => '🏭',
    'has_customer_isolation' => true,
    'edit_url' => 'departments/{id}/edit',
    
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
    // FIELD DEFINITIONS
    // ============================================
    'fields' => array(
        // Customer ID (hidden, auto-set from context)
        'customer_id' => array(
            'type' => 'number',
            'label' => 'Zákazník ID',
            'required' => true,
            'hidden' => true,
            'sanitize' => 'absint',
        ),
        
        // Branch selection
        'branch_id' => array(
            'type' => 'select',
            'label' => 'Pobočka',
            'required' => true,
            'sanitize' => 'absint',
            'help' => 'Pobočka ke které oddělení patří',
        ),
        
        // Department number (optional internal identifier)
        'department_number' => array(
            'type' => 'text',
            'label' => 'Číslo oddělení',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
            'help' => 'Interní číslo oddělení (volitelné)',
        ),
        
        // Department name
        'name' => array(
            'type' => 'text',
            'label' => 'Název oddělení',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
            'help' => 'Název oddělení',
        ),
        
        // Description
        'description' => array(
            'type' => 'textarea',
            'label' => 'Popis',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
            'help' => 'Volitelný popis oddělení',
        ),
        
        // Training version
        'training_version' => array(
            'type' => 'number',
            'label' => 'Verze školení',
            'required' => false,
            'default' => 1,
            'min' => 1,
            'max' => 999,
            'sanitize' => 'intval',
            'help' => 'Aktuální verze školení pro oddělení',
        ),
        
        // Active status
        'is_active' => array(
            'type' => 'boolean',
            'label' => 'Aktivní',
            'required' => false,
            'default' => 1,
            'sanitize' => 'absint',
            'help' => 'Pouze aktivní oddělení jsou dostupná pro výběr',
        ),
    ),
    
    // ============================================
    // LIST VIEW CONFIGURATION
    // ============================================
    'list_config' => array(
        'columns' => array('department_number', 'name', 'branch_id', 'training_version', 'is_active'),
        'searchable' => array('name', 'department_number', 'description'),
        'sortable' => array('name', 'department_number', 'training_version', 'created_at'),
        'filters' => array(
            'is_active' => true,
            'branch_id' => true,
        ),
        'per_page' => 20,
        'enable_detail_modal' => true,
    ),
    
    // ============================================
    // CACHING CONFIGURATION
    // ============================================
    'cache' => array(
        'enabled' => true,
        'ttl' => 300, // 5 minutes
        'invalidate_on' => array('save', 'delete'),
    ),
);