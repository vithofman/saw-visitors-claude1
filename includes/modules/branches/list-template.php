<?php
/**
 * Branches List Template
 * 
 * @package SAW_Visitors
 * @version 3.1.0 - Permissions Fix
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

// Prepare filters
ob_start();
echo '<div style="display: flex; gap: 12px; flex-wrap: wrap;">';

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

// Check permissions for actions
$can_create = $this->can('create');
$can_edit = $this->can('edit');
$can_delete = $this->can('delete');
$can_view = $this->can('view');

// Build actions array
$actions = [];
if ($can_edit) {
    $actions[] = 'edit';
}
if ($can_delete) {
    $actions[] = 'delete';
}

// Initialize admin table
$table = new SAW_Component_Admin_Table('branches', [
    'title' => 'Pobočky',
    'create_url' => $can_create ? home_url('/admin/branches/new/') : null,
    'edit_url' => $can_edit ? home_url('/admin/branches/edit/{id}/') : null,
    
    'columns' => [
        'name' => [
            'label' => 'Název pobočky',
            'type' => 'custom',
            'sortable' => true,
            'bold' => true,
            'callback' => function($value, $item) {
                $html = '';
                
                if (!empty($item['image_thumbnail'])) {
                    $html .= '<img src="' . esc_url($item['image_thumbnail']) . '" alt="' . esc_attr($value) . '" class="saw-branch-thumbnail">';
                } else {
                    $html .= '<span class="saw-branch-icon">🏢</span>';
                }
                
                $html .= '<strong>' . esc_html($value) . '</strong>';
                
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
    'actions' => $actions,
    'empty_message' => 'Žádné pobočky nenalezeny',
    'add_new' => $can_create ? 'Nová pobočka' : null,
    
    'enable_modal' => $can_view,
    'modal_id' => 'branch-detail',
    'modal_ajax_action' => 'saw_get_branches_detail',
]);

$table->render();

// Modal component (only if user can view details)
if ($can_view) {
    $modal_actions = [];
    
    if ($can_edit) {
        $modal_actions[] = [
            'type' => 'edit',
            'label' => '',
            'icon' => 'dashicons-edit',
            'url' => home_url('/admin/branches/edit/{id}/'),
        ];
    }
    
    if ($can_delete) {
        $modal_actions[] = [
            'type' => 'delete',
            'label' => '',
            'icon' => 'dashicons-trash',
            'confirm' => true,
            'confirm_message' => 'Opravdu chcete smazat tuto pobočku?',
            'ajax_action' => 'saw_delete_branches',
        ];
    }
    
    $branch_modal = new SAW_Component_Modal('branch-detail', array(
        'title' => 'Detail pobočky',
        'ajax_enabled' => true,
        'ajax_action' => 'saw_get_branches_detail',
        'size' => 'large',
        'show_close' => true,
        'close_on_backdrop' => true,
        'close_on_escape' => true,
        'header_actions' => $modal_actions,
    ));
    $branch_modal->render();
}