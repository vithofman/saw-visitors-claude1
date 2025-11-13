<?php
/**
 * Departments List Template
 * @version 6.1.0 - FIXED: No background, borders visible
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('SAW_Component_Search')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/search/class-saw-component-search.php';
}
if (!class_exists('SAW_Component_Selectbox')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/selectbox/class-saw-component-selectbox.php';
}
if (!class_exists('SAW_Component_Admin_Table')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';
}

$ajax_nonce = wp_create_nonce('saw_ajax_nonce');

ob_start();
$search_component = new SAW_Component_Search('departments', array(
    'placeholder' => 'Hledat oddělení...',
    'search_value' => $search,
    'ajax_enabled' => false,
    'show_button' => true,
    'show_info_banner' => true,
    'info_banner_label' => 'Vyhledávání:',
    'clear_url' => home_url('/admin/departments/'),
));
$search_component->render();
$search_html = ob_get_clean();

ob_start();
if (!empty($this->config['list_config']['filters']['is_active'])) {
    $status_filter = new SAW_Component_Selectbox('is_active-filter', array(
        'options' => array('' => 'Všechny statusy', '1' => 'Aktivní', '0' => 'Neaktivní'),
        'selected' => $_GET['is_active'] ?? '',
        'on_change' => 'redirect',
        'allow_empty' => true,
        'custom_class' => 'saw-filter-select',
        'name' => 'is_active',
    ));
    $status_filter->render();
}
$filters_html = ob_get_clean();

global $wpdb;
$customer_id = SAW_Context::get_customer_id();
$branches = array();
if ($customer_id) {
    $branches_data = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name FROM %i WHERE customer_id = %d AND is_active = 1 ORDER BY name ASC",
        $wpdb->prefix . 'saw_branches', $customer_id
    ), ARRAY_A);
    foreach ($branches_data as $branch) {
        $branches[$branch['id']] = $branch['name'];
    }
}
?>

<style>
/* ✅ Tučné písmo jako u poboček */
.saw-module-departments .saw-admin-table tbody td:first-child {
    font-weight: 600 !important;
    color: #0066cc !important;
}
.saw-module-departments .saw-admin-table tbody td:nth-child(2) {
    font-weight: 600 !important;
    color: #171717 !important;
}
</style>

<div class="saw-module-departments">
    <?php
    $table = new SAW_Component_Admin_Table('departments', array(
        'title' => 'Oddělení',
        'create_url' => home_url('/admin/departments/create'),
        'edit_url' => home_url('/admin/departments/{id}/edit'),
        'detail_url' => home_url('/admin/departments/{id}/'),
        'module_config' => $this->config,
        'sidebar_mode' => $sidebar_mode ?? null,
        'detail_item' => $detail_item ?? null,
        'form_item' => $form_item ?? null,
        'detail_tab' => $detail_tab ?? 'overview',
        'related_data' => $related_data ?? null,
        'is_edit' => $is_edit ?? false,
        'branches' => $branches,
        'column_formats' => array(
            'department_number' => array('label' => 'Číslo oddělení', 'type' => 'text'),
            'name' => array('label' => 'Název oddělení', 'type' => 'text'),
            'is_active' => array(
                'label' => 'Status', 'type' => 'badge',
                'map' => array('1' => 'success', '0' => 'secondary'),
                'labels' => array('1' => 'Aktivní', '0' => 'Neaktivní')
            ),
        ),
        'rows' => $items,
        'total_items' => $total,
        'current_page' => $page,
        'total_pages' => $total_pages,
        'orderby' => $orderby,
        'order' => $order,
        'search' => $search_html,
        'filters' => $filters_html,
        'actions' => array('view', 'edit', 'delete'),
        'empty_message' => 'Žádná oddělení nenalezena',
        'add_new' => 'Nové oddělení',
        'ajax_enabled' => true,
        'ajax_nonce' => $ajax_nonce,
    ));
    $table->render();
    ?>
</div>