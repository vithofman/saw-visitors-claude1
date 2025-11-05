<?php
/**
 * Departments List Template
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SAW_Component_Search')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/search/class-saw-component-search.php';
}

if (!class_exists('SAW_Component_Selectbox')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/selectbox/class-saw-component-selectbox.php';
}

if (!class_exists('SAW_Component_Admin_Table')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';
}

if (!class_exists('SAW_Component_Modal')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/modal/class-saw-component-modal.php';
}

// Prepare search component
ob_start();
$search_component = new SAW_Component_Search('departments', array(
    'placeholder' => 'Hledat oddělení...',
    'search_value' => $search,
    'ajax_enabled' => false,
    'ajax_action' => 'saw_search_departments',
    'show_button' => true,
    'show_info_banner' => true,
    'info_banner_label' => 'Vyhledávání:',
    'clear_url' => home_url('/admin/departments/'),
));
$search_component->render();
$search_html = ob_get_clean();

// Prepare filters
ob_start();

// Status filter only
if (!empty($this->config['list_config']['filters']['is_active'])) {
    $status_filter = new SAW_Component_Selectbox('is_active-filter', array(
        'options' => array(
            '' => 'Všechny statusy',
            '1' => 'Aktivní',
            '0' => 'Neaktivní',
        ),
        'selected' => $_GET['is_active'] ?? '',
        'on_change' => 'redirect',
        'allow_empty' => true,
        'custom_class' => 'saw-filter-select',
        'name' => 'is_active',
    ));
    $status_filter->render();
}

$filters_html = ob_get_clean();

// Get branch names for items
if (!empty($items)) {
    global $wpdb;
    $branch_ids = array_unique(array_column($items, 'branch_id'));
    
    if (!empty($branch_ids)) {
        $placeholders = implode(',', array_fill(0, count($branch_ids), '%d'));
        $branches_data = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name FROM %i WHERE id IN ($placeholders)",
            $wpdb->prefix . 'saw_branches',
            ...$branch_ids
        ), ARRAY_A);
        
        $branches_map = [];
        foreach ($branches_data as $branch) {
            $branches_map[$branch['id']] = $branch['name'];
        }
        
        foreach ($items as &$item) {
            $item['branch_name'] = $branches_map[$item['branch_id']] ?? 'N/A';
        }
    }
}

// Initialize admin table
$table = new SAW_Component_Admin_Table('departments', [
    'title' => 'Oddělení',
    'create_url' => home_url('/admin/departments/new/'),
    'edit_url' => home_url('/admin/departments/edit/{id}/'),
    
    'columns' => [
        'department_number' => [
            'label' => 'Číslo',
            'type' => 'custom',
            'width' => '100px',
            'callback' => function($value) {
                if (!empty($value)) {
                    return '<span class="saw-code-badge">' . esc_html($value) . '</span>';
                }
                return '<span class="saw-text-muted">—</span>';
            }
        ],
        'name' => [
            'label' => 'Název oddělení',
            'type' => 'text',
            'sortable' => true,
            'bold' => true
        ],
        'branch_name' => [
            'label' => 'Pobočka',
            'type' => 'text',
        ],
        'training_version' => [
            'label' => 'Verze školení',
            'type' => 'custom',
            'align' => 'center',
            'width' => '120px',
            'callback' => function($value) {
                return '<span class="saw-badge saw-badge-info">v' . esc_html($value) . '</span>';
            }
        ],
        'is_active' => [
            'label' => 'Status',
            'type' => 'badge',
            'width' => '100px',
            'align' => 'center',
            'map' => [
                '1' => 'success',
                '0' => 'secondary'
            ],
            'labels' => [
                '1' => 'Aktivní',
                '0' => 'Neaktivní'
            ]
        ],
        'created_at' => [
            'label' => 'Vytvořeno',
            'type' => 'date',
            'format' => 'd.m.Y'
        ]
    ],
    
    'rows' => $items,
    'total_items' => $total,
    'current_page' => $page,
    'total_pages' => $total_pages,
    'orderby' => $orderby,
    'order' => $order,
    'search' => $search_html,
    'filters' => $filters_html,
    'actions' => ['edit', 'delete'],
    'empty_message' => 'Žádná oddělení nenalezena',
    'add_new' => 'Nové oddělení',
    
    'enable_modal' => true,
    'modal_id' => 'department-detail',
    'modal_ajax_action' => 'saw_get_departments_detail',
]);

$table->render();

// Modal component
$department_modal = new SAW_Component_Modal('department-detail', array(
    'title' => 'Detail oddělení',
    'ajax_enabled' => true,
    'ajax_action' => 'saw_get_departments_detail',
    'size' => 'large',
    'show_close' => true,
    'close_on_backdrop' => true,
    'close_on_escape' => true,
    'header_actions' => array(
        array(
            'type' => 'edit',
            'label' => '',
            'icon' => 'dashicons-edit',
            'url' => home_url('/admin/departments/edit/{id}/'),
        ),
        array(
            'type' => 'delete',
            'label' => '',
            'icon' => 'dashicons-trash',
            'confirm' => true,
            'confirm_message' => 'Opravdu chcete smazat toto oddělení?',
            'ajax_action' => 'saw_delete_departments',
        ),
    ),
));
$department_modal->render();