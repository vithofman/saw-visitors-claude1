<?php
/**
 * Customers List Template - SIDEBAR VERSION
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Customers/Templates
 * @since       1.0.0
 * @version     11.1.0 - FIXED: Added .saw-module-customers wrapper
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

if (!class_exists('SAW_Component_Modal')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/modal/class-saw-component-modal.php';
}

// Global nonce for AJAX
$ajax_nonce = wp_create_nonce('saw_ajax_nonce');

// Prepare search component HTML
ob_start();
$search_component = new SAW_Component_Search('customers', array(
    'placeholder' => __('Hledat zákazníka...', 'saw-visitors'),
    'search_value' => $search,
    'ajax_enabled' => false,
    'ajax_action' => 'saw_search_customers',
    'show_button' => true,
    'show_info_banner' => true,
    'info_banner_label' => __('Vyhledávání:', 'saw-visitors'),
    'clear_url' => home_url('/admin/customers/'),
));
$search_component->render();
$search_html = ob_get_clean();

// Prepare filter component HTML
ob_start();
if (!empty($this->config['list_config']['filters']['status'])) {
    $status_filter = new SAW_Component_Selectbox('status-filter', array(
        'options' => array(
            '' => __('Všechny statusy', 'saw-visitors'),
            'potential' => __('Potenciální', 'saw-visitors'),
            'active' => __('Aktivní', 'saw-visitors'),
            'inactive' => __('Neaktivní', 'saw-visitors'),
        ),
        'selected' => $_GET['status'] ?? '',
        'on_change' => 'redirect',
        'allow_empty' => true,
        'custom_class' => 'saw-filter-select',
        'name' => 'status',
    ));
    $status_filter->render();
}
$filters_html = ob_get_clean();
?>

<!-- CRITICAL: Module wrapper for proper layout -->
<div class="saw-module-customers">
    <?php
    // Initialize admin table component with sidebar support
    $table = new SAW_Component_Admin_Table('customers', array(
        'title' => __('Zákazníci', 'saw-visitors'),
        'create_url' => home_url('/admin/customers/create'),
        'edit_url' => home_url('/admin/customers/{id}/edit'),
        'detail_url' => home_url('/admin/customers/{id}/'),
        
        // CRITICAL: Pass module config for auto-generation
        'module_config' => $this->config,
        
        // CRITICAL: Sidebar support with account_types
        'sidebar_mode' => $sidebar_mode ?? null,
        'detail_item' => $detail_item ?? null,
        'form_item' => $form_item ?? null,
        'detail_tab' => $detail_tab ?? 'overview',
        'account_types' => $account_types ?? array(),
	'related_data' => $related_data ?? null,        


        // Override auto-generated columns with custom display
        'columns' => array(
            'logo_url' => array(
                'label' => __('Logo', 'saw-visitors'),
                'type' => 'image',
                'width' => '60px',
                'align' => 'center',
            ),
            'name' => array(
                'label' => __('Název', 'saw-visitors'),
                'type' => 'text',
                'sortable' => true,
                'class' => 'saw-table-cell-bold',
            ),
            'ico' => array(
                'label' => __('IČO', 'saw-visitors'),
                'type' => 'text',
            ),
            'status' => array(
                'label' => __('Status', 'saw-visitors'),
                'type' => 'badge',
                'map' => array(
                    'active' => 'success',
                    'inactive' => 'secondary',
                    'potential' => 'warning'
                ),
                'labels' => array(
                    'active' => __('Aktivní', 'saw-visitors'),
                    'inactive' => __('Neaktivní', 'saw-visitors'),
                    'potential' => __('Potenciální', 'saw-visitors')
                )
            ),
            'account_type_id' => array(
                'label' => __('Typ účtu', 'saw-visitors'),
                'type' => 'custom',
                'width' => '150px',
                'callback' => function($value) {
                    if (empty($value)) {
                        return '<span class="saw-text-muted">—</span>';
                    }
                    
                    global $wpdb;
                    $type = $wpdb->get_row($wpdb->prepare(
                        "SELECT display_name, color FROM %i WHERE id = %d",
                        $wpdb->prefix . 'saw_account_types',
                        $value
                    ), ARRAY_A);
                    
                    if (!$type) {
                        return '<span class="saw-text-muted">—</span>';
                    }
                    
                    $color = !empty($type['color']) ? esc_attr($type['color']) : '#6b7280';
                    return sprintf(
                        '<span class="saw-badge" style="background-color: %s; color: white;">%s</span>',
                        $color,
                        esc_html($type['display_name'])
                    );
                }
            ),
            'created_at' => array(
                'label' => __('Vytvořeno', 'saw-visitors'),
                'type' => 'date',
                'sortable' => true,
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
        
        'enable_modal' => empty($sidebar_mode),
        'modal_id' => 'customer-detail',
        'modal_ajax_action' => 'saw_get_customers_detail',
    ));

    $table->render();
    ?>
</div>

<?php
// Modal component (backward compatible)
if (empty($sidebar_mode)) {
    $modal = new SAW_Component_Modal('customer-detail', array(
        'title' => __('Detail zákazníka', 'saw-visitors'),
        'size' => 'large',
    ));
    $modal->render();
}