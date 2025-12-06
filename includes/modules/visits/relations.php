<?php
/**
 * Visits Relations Configuration
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
 * @version     4.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load translations
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}
$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'visits') 
    : [];

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// Pre-load labels for closures
$visitors_label = $tr('relation_visitors', 'Návštěvníci');
$visitor_fallback = $tr('relation_visitor_fallback', 'Návštěvník');

return array(
    'visitors' => array(
        'label' => $visitors_label,
        'icon' => '👥',
        'entity' => 'visitors',
        'foreign_key' => 'visit_id',
        'display_fields' => array('first_name', 'last_name', 'position'),
        'custom_display' => function($item) use ($visitor_fallback) {
            $name = trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''));
            if (empty($name)) {
                return $visitor_fallback . ' #' . ($item['id'] ?? '');
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