<?php
/**
 * Departments Module Configuration
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Departments
 * @version     5.0.0 - FINAL: Removed branch_id from columns
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(
    'entity' => 'departments',
    'table' => 'saw_departments',
    'singular' => 'Oddělení',
    'plural' => 'Oddělení',
    'route' => 'departments',
    'icon' => '🏭',
    'has_customer_isolation' => true,
    'edit_url' => 'departments/{id}/edit',
    
    'capabilities' => array(
        'list' => 'manage_options',
        'view' => 'manage_options',
        'create' => 'manage_options',
        'edit' => 'manage_options',
        'delete' => 'manage_options',
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
            'help' => 'Pobočka ke které oddělení patří',
            'hidden' => true, // ✅ HIDDEN from auto-generation
        ),
        
        'department_number' => array(
            'type' => 'text',
            'label' => 'Číslo oddělení',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
            'help' => 'Interní číslo oddělení (volitelné)',
        ),
        
        'name' => array(
            'type' => 'text',
            'label' => 'Název oddělení',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
            'help' => 'Název oddělení',
        ),
        
        'description' => array(
            'type' => 'textarea',
            'label' => 'Popis',
            'required' => false,
            'sanitize' => 'sanitize_textarea_field',
            'help' => 'Volitelný popis oddělení',
        ),
        
        'training_version' => array(
            'type' => 'number',
            'label' => 'Verze školení',
            'required' => false,
            'default' => 1,
            'min' => 1,
            'max' => 999,
            'sanitize' => 'intval',
            'help' => 'Deprecated - not used',
            'hidden' => true,
        ),
        
        'is_active' => array(
            'type' => 'boolean',
            'label' => 'Aktivní',
            'required' => false,
            'default' => 1,
            'sanitize' => 'absint',
            'help' => 'Pouze aktivní oddělení jsou dostupná pro výběr',
        ),
    ),
    
    'list_config' => array(
        // ✅ ONLY 3 COLUMNS - no branch_id!
        'columns' => array('department_number', 'name', 'is_active'),
        
        'searchable' => array('name', 'department_number', 'description'),
        'sortable' => array('name', 'department_number', 'created_at'),
        
        'filters' => array(
            'is_active' => true,
        ),
        
        'per_page' => 20,
        'enable_detail_modal' => true,
    ),
    
    'cache' => array(
        'enabled' => true,
        'ttl' => 300,
        'invalidate_on' => array('save', 'delete'),
    ),
);