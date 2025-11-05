<?php
/**
 * Account Types List Template
 * 
 * @package SAW_Visitors
 * @version 7.2.0 - FIXED: Correct AJAX action names
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

// Prepare search component HTML
ob_start();
$search_component = new SAW_Component_Search('account-types', array(
    'placeholder' => 'Hledat typ účtu...',
    'search_value' => $search,
    'ajax_enabled' => false,
    'ajax_action' => 'saw_search_account_types',
    'show_button' => true,
    'show_info_banner' => true,
    'info_banner_label' => 'Vyhledávání:',
    'clear_url' => home_url('/admin/settings/account-types/'),
));
$search_component->render();
$search_html = ob_get_clean();

// Prepare filter component HTML
ob_start();
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

// Initialize admin table component
$table = new SAW_Component_Admin_Table('account-types', [
    'title' => 'Typy účtu',
    'create_url' => home_url('/admin/settings/account-types/new/'),
    'edit_url' => home_url('/admin/settings/account-types/edit/{id}/'),
    
    'columns' => [
        'color' => [
            'label' => 'Barva',
            'type' => 'color_badge',
            'width' => '80px',
            'align' => 'center'
        ],
        'display_name' => [
            'label' => 'Název',
            'type' => 'text',
            'sortable' => true,
            'bold' => true
        ],
        'name' => [
            'label' => 'Interní název',
            'type' => 'custom',
            'callback' => function($value) {
                return '<span class="saw-code-badge">' . esc_html($value) . '</span>';
            }
        ],
        'price' => [
            'label' => 'Cena',
            'type' => 'custom',
            'align' => 'right',
            'width' => '120px',
            'callback' => function($value) {
                $price = floatval($value ?? 0);
                if ($price > 0) {
                    return number_format($price, 2, ',', ' ') . ' Kč/měsíc';
                }
                return '<span class="saw-text-muted">Zdarma</span>';
            }
        ],
        'features' => [
            'label' => 'Funkce',
            'type' => 'custom',
            'align' => 'center',
            'width' => '100px',
            'callback' => function($value) {
                $features = !empty($value) ? json_decode($value, true) : [];
                $count = is_array($features) ? count($features) : 0;
                return '<span class="saw-badge saw-badge-info">' . $count . ' funkcí</span>';
            }
        ],
        'sort_order' => [
            'label' => 'Pořadí',
            'type' => 'custom',
            'align' => 'center',
            'width' => '100px',
            'callback' => function($value) {
                return '<span class="saw-sort-order-badge">' . esc_html($value ?? 0) . '</span>';
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
    'empty_message' => 'Žádné typy účtu nenalezeny',
    'add_new' => 'Nový typ účtu',
    
    'enable_modal' => true,
    'modal_id' => 'account-type-detail',
    'modal_ajax_action' => 'saw_get_account_types_detail',
]);

// Render table
$table->render();

// Modal component
$account_type_modal = new SAW_Component_Modal('account-type-detail', array(
    'title' => 'Detail typu účtu',
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
            'confirm_message' => 'Opravdu chcete smazat tento typ účtu?',
            'ajax_action' => 'saw_delete_account_types',
        ),
    ),
));
$account_type_modal->render();