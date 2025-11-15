<?php
/**
 * Companies List Template
 * @version 1.0.0
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
$search_component = new SAW_Component_Search('companies', array(
    'placeholder' => 'Hledat firmy...',
    'search_value' => $search,
    'ajax_enabled' => false,
    'show_button' => true,
    'show_info_banner' => true,
    'info_banner_label' => 'Vyhledávání:',
    'clear_url' => home_url('/admin/companies/'),
));
$search_component->render();
$search_html = ob_get_clean();

ob_start();
if (!empty($this->config['list_config']['filters']['is_archived'])) {
    $status_filter = new SAW_Component_Selectbox('is_archived-filter', array(
        'options' => array('' => 'Všechny statusy', '0' => 'Aktivní', '1' => 'Archivované'),
        'selected' => $_GET['is_archived'] ?? '',
        'on_change' => 'redirect',
        'allow_empty' => true,
        'custom_class' => 'saw-filter-select',
        'name' => 'is_archived',
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
/* Tučné písmo pro název firmy a IČO */
.saw-module-companies .saw-admin-table tbody td:first-child {
    font-weight: 600 !important;
    color: #0066cc !important;
}
.saw-module-companies .saw-admin-table tbody td:nth-child(2) {
    font-weight: 500 !important;
    color: #666 !important;
}
</style>

<div class="saw-module-companies">
    <?php
    $table = new SAW_Component_Admin_Table('companies', array(
        'title' => 'Firmy',
        'create_url' => home_url('/admin/companies/create'),
        'edit_url' => home_url('/admin/companies/{id}/edit'),
        'detail_url' => home_url('/admin/companies/{id}/'),
        'module_config' => $this->config,
        'sidebar_mode' => $sidebar_mode ?? null,
        'detail_item' => $detail_item ?? null,
        'form_item' => $form_item ?? null,
        'detail_tab' => $detail_tab ?? 'overview',
        'related_data' => $related_data ?? null,
        'is_edit' => $is_edit ?? false,
        'branches' => $branches,
        
        // Override formatting for specific columns
        'column_formats' => array(
            'name' => array(
                'label' => 'Název firmy',
                'type' => 'text',
                'sortable' => true,
            ),
            'ico' => array(
                'label' => 'IČO',
                'type' => 'text',
                'sortable' => true,
            ),
            'city' => array(
                'label' => 'Město',
                'type' => 'text',
                'sortable' => true,
            ),
            'is_archived' => array(
                'label' => 'Status', 
                'type' => 'badge',
                'align' => 'center',
                'map' => array(0 => 'success', 1 => 'secondary'),
                'labels' => array(0 => '✓ Aktivní', 1 => '✗ Archivováno')
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
        'empty_message' => 'Žádné firmy nenalezeny',
        'add_new' => 'Nová firma',
        'ajax_enabled' => true,
        'ajax_nonce' => $ajax_nonce,
    ));
    $table->render();
    ?>
</div>
