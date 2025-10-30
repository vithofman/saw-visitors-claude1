<?php
/**
 * Customers List Template
 * 
 * Používá existující SAW_Component_Admin_Table
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 * @since   4.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';

$admin_table = new SAW_Component_Admin_Table('customers', array(
    'title' => 'Zákazníci',
    
    'columns' => array(
        'name' => array(
            'label' => 'Název',
            'sortable' => true,
        ),
        'ico' => array(
            'label' => 'IČO',
            'sortable' => true,
        ),
        'status' => array(
            'label' => 'Status',
            'format' => function($value) {
                $badges = array(
                    'potential' => '<span class="saw-badge saw-badge-warning">Potenciální</span>',
                    'active' => '<span class="saw-badge saw-badge-success">Aktivní</span>',
                    'inactive' => '<span class="saw-badge saw-badge-secondary">Neaktivní</span>',
                );
                return $badges[$value] ?? $value;
            },
        ),
        'subscription_type' => array(
            'label' => 'Předplatné',
            'format' => function($value) {
                $labels = array(
                    'free' => 'Zdarma',
                    'basic' => 'Basic',
                    'pro' => 'Pro',
                    'enterprise' => 'Enterprise',
                );
                return $labels[$value ?? 'free'] ?? 'Zdarma';
            },
        ),
        'primary_color' => array(
            'label' => 'Barva',
            'format' => function($value) {
                if (empty($value)) {
                    return '';
                }
                return '<span class="saw-color-badge" style="background-color: ' . esc_attr($value) . '; border: 2px solid #fff; box-shadow: 0 0 0 1px #dcdcde;" title="' . esc_attr($value) . '"></span>';
            },
        ),
        'created_at' => array(
            'label' => 'Vytvořeno',
            'sortable' => true,
            'format' => function($value) {
                return date_i18n('d.m.Y', strtotime($value));
            },
        ),
    ),
    
    'rows' => $items,
    'total_items' => $total_items,
    
    'current_page' => $page,
    'total_pages' => $total_pages,
    'per_page' => 20,
    
    'orderby' => $orderby,
    'order' => $order,
    
    'search' => true,
    'search_value' => $search,
    
    'actions' => array('edit', 'delete'),
    'create_url' => home_url('/admin/settings/customers/new/'),
    'edit_url' => home_url('/admin/settings/customers/edit/{id}/'),
    
    'enable_detail_modal' => true,
    
    'row_class_callback' => function($row) {
        return 'saw-customer-row';
    },
    'row_style_callback' => function($row) {
        if (!empty($row['primary_color'])) {
            $color = $row['primary_color'];
            list($r, $g, $b) = sscanf($color, "#%02x%02x%02x");
            return 'background: linear-gradient(to right, rgba(' . $r . ', ' . $g . ', ' . $b . ', 0.08) 0%, rgba(' . $r . ', ' . $g . ', ' . $b . ', 0.02) 100%);';
        }
        return '';
    },
));

$admin_table->render();

include SAW_VISITORS_PLUGIN_DIR . 'templates/modals/customer-detail-modal.php';
