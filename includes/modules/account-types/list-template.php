<?php
/**
 * Account Types List Template
 * 
 * Displays paginated list of account types with:
 * - Search functionality
 * - Active/Inactive filter
 * - Sortable columns
 * - Color badge display
 * - Price formatting (Kč/měsíc or "Zdarma")
 * - Features count badge
 * - Sort order display
 * - AJAX modal for detail view
 * - Edit/Delete actions
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes/Templates
 * @since       1.0.0
 * @version     7.2.0
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

// Prepare search component HTML
ob_start();
$search_component = new SAW_Component_Search('account-types', array(
    'placeholder' => __('Hledat typ účtu...', 'saw-visitors'),
    'search_value' => $search,
    'ajax_enabled' => false,
    'ajax_action' => 'saw_search_account_types',
    'show_button' => true,
    'show_info_banner' => true,
    'info_banner_label' => __('Vyhledávání:', 'saw-visitors'),
    'clear_url' => home_url('/admin/settings/account-types/'),
));
$search_component->render();
$search_html = ob_get_clean();

// Prepare filter component HTML
ob_start();
if (!empty($this->config['list_config']['filters']['is_active'])) {
    $status_filter = new SAW_Component_Selectbox('is_active-filter', array(
        'options' => array(
            '' => __('Všechny statusy', 'saw-visitors'),
            '1' => __('Aktivní', 'saw-visitors'),
            '0' => __('Neaktivní', 'saw-visitors'),
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

// Initialize admin table component
$table = new SAW_Component_Admin_Table('account-types', array(
    'title' => __('Typy účtu', 'saw-visitors'),
    'create_url' => home_url('/admin/settings/account-types/new/'),
    'edit_url' => home_url('/admin/settings/account-types/edit/{id}/'),
    
    'columns' => array(
        'color' => array(
            'label' => __('Barva', 'saw-visitors'),
            'type' => 'color_badge',
            'width' => '80px',
            'align' => 'center'
        ),
        'display_name' => array(
            'label' => __('Název', 'saw-visitors'),
            'type' => 'text',
            'sortable' => true,
            'bold' => true
        ),
        'name' => array(
            'label' => __('Interní název', 'saw-visitors'),
            'type' => 'custom',
            'callback' => function($value) {
                return '<span class="saw-code-badge">' . esc_html($value) . '</span>';
            }
        ),
        'price' => array(
            'label' => __('Cena', 'saw-visitors'),
            'type' => 'custom',
            'align' => 'right',
            'width' => '120px',
            'callback' => function($value) {
                $price = floatval($value ?? 0);
                if ($price > 0) {
                    return number_format($price, 2, ',', ' ') . ' ' . __('Kč/měsíc', 'saw-visitors');
                }
                return '<span class="saw-text-muted">' . esc_html__('Zdarma', 'saw-visitors') . '</span>';
            }
        ),
        'features' => array(
            'label' => __('Funkce', 'saw-visitors'),
            'type' => 'custom',
            'align' => 'center',
            'width' => '100px',
            'callback' => function($value) {
                $features = !empty($value) ? json_decode($value, true) : array();
                $count = is_array($features) ? count($features) : 0;
                return '<span class="saw-badge saw-badge-info">' . $count . ' ' . esc_html__('funkcí', 'saw-visitors') . '</span>';
            }
        ),
        'sort_order' => array(
            'label' => __('Pořadí', 'saw-visitors'),
            'type' => 'custom',
            'align' => 'center',
            'width' => '100px',
            'callback' => function($value) {
                return '<span class="saw-sort-order-badge">' . esc_html($value ?? 0) . '</span>';
            }
        ),
        'is_active' => array(
            'label' => __('Status', 'saw-visitors'),
            'type' => 'badge',
            'width' => '100px',
            'align' => 'center',
            'map' => array(
                '1' => 'success',
                '0' => 'secondary'
            ),
            'labels' => array(
                '1' => __('Aktivní', 'saw-visitors'),
                '0' => __('Neaktivní', 'saw-visitors')
            )
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
    'empty_message' => __('Žádné typy účtu nenalezeny', 'saw-visitors'),
    'add_new' => __('Nový typ účtu', 'saw-visitors'),
    
    'enable_modal' => true,
    'modal_id' => 'account-type-detail',
    'modal_ajax_action' => 'saw_get_account_types_detail',
));

// Render table
$table->render();

// Modal component
$account_type_modal = new SAW_Component_Modal('account-type-detail', array(
    'title' => __('Detail typu účtu', 'saw-visitors'),
    'ajax_enabled' => true,
    'ajax_action' => 'saw_get_account_types_detail',
    'size' => 'large',
    'show_close' => true,
    'close_on_backdrop' => true,
    'close_on_escape' => true,
    'header_actions' => array(
        array(
            'type' => 'edit',
            'label' => '',
            'icon' => 'dashicons-edit',
            'url' => home_url('/admin/settings/account-types/edit/{id}/'),
        ),
        array(
            'type' => 'delete',
            'label' => '',
            'icon' => 'dashicons-trash',
            'confirm' => true,
            'confirm_message' => __('Opravdu chcete smazat tento typ účtu?', 'saw-visitors'),
            'ajax_action' => 'saw_delete_account_types',
        ),
    ),
));
$account_type_modal->render();