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
        'custom_display' => function($item) {
            $name = trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''));
            if (empty($name)) {
                return 'Návštěvník #' . ($item['id'] ?? '');
            }
            $parts = array($name);
            if (!empty($item['position'])) {
                $parts[] = $item['position'];
            }
            return implode(' - ', $parts);
        },
        'route' => 'admin/visitors/{id}/',
        'order_by' => 'last_name ASC, first_name ASC',
    ),
);