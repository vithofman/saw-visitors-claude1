<?php
/**
 * Customers List Template
 * 
 * @package SAW_Visitors
 * @version 8.0.0 - PRODUCTION: account_type_id column with colored badge
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

// Global nonce for AJAX
$ajax_nonce = wp_create_nonce('saw_ajax_nonce');

// Prepare search component HTML
ob_start();
$search_component = new SAW_Component_Search('customers', array(
    'placeholder' => 'Hledat zákazníka...',
    'search_value' => $search,
    'ajax_enabled' => false,
    'ajax_action' => 'saw_search_customers',
    'show_button' => true,
    'show_info_banner' => true,
    'info_banner_label' => 'Vyhledávání:',
    'clear_url' => home_url('/admin/settings/customers/'),
));
$search_component->render();
$search_html = ob_get_clean();

// Prepare filter component HTML
ob_start();
if (!empty($this->config['list_config']['filters']['status'])) {
    $status_filter = new SAW_Component_Selectbox('status-filter', array(
        'options' => array(
            '' => 'Všechny statusy',
            'potential' => 'Potenciální',
            'active' => 'Aktivní',
            'inactive' => 'Neaktivní',
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
$table = new SAW_Component_Admin_Table('customers', [
    'title' => 'Zákazníci',
    'create_url' => home_url('/admin/settings/customers/new/'),
    'edit_url' => home_url('/admin/settings/customers/edit/{id}/'),
    
    'columns' => [
        'logo_url' => [
            'label' => 'Logo',
            'type' => 'image',
            'width' => '60px',
            'align' => 'center',
            'placeholder' => 'building'
        ],
        'name' => [
            'label' => 'Název',
            'type' => 'text',
            'sortable' => true,
            'bold' => true
        ],
        'ico' => [
            'label' => 'IČO',
            'type' => 'text'
        ],
        'status' => [
            'label' => 'Status',
            'type' => 'badge',
            'map' => [
                'active' => 'success',
                'inactive' => 'secondary',
                'potential' => 'warning'
            ],
            'labels' => [
                'active' => 'Aktivní',
                'inactive' => 'Neaktivní',
                'potential' => 'Potenciální'
            ]
        ],
        'subscription_type' => [
            'label' => 'Typ účtu',
            'type' => 'custom',
            'width' => '150px',
            'callback' => function($value) {
                if (empty($value)) {
                    return '<span class="saw-text-muted">—</span>';
                }
                
                global $wpdb;
                $type = $wpdb->get_row($wpdb->prepare(
                    "SELECT display_name, color FROM {$wpdb->prefix}saw_account_types WHERE id = %d",
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
        ],
        'primary_color' => [
            'label' => 'Barva',
            'type' => 'color_badge',
            'width' => '80px',
            'align' => 'center'
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
    'empty_message' => 'Žádní zákazníci nenalezeni',
    'add_new' => 'Nový zákazník',
    
    'enable_modal' => true,
    'modal_id' => 'customer-detail',
    'modal_ajax_action' => 'saw_get_customers_detail',
]);

// Render table
$table->render();

// Modal component
$customer_modal = new SAW_Component_Modal('customer-detail', array(
    'title' => 'Detail zákazníka',
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
            'confirm_message' => 'Opravdu chcete smazat tohoto zákazníka?',
            'ajax_action' => 'saw_delete_customers',
        ),
    ),
));
$customer_modal->render();
?>

<script>
window.sawAjaxNonce = '<?php echo $ajax_nonce; ?>';
</script>