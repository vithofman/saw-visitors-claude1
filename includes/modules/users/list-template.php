<?php
/**
 * Users List Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Users
 * @version     2.0.0 - Refactored: Fixed column widths, infinite scroll
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// TRANSLATIONS
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}
$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'users') 
    : [];

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// COMPONENT LOADING
// ============================================
if (!class_exists('SAW_Component_Admin_Table')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';
}

// ============================================
// DATA FROM CONTROLLER
// ============================================
$items = $items ?? array();
$total = $total ?? 0;
$page = $page ?? 1;
$total_pages = $total_pages ?? 0;
$search = $search ?? '';
$orderby = $orderby ?? 'last_name';
$order = $order ?? 'ASC';

// ============================================
// CONTEXT & LOAD DATA FOR FILTERS
// ============================================
global $wpdb;
$customer_id = SAW_Context::get_customer_id();
$context_branch_id = SAW_Context::get_branch_id();

// ============================================
// LOAD BRANCHES FOR FILTER
// ============================================
$branch_options = array('' => $tr('filter_all_branches', 'Všechny pobočky'));

if ($customer_id) {
    $branches_data = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name FROM {$wpdb->prefix}saw_branches WHERE customer_id = %d AND is_active = 1 ORDER BY name ASC",
        $customer_id
    ), ARRAY_A);
    if ($branches_data) {
        foreach ($branches_data as $branch) {
            $branch_options[$branch['id']] = $branch['name'];
        }
    }
}

// ============================================
// LOAD DEPARTMENTS FOR FILTER
// ============================================
$department_options = array('' => $tr('filter_all_departments', 'Všechna oddělení'));

// Get filter branch or context branch
$filter_branch_id = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;
$dept_branch_id = $filter_branch_id ? $filter_branch_id : ($context_branch_id ? $context_branch_id : 0);

if ($dept_branch_id) {
    // Departments for specific branch
    $departments_data = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name FROM {$wpdb->prefix}saw_departments WHERE branch_id = %d AND is_active = 1 ORDER BY name ASC",
        $dept_branch_id
    ), ARRAY_A);
    if ($departments_data) {
        foreach ($departments_data as $dept) {
            $department_options[$dept['id']] = $dept['name'];
        }
    }
} elseif ($customer_id) {
    // All departments for customer (grouped by branch)
    $departments_data = $wpdb->get_results($wpdb->prepare(
        "SELECT d.id, d.name, b.name as branch_name
         FROM {$wpdb->prefix}saw_departments d
         JOIN {$wpdb->prefix}saw_branches b ON d.branch_id = b.id
         WHERE b.customer_id = %d AND d.is_active = 1
         ORDER BY b.name ASC, d.name ASC",
        $customer_id
    ), ARRAY_A);
    if ($departments_data) {
        foreach ($departments_data as $dept) {
            $label = $dept['name'];
            if (!empty($dept['branch_name'])) {
                $label = $dept['branch_name'] . ' → ' . $dept['name'];
            }
            $department_options[$dept['id']] = $label;
        }
    }
}

// ============================================
// TABLE CONFIGURATION
// ============================================
$table_config = array(
    'title' => $tr('entity_title', 'Uživatelé'),
    'create_url' => home_url('/admin/users/create'),
    'edit_url' => home_url('/admin/users/{id}/edit'),
    'detail_url' => home_url('/admin/users/{id}/'),
    
    'sidebar_mode' => $sidebar_mode ?? null,
    'detail_item' => $detail_item ?? null,
    'form_item' => $form_item ?? null,
    'detail_tab' => $detail_tab ?? 'overview',
    'related_data' => $related_data ?? null,
    
    'module_config' => isset($config) ? $config : array(),
    
    'rows' => $items,
    'total_items' => $total,
    'current_page' => $page,
    'total_pages' => $total_pages,
    'orderby' => $orderby,
    'order' => $order,
    
    'actions' => array('view', 'edit', 'delete'),
    'empty_message' => $tr('empty_message', 'Žádní uživatelé nenalezeni'),
    'add_new' => $tr('action_add_new', 'Nový uživatel'),
);

// ============================================
// SEARCH CONFIGURATION
// ============================================
$table_config['search'] = array(
    'enabled' => true,
    'placeholder' => $tr('search_placeholder', 'Hledat uživatele...'),
    'fields' => array('first_name', 'last_name', 'position'),
    'show_info_banner' => true,
);

// ============================================
// FILTERS CONFIGURATION
// ============================================
$table_config['filters'] = array(
    'branch_id' => array(
        'label' => $tr('col_branch', 'Pobočka'),
        'type' => 'select',
        'options' => $branch_options,
    ),
    'department_id' => array(
        'label' => $tr('col_departments', 'Oddělení'),
        'type' => 'select',
        'options' => $department_options,
    ),
    'is_active' => array(
        'label' => $tr('col_status', 'Status'),
        'type' => 'select',
        'options' => array(
            '' => $tr('filter_all_statuses', 'Všechny statusy'),
            '1' => $tr('status_active', 'Aktivní'),
            '0' => $tr('status_inactive', 'Neaktivní'),
        ),
    ),
);

// ============================================
// COLUMNS CONFIGURATION - ŠÍŘKY V PROCENTECH
// ============================================
$table_config['columns'] = array(
    'name' => array(
        'label' => $tr('col_name', 'Jméno'),
        'type' => 'custom',
        'sortable' => true,
        'sort_field' => 'last_name',
        'class' => 'saw-table-cell-bold',
        'width' => '22%',  // Hlavní identifikátor
        'callback' => function($value, $item) {
            $html = '<span class="saw-user-icon">👤</span>';
            $html .= '<strong>' . esc_html(trim($item['first_name'] . ' ' . $item['last_name'])) . '</strong>';
            return $html;
        }
    ),
    'position' => array(
        'label' => $tr('col_position', 'Funkce'),
        'type' => 'custom',
        'width' => '14%',
        'callback' => function($value, $item) {
            if (!empty($item['position'])) {
                return esc_html($item['position']);
            }
            return '<span class="saw-text-muted">—</span>';
        }
    ),
    'email' => array(
        'label' => $tr('col_email', 'Email'),
        'type' => 'custom',
        'width' => '16%',
        'callback' => function($value, $item) {
            if (!empty($item['wp_user_id'])) {
                $wp_user = get_userdata($item['wp_user_id']);
                if ($wp_user) {
                    return '<a href="mailto:' . esc_attr($wp_user->user_email) . '">' . esc_html($wp_user->user_email) . '</a>';
                }
            }
            return '<span class="saw-text-muted">—</span>';
        }
    ),
    'role' => array(
        'label' => $tr('col_role', 'Role'),
        'type' => 'custom',
        'width' => '12%',  // Badge-like
        'callback' => function($value) use ($tr) {
            $role_labels = array(
                'super_admin' => $tr('role_super_admin', 'Super Admin'),
                'admin' => $tr('role_admin', 'Admin'),
                'super_manager' => $tr('role_super_manager', 'Super Manager'),
                'manager' => $tr('role_manager', 'Manager'),
                'terminal' => $tr('role_terminal', 'Terminál'),
            );
            $role_label = isset($role_labels[$value]) ? $role_labels[$value] : $value;
            return '<span class="saw-role-badge saw-role-' . esc_attr($value) . '">' . esc_html($role_label) . '</span>';
        }
    ),
    'branch' => array(
        'label' => $tr('col_branch', 'Pobočka'),
        'type' => 'custom',
        'width' => '12%',
        'callback' => function($value, $item) {
            global $wpdb;
            if (!empty($item['branch_id'])) {
                $branch = $wpdb->get_row($wpdb->prepare(
                    "SELECT name, code FROM {$wpdb->prefix}saw_branches WHERE id = %d",
                    $item['branch_id']
                ), ARRAY_A);
                
                if ($branch) {
                    $display = esc_html($branch['name']);
                    if (!empty($branch['code'])) {
                        $display .= ' <span class="saw-text-muted">(' . esc_html($branch['code']) . ')</span>';
                    }
                    return $display;
                }
            }
            return '<span class="saw-text-muted">—</span>';
        }
    ),
    'departments' => array(
        'label' => $tr('col_departments', 'Oddělení'),
        'type' => 'custom',
        'width' => '8%',   // Badge malý
        'align' => 'center',
        'callback' => function($value, $item) {
            global $wpdb;
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}saw_user_departments WHERE user_id = %d",
                $item['id']
            ));
            
            if ($count > 0) {
                return '<span class="saw-badge saw-badge-info">' . intval($count) . '</span>';
            }
            return '<span class="saw-text-muted">—</span>';
        }
    ),
    'is_active' => array(
        'label' => $tr('col_status', 'Status'),
        'type' => 'badge',
        'sortable' => true,
        'width' => '8%',   // Badge malý
        'align' => 'center',
        'map' => array(
            '1' => 'success',
            '0' => 'secondary',
        ),
        'labels' => array(
            '1' => $tr('status_active', 'Aktivní'),
            '0' => $tr('status_inactive', 'Neaktivní'),
        ),
    ),
    'last_login' => array(
        'label' => $tr('col_last_login', 'Poslední přihlášení'),
        'type' => 'custom',
        'width' => '8%',   // Date/custom
        'sortable' => true,
        'callback' => function($value) use ($tr) {
            if (!empty($value)) {
                $timestamp = strtotime($value);
                $diff = time() - $timestamp;
                
                if ($diff < 86400) {
                    $hours = floor($diff / 3600);
                    if ($hours < 1) {
                        return '<span class="saw-text-success">● právě teď</span>';
                    }
                    return '<span class="saw-text-success">● před ' . intval($hours) . ' h</span>';
                }
                
                if ($diff < 604800) {
                    $days = floor($diff / 86400);
                    return '<span class="saw-text-info">● před ' . intval($days) . ' dny</span>';
                }
                
                return esc_html(date_i18n('d.m.Y', $timestamp));
            }
            return '<span class="saw-text-muted">' . esc_html($tr('status_never_logged_in', 'Nikdy')) . '</span>';
        }
    ),
);
// Součet: 22 + 14 + 16 + 12 + 12 + 8 + 8 + 8 = 100%

// Hide role column if on specific role tab
$current_tab_value = $current_tab ?? 'all';
if ($current_tab_value !== 'all' && $current_tab_value !== '') {
    unset($table_config['columns']['role']);
}

// ============================================
// TABS CONFIGURATION
// ============================================
$table_config['tabs'] = $config['tabs'] ?? null;

if (!empty($table_config['tabs']['enabled'])) {
    // Přepsat labels z configu překlady
    if (!empty($table_config['tabs']['tabs'])) {
        if (isset($table_config['tabs']['tabs']['all'])) {
            $table_config['tabs']['tabs']['all']['label'] = $tr('tab_all', 'Všichni');
        }
        if (isset($table_config['tabs']['tabs']['admin'])) {
            $table_config['tabs']['tabs']['admin']['label'] = $tr('tab_admin', 'Admini');
        }
        if (isset($table_config['tabs']['tabs']['super_manager'])) {
            $table_config['tabs']['tabs']['super_manager']['label'] = $tr('tab_super_manager', 'Super Manageři');
        }
        if (isset($table_config['tabs']['tabs']['manager'])) {
            $table_config['tabs']['tabs']['manager']['label'] = $tr('tab_manager', 'Manageři');
        }
        if (isset($table_config['tabs']['tabs']['terminal'])) {
            $table_config['tabs']['tabs']['terminal']['label'] = $tr('tab_terminal', 'Terminály');
        }
    }
    
    $table_config['current_tab'] = (isset($current_tab) && $current_tab !== null && $current_tab !== '') 
        ? (string)$current_tab 
        : ($table_config['tabs']['default_tab'] ?? 'all');
    $table_config['tab_counts'] = (isset($tab_counts) && is_array($tab_counts)) ? $tab_counts : array();
}

// ============================================
// INFINITE SCROLL CONFIGURATION
// ============================================
$table_config['infinite_scroll'] = array(
    'enabled' => true,
    'initial_load' => 100,
    'per_page' => 50,
    'threshold' => 0.6,
);

// ============================================
// RENDER TABLE
// ============================================
$table = new SAW_Component_Admin_Table('users', $table_config);
$table->render();