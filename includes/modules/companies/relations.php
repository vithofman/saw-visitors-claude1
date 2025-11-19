<?php
/**
 * Companies Relations Configuration
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Companies
 * @version     2.0.0 - SIMPLIFIED: Basic display without custom function
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(
    'visits' => array(
        'label' => 'Návštěvy této firmy',
        'icon' => '📋',
        'entity' => 'visits',
        'foreign_key' => 'company_id',
        'display_field' => 'id',
        'route' => 'admin/visits/{id}/',
        'order_by' => 'created_at DESC',
    ),
);