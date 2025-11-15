<?php
/**
 * Companies Relations Configuration
 * 
 * Defines related data that should be displayed in company detail view.
 * 
 * Future relations could include:
 * - Visitors from this company
 * - Contacts from this company
 * - Training records for this company
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Companies
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(
    // No relations configured yet
    // Add relations here as needed in the future
    
    // Example structure for future visitors relation:
    /*
    'visitors' => array(
        'label' => 'Návštěvníci',
        'icon' => '👥',
        'entity' => 'visitors',
        'table' => 'saw_visitors',
        'foreign_key' => 'company_id',
        'display_fields' => array('first_name', 'last_name', 'email'),
        'route' => 'admin/visitors/{id}/',
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
        ),
    ),
    */
);
