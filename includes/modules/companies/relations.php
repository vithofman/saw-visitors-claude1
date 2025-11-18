<?php
/**
 * Companies Relations Configuration
 * 
 * Defines related data for companies (visits that belong to this company)
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Companies
 * @version     1.3.0 - Fixed: Direct foreign key relationship (no junction)
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(
    'visits' => array(
        'label' => 'Návštěvy této firmy',
        'icon' => '📋',
        'entity' => 'visits',
        'foreign_key' => 'company_id', // visits.company_id = companies.id
        'display_fields' => array('visit_type', 'status', 'created_at'),
        'route' => 'admin/visits/{id}/',
        'order_by' => 'created_at DESC',
        'fields' => array(
            'visit_type' => array(
                'label' => 'Typ',
                'type' => 'badge',
            ),
            'status' => array(
                'label' => 'Stav',
                'type' => 'badge',
            ),
            'created_at' => array(
                'label' => 'Vytvořeno',
                'type' => 'datetime',
            ),
        ),
    ),
);