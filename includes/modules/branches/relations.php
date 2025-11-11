<?php
/**
 * Branches Relations Configuration
 * 
 * Defines related data that should be displayed in branch detail view.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(
    'departments' => array(
        'label' => 'Oddělení',
        'icon' => '🏭',
        'entity' => 'departments',
        'foreign_key' => 'branch_id',
        'display_fields' => array('department_number', 'name'),
        'route' => 'admin/departments/{id}/',
        'order_by' => 'name ASC',
        'fields' => array(
            'department_number' => array(
                'label' => 'Číslo',
                'type' => 'text',
            ),
            'name' => array(
                'label' => 'Název',
                'type' => 'text',
            ),
            'training_version' => array(
                'label' => 'Verze školení',
                'type' => 'number',
            ),
            'is_active' => array(
                'label' => 'Aktivní',
                'type' => 'boolean',
                'format' => function($value) {
                    return $value ? '✓ Ano' : '✗ Ne';
                },
            ),
        ),
    ),
);
