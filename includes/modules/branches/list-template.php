<?php
/**
 * Branches List Template
 * 
 * REFACTORED v3.0.0:
 * ✅ Filters side by side (inline display)
 * ✅ Uses SAW_Component_Admin_Table
 * ✅ All values escaped
 * 
 * @package SAW_Visitors
 * @version 3.0.0
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
$search_component = new SAW_Component_Search('branches', array(
    'placeholder' => 'Hledat pobočku...',
    'search_value' => $search,
    'ajax_enabled' => false,
    'ajax_action' => 'saw_search_branches',
    'show_button' => true,
    'show_info_banner' => true,
    'info_banner_label' => 'Vyhledávání:',
    'clear_url' => home_url('/admin/branches/'),
));
$search_component->render();
$search_html = ob_get_clean();

// Prepare filters - INLINE STYLE FOR SIDE BY SIDE
ob_start();
echo '<div style="display: flex; gap: 12px; flex-wrap: wrap;">';

// Status filter
if (!empty($this->config['list_config']['filters']['is_active'])) {
    echo '<div style="flex: 0 0 auto;">';
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
    echo '</div>';
}

// Headquarters filter
if (!empty($this->config['list_config']['filters']['is_headquarters'])) {
    echo '<div style="flex: 0 0 auto;">';
    $headquarters_filter = new SAW_Component_Selectbox('is_headquarters-filter', array(
        'options' => array(
            '' => 'Všechny pobočky',
            '1' => 'Jen hlavní sídla',
            '0' => 'Bez hlavních sídel',
        ),
        'selected' => $_GET['is_headquarters'] ?? '',
        'on_change' => 'redirect',
        'allow_empty' => true,
        'custom_class' => 'saw-filter-select',
        'name' => 'is_headquarters',
    ));
    $headquarters_filter->render();
    echo '</div>';
}

echo '</div>';
$filters_html = ob_get_clean();

// Initialize admin table
$table = new SAW_Component_Admin_Table('branches', [
    'title' => 'Pobočky',
    'create_url' => home_url('/admin/branches/new/'),
    'edit_url' => home_url('/admin/branches/edit/{id}/'),
    
    'columns' => [
        'name' => [
            'label' => 'Název pobočky',
            'type' => 'custom',
            'sortable' => true,
            'bold' => true,
            'callback' => function($value, $item) {
                $html = '';
                
                // Thumbnail or icon
                if (!empty($item['image_thumbnail'])) {
                    $html .= '<img src="' . esc_url($item['image_thumbnail']) . '" alt="' . esc_attr($value) . '" class="saw-branch-thumbnail">';
                } else {
                    $html .= '<span class="saw-branch-icon">🏢</span>';
                }
                
                // Name
                $html .= '<strong>' . esc_html($value) . '</strong>';
                
                // Headquarters badge
                if (!empty($item['is_headquarters'])) {
                    $html .= ' <span class="saw-badge saw-badge-info saw-badge-sm">HQ</span>';
                }
                
                return $html;
            }
        ],
        'code' => [
            'label' => 'Kód',
            'type' => 'custom',
            'width' => '100px',
            'sortable' => true,
            'callback' => function($value) {
                if (!empty($value)) {
                    return '<span class="saw-code-badge">' . esc_html($value) . '</span>';
                }
                return '<span class="saw-text-muted">—</span>';
            }
        ],
        'city' => [
            'label' => 'Město',
            'type' => 'text',
            'width' => '150px',
            'sortable' => true
        ],
        'phone' => [
            'label' => 'Telefon',
            'type' => 'custom',
            'width' => '120px',
            'callback' => function($value) {
                if (!empty($value)) {
                    return '<a href="tel:' . esc_attr($value) . '" class="saw-phone-link" onclick="event.stopPropagation();">' . esc_html($value) . '</a>';
                }
                return '<span class="saw-text-muted">—</span>';
            }
        ],
        'is_headquarters' => [
            'label' => 'Hlavní',
            'type' => 'badge',
            'width' => '100px',
            'align' => 'center',
            'map' => [
                '1' => 'info',
                '0' => 'secondary'
            ],
            'labels' => [
                '1' => 'Ano',
                '0' => 'Ne'
            ]
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
        'sort_order' => [
            'label' => 'Pořadí',
            'type' => 'custom',
            'width' => '80px',
            'align' => 'center',
            'sortable' => true,
            'callback' => function($value) {
                return '<span class="saw-sort-order-badge">' . esc_html($value ?? 0) . '</span>';
            }
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
    'empty_message' => 'Žádné pobočky nenalezeny',
    'add_new' => 'Nová pobočka',
    
    'enable_modal' => true,
    'modal_id' => 'branch-detail',
    'modal_ajax_action' => 'saw_get_branches_detail',
]);

$table->render();

// Modal component
$branch_modal = new SAW_Component_Modal('branch-detail', array(
    'title' => 'Detail pobočky',
    'ajax_enabled' => true,
    'ajax_action' => 'saw_get_branches_detail',
    'size' => 'large',
    'show_close' => true,
    'close_on_backdrop' => true,
    'close_on_escape' => true,
    'header_actions' => array(
        array(
            'type' => 'edit',
            'label' => '',
            'icon' => 'dashicons-edit',
            'url' => home_url('/admin/branches/edit/{id}/'),
        ),
        array(
            'type' => 'delete',
            'label' => '',
            'icon' => 'dashicons-trash',
            'confirm' => true,
            'confirm_message' => 'Opravdu chcete smazat tuto pobočku?',
            'ajax_action' => 'saw_delete_branches',
        ),
    ),
));
$branch_modal->render();