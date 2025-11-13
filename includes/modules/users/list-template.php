<?php
/**
 * Users List Template - FINAL WORKING VERSION
 * 
 * @package SAW_Visitors
 * @version 5.3.0
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

// ONE FILTER ROW: [Role] [Status] [Search]
ob_start();
echo '<div style="display: flex; gap: 12px; align-items: flex-start;">';

echo '<div>';
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
    'name' => 'role',
));
$role_filter->render();
echo '</div>';

echo '<div>';
$status_filter = new SAW_Component_Selectbox('is_active-filter', array(
    'options' => array(
        '' => 'Všechny statusy',
        '1' => 'Aktivní',
        '0' => 'Neaktivní',
    ),
    'selected' => $_GET['is_active'] ?? '',
    'on_change' => 'redirect',
    'name' => 'is_active',
));
$status_filter->render();
echo '</div>';

echo '<div style="flex: 1;">';
$search_component = new SAW_Component_Search('users', array(
    'placeholder' => 'Hledat uživatele...',
    'search_value' => $search,
    'ajax_enabled' => false,
    'show_button' => true,
    'show_info_banner' => false,
    'clear_url' => home_url('/admin/users/'),
));
$search_component->render();
echo '</div>';

echo '</div>';
$filters_html = ob_get_clean();

global $wpdb;

echo '<div class="saw-module-users">';

$table = new SAW_Component_Admin_Table('users', [
    'title' => 'Uživatelé',
    'create_url' => home_url('/admin/users/new/'),
    'edit_url' => home_url('/admin/users/edit/{id}/'),
    'detail_url' => home_url('/admin/users/{id}/'),
    
    'module_config' => $this->config,
    'sidebar_mode' => $sidebar_mode ?? null,
    'detail_item' => $detail_item ?? null,
    'form_item' => $form_item ?? null,
    'detail_tab' => $detail_tab ?? 'overview',
    'related_data' => $related_data ?? null,
    'is_edit' => $is_edit ?? false,
    
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
        'position' => [
            'label' => 'Funkce',
            'type' => 'text',
            'width' => '180px',
            'empty_value' => '—'
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
    'filters' => $filters_html,
    'actions' => ['view', 'edit', 'delete'],
    'empty_message' => 'Žádní uživatelé nenalezeni',
    'add_new' => 'Nový uživatel',
    
    'ajax_enabled' => true,
    'ajax_nonce' => wp_create_nonce('saw_ajax_nonce'),
]);

$table->render();

echo '</div>';