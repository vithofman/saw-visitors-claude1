<?php
if (!defined('ABSPATH')) {
    exit;
}

return array(
    'visitors' => array(
        'label' => 'Návštěvníci',
        'icon' => '👥',
        'entity' => 'visitors',
        'foreign_key' => 'visit_id',
        'display_fields' => array('first_name', 'last_name', 'position'),
        'route' => 'admin/visitors/{id}/',
        'order_by' => 'last_name ASC, first_name ASC',
    ),
);