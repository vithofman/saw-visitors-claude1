<?php
/**
 * Customers List Template
 * 
 * Displays paginated list of customers with:
 * - Search functionality
 * - Status filter (potential/active/inactive)
 * - Sortable columns
 * - Logo display
 * - Account type badge (dynamic from database)
 * - Primary color preview
 * - AJAX modal for detail view
 * - Edit/Delete actions
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Customers/Templates
 * @since       1.0.0
 * @version     8.0.0
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
    'clear_url' => home_url('/admin/settings/customers/'),
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

// Initialize admin table component
$table = new SAW_Component_Admin_Table('customers', array(
    'title' => __('Zákazníci', 'saw-visitors'),
    'create_url' => home_url('/admin/settings/customers/new/'),
    'edit_url' => home_url('/admin/settings/customers/edit/{id}/'),
    
    'columns' => array(
        'logo_url' => array(
            'label' => __('Logo', 'saw-visitors'),
            'type' => 'image',
            'width' => '60px',
            'align' => 'center',
            'placeholder' => 'building'
        ),
        'name' => array(
            'label' => __('Název', 'saw-visitors'),
            'type' => 'text',
            'sortable' => true,
            'bold' => true
        ),
        'ico' => array(
            'label' => __('IČO', 'saw-visitors'),
            'type' => 'text'
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
        'subscription_type' => array(
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
                
                return sprintf(
                    '<span class="saw-badge" style="background-color: %s; color: #fff; border-color: %s;">%s</span>',
                    esc_attr($type['color']),
                    esc_attr($type['color']),
                    esc_html($type['display_name'])
                );
            }
        ),
        'primary_color' => array(
            'label' => __('Barva', 'saw-visitors'),
            'type' => 'color_badge',
            'width' => '80px',
            'align' => 'center'
        ),
        'created_at' => array(
            'label' => __('Vytvořeno', 'saw-visitors'),
            'type' => 'date',
            'format' => 'd.m.Y'
        )
    ),
    
    'rows' => $items,
    'total_items' => $total,
    'current_page' => $page,
    'total_pages' => $total_pages,
    'orderby' => $orderby,
    'order' => $order,
    'search' => $search_html,
    'filters' => $filters_html,
    'actions' => array('edit', 'delete'),
    'empty_message' => __('Žádní zákazníci nenalezeni', 'saw-visitors'),
    'add_new' => __('Nový zákazník', 'saw-visitors'),
    
    'enable_modal' => true,
    'modal_id' => 'customer-detail',
    'modal_ajax_action' => 'saw_get_customers_detail',
));

// Render table
$table->render();

// Modal component
$customer_modal = new SAW_Component_Modal('customer-detail', array(
    'title' => __('Detail zákazníka', 'saw-visitors'),
    'ajax_enabled' => true,
    'ajax_action' => 'saw_get_customers_detail',
    'size' => 'large',
    'show_close' => true,
    'close_on_backdrop' => true,
    'close_on_escape' => true,
    'header_actions' => array(
        array(
            'type' => 'edit',
            'label' => '',
            'icon' => 'dashicons-edit',
            'url' => home_url('/admin/settings/customers/edit/{id}/'),
        ),
        array(
            'type' => 'delete',
            'label' => '',
            'icon' => 'dashicons-trash',
            'confirm' => true,
            'confirm_message' => __('Opravdu chcete smazat tohoto zákazníka?', 'saw-visitors'),
            'ajax_action' => 'saw_delete_customers',
        ),
    ),
));
$customer_modal->render();
?>

<script>
window.sawAjaxNonce = '<?php echo esc_js($ajax_nonce); ?>';
</script>