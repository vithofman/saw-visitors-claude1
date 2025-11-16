<?php
/**
 * Visitors Relations Configuration
 * 
 * Defines related data that should be displayed in visitor detail view.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visitors
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(
    'visit' => array(
        'label' => 'Návštěva',
        'icon' => '📅',
        'entity' => 'visits',
        'type' => 'parent',
        'foreign_key' => 'visit_id',
        'display_field' => 'id',
        'route' => 'admin/visits/{id}/',
        'custom_display' => function($item) {
            if (empty($item['visit_data'])) {
                return 'N/A';
            }
            
            $visit = $item['visit_data'];
            
            $output = '<div class="saw-parent-visit-info">';
            $output .= '<div><strong>Firma:</strong> ' . esc_html($visit['company_name'] ?? 'N/A') . '</div>';
            $output .= '<div><strong>Pobočka:</strong> ' . esc_html($visit['branch_name'] ?? 'N/A') . '</div>';
            $output .= '<div><strong>Stav:</strong> ' . esc_html($visit['status'] ?? 'N/A') . '</div>';
            $output .= '</div>';
            
            return $output;
        },
    ),
);
