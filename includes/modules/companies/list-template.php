<?php
/**
 * Companies List Template - SIDEBAR VERSION
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Companies
 * @since       1.0.0
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load required components
if (!class_exists('SAW_Component_Search')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/search/class-saw-component-search.php';
}

if (!class_exists('SAW_Component_Selectbox')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/selectbox/class-saw-component-selectbox.php';
}

if (!class_exists('SAW_Component_Admin_Table')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';
}

// Global nonce for AJAX
$ajax_nonce = wp_create_nonce('saw_ajax_nonce');

// Prepare search component HTML
ob_start();
$search_component = new SAW_Component_Search('companies', array(
    'placeholder' => __('Hledat firmy...', 'saw-visitors'),
    'search_value' => $search,
    'ajax_enabled' => false,
    'show_button' => true,
    'show_info_banner' => true,
    'info_banner_label' => __('Vyhledávání:', 'saw-visitors'),
    'clear_url' => home_url('/admin/companies/'),
));
$search_component->render();
$search_html = ob_get_clean();

// Prepare filter component HTML
ob_start();
if (!empty($this->config['list_config']['filters']['is_archived'])) {
    $status_filter = new SAW_Component_Selectbox('is_archived-filter', array(
        'options' => array(
            '' => __('Všechny statusy', 'saw-visitors'),
            '0' => __('Aktivní', 'saw-visitors'),
            '1' => __('Archivované', 'saw-visitors'),
        ),
        'selected' => $_GET['is_archived'] ?? '',
        'on_change' => 'redirect',
        'allow_empty' => true,
        'custom_class' => 'saw-filter-select',
        'name' => 'is_archived',
    ));
    $status_filter->render();
}
$filters_html = ob_get_clean();

// Load branches for sidebar
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

<!-- CRITICAL: Module wrapper for proper layout -->
<div class="saw-module-companies">
    <?php
    // Initialize admin table component with sidebar support
    $table = new SAW_Component_Admin_Table('companies', array(
        'title' => __('Firmy', 'saw-visitors'),
        'create_url' => home_url('/admin/companies/create'),
        'edit_url' => home_url('/admin/companies/{id}/edit'),
        'detail_url' => home_url('/admin/companies/{id}/'),
        
        // CRITICAL: Pass module config for auto-generation
        'module_config' => $this->config,
        
        // CRITICAL: Sidebar support
        'sidebar_mode' => $sidebar_mode ?? null,
        'detail_item' => $detail_item ?? null,
        'form_item' => $form_item ?? null,
        'detail_tab' => $detail_tab ?? 'overview',
        'related_data' => $related_data ?? null,
        'branches' => $branches ?? array(),

        // Override auto-generated columns with custom display
        'columns' => array(
            'name' => array(
                'label' => __('Název firmy', 'saw-visitors'),
                'type' => 'text',
                'sortable' => true,
                'class' => 'saw-table-cell-bold',
            ),
            'ico' => array(
                'label' => __('IČO', 'saw-visitors'),
                'type' => 'text',
                'sortable' => true,
            ),
            'street' => array(
                'label' => __('Ulice a č.p.', 'saw-visitors'),
                'type' => 'text',
            ),
            'city' => array(
                'label' => __('Město', 'saw-visitors'),
                'type' => 'text',
                'sortable' => true,
            ),
            'zip' => array(
                'label' => __('PSČ', 'saw-visitors'),
                'type' => 'text',
            ),
            'email' => array(
                'label' => __('Email', 'saw-visitors'),
                'type' => 'email',
            ),
            'phone' => array(
                'label' => __('Telefon', 'saw-visitors'),
                'type' => 'text',
            ),
            'website' => array(
                'label' => __('Web', 'saw-visitors'),
                'type' => 'text',
            ),
            'is_archived' => array(
                'label' => __('Status', 'saw-visitors'),
                'type' => 'badge',
                'align' => 'center',
                'map' => array(
                    0 => 'success',
                    1 => 'secondary',
                ),
                'labels' => array(
                    0 => __('✓ Aktivní', 'saw-visitors'),
                    1 => __('✗ Archivováno', 'saw-visitors'),
                )
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
        'empty_message' => __('Žádné firmy nenalezeny', 'saw-visitors'),
        'add_new' => __('Nová firma', 'saw-visitors'),
        
        'ajax_enabled' => true,
        'ajax_nonce' => $ajax_nonce,
    ));

    $table->render();
    ?>
</div>
