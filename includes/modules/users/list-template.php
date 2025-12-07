<?php
/**
 * Users List Template
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Users
 * @version     6.3.0 - FIXED: Filters as config object (like visits)
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// LOAD TRANSLATIONS
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}
$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'users') 
    : array();

$tr = function($key, $fallback = null) use ($t) {
    return isset($t[$key]) ? $t[$key] : ($fallback !== null ? $fallback : $key);
};

// ============================================
// ENSURE COMPONENTS ARE LOADED
// ============================================
if (!class_exists('SAW_Component_Admin_Table')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';
}

// ============================================
// PREPARE CONFIG FOR ADMIN TABLE
// ============================================
$entity = isset($config['entity']) ? $config['entity'] : 'users';
$table_config = $config;
$base_url = home_url('/admin/' . (isset($config['route']) ? $config['route'] : 'users'));

$table_config['title'] = $tr('entity_title', 'Uživatelé');
$table_config['create_url'] = $base_url . '/create';
$table_config['detail_url'] = $base_url . '/{id}/';
$table_config['edit_url'] = $base_url . '/{id}/edit';

// ============================================
// CONTEXT
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
// SEARCH CONFIGURATION
// ============================================
$table_config['search'] = array(
    'enabled' => true,
    'placeholder' => $tr('search_placeholder', 'Hledat uživatele...'),
    'fields' => array('first_name', 'last_name', 'position'),
    'show_info_banner' => true,
);

// ============================================
// FILTERS CONFIGURATION (like visits module)
// ============================================
$table_config['filters'] = array(
    'branch_id' => array(
        'type' => 'select',
        'label' => $tr('col_branch', 'Pobočka'),
        'options' => $branch_options,
    ),
    'department_id' => array(
        'type' => 'select',
        'label' => $tr('col_departments', 'Oddělení'),
        'options' => $department_options,
    ),
    'is_active' => array(
        'type' => 'select',
        'label' => $tr('col_status', 'Status'),
        'options' => array(
            '' => $tr('filter_all_statuses', 'Všechny statusy'),
            '1' => $tr('status_active', 'Aktivní'),
            '0' => $tr('status_inactive', 'Neaktivní'),
        ),
    ),
);

// ============================================
// COLUMN DEFINITIONS
// ============================================
$table_config['columns'] = array(
    'name' => array(
        'label' => $tr('col_name', 'Jméno'),
        'type' => 'custom',
        'sortable' => true,
        'sort_field' => 'last_name',
        'class' => 'saw-table-cell-bold',
        'callback' => function($value, $item) {
            $html = '<span class="saw-user-icon">👤</span>';
            $html .= '<strong>' . esc_html(trim($item['first_name'] . ' ' . $item['last_name'])) . '</strong>';
            return $html;
        }
    ),
    'position' => array(
        'label' => $tr('col_position', 'Funkce'),
        'type' => 'custom',
        'width' => '180px',
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
        'width' => '140px',
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
        'width' => '150px',
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
        'width' => '100px',
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
        'type' => 'custom',
        'width' => '100px',
        'align' => 'center',
        'callback' => function($value) use ($tr) {
            if (!empty($value)) {
                return '<span class="saw-badge saw-badge-success">' . esc_html($tr('status_active', 'Aktivní')) . '</span>';
            }
            return '<span class="saw-badge saw-badge-secondary">' . esc_html($tr('status_inactive', 'Neaktivní')) . '</span>';
        }
    ),
    'last_login' => array(
        'label' => $tr('col_last_login', 'Poslední přihlášení'),
        'type' => 'custom',
        'width' => '140px',
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

// Hide role column if on specific role tab
$current_tab_value = isset($current_tab) ? $current_tab : 'all';
if ($current_tab_value !== 'all' && $current_tab_value !== '') {
    unset($table_config['columns']['role']);
}

// ============================================
// DATA
// ============================================
$table_config['rows'] = isset($items) ? $items : array();
$table_config['total_items'] = isset($total) ? $total : 0;
$table_config['current_page'] = isset($page) ? $page : 1;
$table_config['total_pages'] = isset($total_pages) ? $total_pages : 1;
$table_config['search_value'] = isset($search) ? $search : '';
$table_config['orderby'] = isset($orderby) ? $orderby : 'last_name';
$table_config['order'] = isset($order) ? $order : 'ASC';

// ============================================
// SIDEBAR CONTEXT
// ============================================
$table_config['sidebar_mode'] = isset($sidebar_mode) ? $sidebar_mode : null;
$table_config['detail_item'] = isset($detail_item) ? $detail_item : null;
$table_config['form_item'] = isset($form_item) ? $form_item : null;
$table_config['detail_tab'] = isset($detail_tab) ? $detail_tab : 'overview';
$table_config['module_config'] = $config;
$table_config['related_data'] = isset($related_data) ? $related_data : null;

// ============================================
// ACTIONS
// ============================================
$table_config['actions'] = array('view', 'edit', 'delete');
$table_config['add_new'] = $tr('action_add_new', 'Nový uživatel');
$table_config['empty_message'] = $tr('empty_message', 'Žádní uživatelé nenalezeni');

// ============================================
// TABS
// ============================================
$table_config['tabs'] = isset($config['tabs']) ? $config['tabs'] : null;

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

// ============================================
// INFINITE SCROLL
// ============================================
$table_config['infinite_scroll'] = array(
    'enabled' => true,
    'initial_load' => 100,
    'per_page' => 50,
    'threshold' => 0.6,
);

// ============================================
// CURRENT TAB & TAB COUNTS
// ============================================
if (!empty($table_config['tabs']['enabled'])) {
    $table_config['current_tab'] = (isset($current_tab) && $current_tab !== null && $current_tab !== '') 
        ? (string)$current_tab 
        : (isset($table_config['tabs']['default_tab']) ? $table_config['tabs']['default_tab'] : 'all');
    $table_config['tab_counts'] = (isset($tab_counts) && is_array($tab_counts)) ? $tab_counts : array();
}

// ============================================
// RENDER
// ============================================
$table = new SAW_Component_Admin_Table($entity, $table_config);
$table->render();