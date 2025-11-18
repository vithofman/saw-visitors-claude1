<?php
/**
 * Account Types List Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @version     4.0.0 - FIXED: Color display + AJAX handlers + badge styling
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prepare config for AdminTable
$table_config = $config;
$base_url = home_url('/admin/' . ($config['route'] ?? 'account-types'));

$table_config['title'] = $config['plural'];
$table_config['create_url'] = $base_url . '/create';
$table_config['detail_url'] = $base_url . '/{id}/';
$table_config['edit_url'] = $base_url . '/{id}/edit';

// ✅ FIXED: Proper column definitions
$table_config['columns'] = array(
    'color' => array(
        'label' => 'Barva',
        'type' => 'custom',
        'width' => '80px',
        'align' => 'center',
        'callback' => function($value) {
            if (empty($value)) {
                return '<span class="saw-text-muted">—</span>';
            }
            return '<div style="width: 40px; height: 40px; border-radius: 8px; background-color: ' . esc_attr($value) . '; border: 2px solid #e5e7eb; margin: 0 auto;"></div>';
        }
    ),
    'display_name' => array(
        'label' => 'Název',
        'type' => 'text',
        'sortable' => true,
        'class' => 'saw-table-cell-bold'
    ),
    'name' => array(
        'label' => 'Interní název',
        'type' => 'custom',
        'callback' => function($value) {
            return '<span class="saw-badge saw-badge-secondary" style="font-family: monospace;">' . esc_html($value) . '</span>';
        }
    ),
    'price' => array(
        'label' => 'Cena',
        'type' => 'custom',
        'align' => 'right',
        'width' => '120px',
        'callback' => function($value) {
            $price = floatval($value ?? 0);
            if ($price > 0) {
                return '<strong>' . number_format($price, 2, ',', ' ') . ' Kč/měsíc</strong>';
            }
            return '<span class="saw-text-muted">Zdarma</span>';
        }
    ),
    'features' => array(
        'label' => 'Funkce',
        'type' => 'custom',
        'align' => 'center',
        'width' => '100px',
        'callback' => function($value) {
            $features = !empty($value) ? json_decode($value, true) : array();
            $count = is_array($features) ? count($features) : 0;
            if ($count > 0) {
                return '<span class="saw-badge saw-badge-info">' . $count . ' funkcí</span>';
            }
            return '<span class="saw-text-muted">—</span>';
        }
    ),
    'sort_order' => array(
        'label' => 'Pořadí',
        'type' => 'text',
        'align' => 'center',
        'width' => '80px'
    ),
    'is_active' => array(
        'label' => 'Status',
        'type' => 'custom',
        'width' => '100px',
        'align' => 'center',
        'callback' => function($value) {
            if (!empty($value)) {
                return '<span class="saw-badge saw-badge-success">Aktivní</span>';
            }
            return '<span class="saw-badge saw-badge-secondary">Neaktivní</span>';
        }
    ),
    'created_at' => array(
        'label' => 'Vytvořeno',
        'type' => 'date',
        'width' => '120px',
        'format' => 'd.m.Y'
    )
);

// Data
$table_config['rows'] = $items;
$table_config['total_items'] = $total;
$table_config['current_page'] = $page;
$table_config['total_pages'] = $total_pages;
$table_config['search_value'] = $search;
$table_config['orderby'] = $orderby;
$table_config['order'] = $order;

// Features
$table_config['enable_search'] = true;
$table_config['search_placeholder'] = 'Hledat typ účtu...';
$table_config['enable_filters'] = true;

// Sidebar context
$table_config['sidebar_mode'] = $sidebar_mode;
$table_config['detail_item'] = $detail_item ?? null;
$table_config['form_item'] = $form_item ?? null;
$table_config['detail_tab'] = $detail_tab;
$table_config['module_config'] = $config;

// Actions
$table_config['actions'] = array('view', 'edit', 'delete');
$table_config['add_new'] = 'Nový typ účtu';

// Render
$table = new SAW_Component_Admin_Table($entity, $table_config);
$table->render();