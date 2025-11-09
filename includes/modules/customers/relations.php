<?php
/**
 * Customers Module - Relations Configuration
 *
 * Defines relationships between customers and other entities
 * for the Related Data sidebar system.
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Customers
 * @since       8.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(
    'branches' => array(
        'label' => 'Pobočky',
        'icon' => '🏢',
        'entity' => 'branches',
        'foreign_key' => 'customer_id',
        'display_field' => 'name',
        'route' => 'admin/branches/{id}/',
        'order_by' => 'is_headquarters DESC, name ASC',
    ),
);