<?php
/**
 * Departments Relations Configuration
 * 
 * Defines related data that should be displayed in department detail view.
 * Shows users who are assigned to this department.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Departments
 * @version     1.1.0 - Added users relation
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(
    'users' => array(
        'label' => 'Uživatelé',
        'icon' => '👥',
        'entity' => 'users',
        'junction_table' => 'saw_user_departments',
        'foreign_key' => 'department_id',
        'local_key' => 'user_id',
        'display_fields' => array('first_name', 'last_name', 'email'),
        'route' => 'admin/users/{id}/',
        'order_by' => 'last_name ASC, first_name ASC',
        'fields' => array(
            'first_name' => array(
                'label' => 'Jméno',
                'type' => 'text',
            ),
            'last_name' => array(
                'label' => 'Příjmení',
                'type' => 'text',
            ),
            'email' => array(
                'label' => 'Email',
                'type' => 'text',
            ),
            'role' => array(
                'label' => 'Role',
                'type' => 'text',
                'format' => function($value) {
                    $roles = array(
                        'super_admin' => 'Super Admin',
                        'admin' => 'Admin',
                        'super_manager' => 'Super Manager',
                        'manager' => 'Manager',
                        'terminal' => 'Terminál',
                    );
                    return $roles[$value] ?? $value;
                },
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