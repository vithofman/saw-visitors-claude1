<?php
/**
 * Users Relations Configuration
 * 
 * Defines related data that should be displayed in user detail view.
 * Shows branch and departments associated with the user.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Users
 * @version     1.1.0 - Added branches relation
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(
    'branches' => array(
        'label' => 'Pobočka',
        'icon' => '🏢',
        'entity' => 'branches',
        'foreign_key' => 'id',
        'local_key' => 'branch_id',
        'display_fields' => array('name', 'code'),
        'route' => 'admin/branches/{id}/',
        'order_by' => 'name ASC',
        'custom_query' => true, // Flag that this needs custom logic
        'fields' => array(
            'name' => array(
                'label' => 'Název',
                'type' => 'text',
            ),
            'code' => array(
                'label' => 'Kód',
                'type' => 'text',
            ),
            'city' => array(
                'label' => 'Město',
                'type' => 'text',
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
    'departments' => array(
        'label' => 'Oddělení',
        'icon' => '🏭',
        'entity' => 'departments',
        'junction_table' => 'saw_user_departments',
        'foreign_key' => 'user_id',
        'local_key' => 'department_id',
        'display_fields' => array('department_number', 'name'),
        'route' => 'admin/departments/{id}/',
        'order_by' => 'name ASC',
        'fields' => array(
            'department_number' => array(
                'label' => 'Číslo',
                'type' => 'text',
            ),
            'name' => array(
                'label' => 'Název oddělení',
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