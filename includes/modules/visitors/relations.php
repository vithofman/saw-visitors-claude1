<?php
/**
 * Visitors Relations Configuration
 * 
 * Defines related data that should be displayed in visitor detail view.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visitors
 * @version     4.0.0 - Multi-language support
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// TRANSLATIONS SETUP
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}
$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'visitors') 
    : [];

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// Pre-load labels for closures
$visit_label = $tr('relation_visit', 'Návštěva');
$company_label = $tr('relation_company', 'Firma');
$branch_label = $tr('relation_branch', 'Pobočka');
$status_label = $tr('relation_status', 'Stav');

return array(
    'visit' => array(
        'label' => $visit_label,
        'icon' => '📅',
        'entity' => 'visits',
        'type' => 'parent',
        'foreign_key' => 'visit_id',
        'display_field' => 'id',
        'route' => 'admin/visits/{id}/',
        'custom_display' => function($item) use ($company_label, $branch_label, $status_label) {
            if (empty($item['visit_data'])) {
                return 'N/A';
            }
            
            $visit = $item['visit_data'];
            
            $output = '<div class="saw-parent-visit-info">';
            $output .= '<div><strong>' . esc_html($company_label) . ':</strong> ' . esc_html($visit['company_name'] ?? 'N/A') . '</div>';
            $output .= '<div><strong>' . esc_html($branch_label) . ':</strong> ' . esc_html($visit['branch_name'] ?? 'N/A') . '</div>';
            $output .= '<div><strong>' . esc_html($status_label) . ':</strong> ' . esc_html($visit['status'] ?? 'N/A') . '</div>';
            $output .= '</div>';
            
            return $output;
        },
    ),
);