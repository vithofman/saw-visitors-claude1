<?php
/**
 * Users List Template - REFACTORED v4.0.0
 * 
 * ✅ Uses SAW_Component_Admin_Table
 * ✅ Inline filters (side by side)
 * ✅ Float button for create
 * ✅ Modal detail
 * 
 * @package SAW_Visitors
 * @version 4.0.0
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
$search_component = new SAW_Component_Search('users', array(
    'placeholder' => 'Hledat uživatele...',
    'search_value' => $search,
    'ajax_enabled' => false,
    'ajax_action' => 'saw_search_users',
    'show_button' => true,
    'show_info_banner' => true,
    'info_banner_label' => 'Vyhledávání:',
    'clear_url' => home_url('/admin/users/'),
));
$search_component->render();
$search_html = ob_get_clean();

// Prepare filters - INLINE STYLE FOR SIDE BY SIDE
ob_start();
echo '<div style="display: flex; gap: 12px; flex-wrap: wrap;">';

// Role filter
echo '<div style="flex: 0 0 auto;">';
$role_filter = new SAW_Component_Selectbox('role-filter', array(
    'options' => array(
        '' => 'Všechny role',
        'admin' => 'Admin',
        'super_manager' => 'Super Manager',
        'manager' => 'Manager',
        'terminal' => 'Terminál',
    ),
    'selected' => $_GET['role'] ?? '',
    'on_change' => 'redirect',
    'allow_empty' => true,
    'custom_class' => 'saw-filter-select',
    'name' => 'role',
));
$role_filter->render();
echo '</div>';

// Status filter
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

echo '</div>';
$filters_html = ob_get_clean();

global $wpdb;

// Initialize admin table
$table = new SAW_Component_Admin_Table('users', [
    'title' => 'Uživatelé',
    'create_url' => home_url('/admin/users/new/'),
    'edit_url' => home_url('/admin/users/edit/{id}/'),
    
    'columns' => [
        'name' => [
            'label' => 'Jméno',
            'type' => 'custom',
            'sortable' => true,
            'bold' => true,
            'callback' => function($value, $item) {
                $html = '<span class="saw-user-icon">👤</span>';
                $html .= '<strong>' . esc_html($item['first_name'] . ' ' . $item['last_name']) . '</strong>';
                return $html;
            }
        ],
        'email' => [
            'label' => 'Email',
            'type' => 'custom',
            'callback' => function($value, $item) {
                if (!empty($item['wp_user_id'])) {
                    $wp_user = get_userdata($item['wp_user_id']);
                    $email = $wp_user ? $wp_user->user_email : 'N/A';
                    return esc_html($email);
                }
                return '<span class="saw-text-muted">—</span>';
            }
        ],
        'role' => [
            'label' => 'Role',
            'type' => 'custom',
            'width' => '150px',
            'callback' => function($value) {
                $role_labels = [
                    'admin' => 'Admin',
                    'super_manager' => 'Super Manager',
                    'manager' => 'Manager',
                    'terminal' => 'Terminál'
                ];
                $role_label = $role_labels[$value] ?? $value;
                
                return '<span class="saw-role-badge saw-role-' . esc_attr($value) . '">' . esc_html($role_label) . '</span>';
            }
        ],
        'branch' => [
            'label' => 'Pobočka',
            'type' => 'custom',
            'width' => '150px',
            'callback' => function($value, $item) use ($wpdb) {
                if (!empty($item['branch_id'])) {
                    $branch = $wpdb->get_row($wpdb->prepare(
                        "SELECT name FROM %i WHERE id = %d",
                        $wpdb->prefix . 'saw_branches',
                        $item['branch_id']
                    ), ARRAY_A);
                    
                    if ($branch) {
                        return esc_html($branch['name']);
                    }
                }
                return '<span class="saw-text-muted">—</span>';
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
        'last_login' => [
            'label' => 'Poslední přihlášení',
            'type' => 'custom',
            'width' => '150px',
            'callback' => function($value) {
                if (!empty($value)) {
                    return esc_html(date_i18n('j. n. Y H:i', strtotime($value)));
                }
                return '<span class="saw-text-muted">Nikdy</span>';
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
    'empty_message' => 'Žádní uživatelé nenalezeni',
    'add_new' => 'Nový uživatel',
    
    'enable_modal' => true,
    'modal_id' => 'user-detail',
    'modal_ajax_action' => 'saw_get_users_detail',
]);

$table->render();

// Modal component
$user_modal = new SAW_Component_Modal('user-detail', array(
    'title' => 'Detail uživatele',
    'ajax_enabled' => true,
    'ajax_action' => 'saw_get_users_detail',
    'size' => 'large',
    'show_close' => true,
    'close_on_backdrop' => true,
    'close_on_escape' => true,
    'header_actions' => array(
        array(
            'type' => 'edit',
            'label' => '',
            'icon' => 'dashicons-edit',
            'url' => home_url('/admin/users/edit/{id}/'),
        ),
        array(
            'type' => 'delete',
            'label' => '',
            'icon' => 'dashicons-trash',
            'confirm' => true,
            'confirm_message' => 'Opravdu chcete smazat tohoto uživatele?',
            'ajax_action' => 'saw_delete_users',
        ),
    ),
));
$user_modal->render();
