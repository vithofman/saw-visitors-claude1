<?php
/**
 * Departments List Template
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Departments
 * @version     7.0.0 - REFACTORED: Translations, tabs, infinite scroll
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
    ? saw_get_translations($lang, 'admin', 'departments') 
    : [];

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
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
$entity = $config['entity'] ?? 'departments';
$table_config = $config;
$base_url = home_url('/admin/' . ($config['route'] ?? 'departments'));

$table_config['title'] = $tr('title', 'Oddělení');
$table_config['create_url'] = $base_url . '/create';
$table_config['detail_url'] = $base_url . '/{id}/';
$table_config['edit_url'] = $base_url . '/{id}/edit';

// ============================================
// LOAD BRANCHES FOR FORM
// ============================================
global $wpdb;
$customer_id = SAW_Context::get_customer_id();
$branches = array();
if ($customer_id) {
    $branches_data = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name FROM {$wpdb->prefix}saw_branches WHERE customer_id = %d AND is_active = 1 ORDER BY name ASC",
        $customer_id
    ), ARRAY_A);
    foreach ($branches_data as $branch) {
        $branches[$branch['id']] = $branch['name'];
    }
}

// ============================================
// COLUMN DEFINITIONS
// ============================================
$table_config['columns'] = array(
    'department_number' => array(
        'label' => $tr('col_department_number', 'Číslo oddělení'),
        'type' => 'custom',
        'width' => '150px',
        'sortable' => true,
        'callback' => function($value) {
            if (empty($value)) return '<span class="saw-text-muted">—</span>';
            return sprintf('<span class="saw-code-badge">%s</span>', esc_html($value));
        }
    ),
    'name' => array(
        'label' => $tr('col_name', 'Název oddělení'),
        'type' => 'text',
        'sortable' => true,
        'class' => 'saw-table-cell-bold',
    ),
    'is_active' => array(
        'label' => $tr('col_status', 'Status'),
        'type' => 'custom',
        'width' => '100px',
        'align' => 'center',
        'callback' => function($value) use ($tr) {
            if (empty($value)) {
                return '<span class="saw-badge saw-badge-secondary">' 
                    . esc_html($tr('status_inactive', 'Neaktivní')) . '</span>';
            }
            return '<span class="saw-badge saw-badge-success">' 
                . esc_html($tr('status_active', 'Aktivní')) . '</span>';
        }
    ),
);

// ============================================
// DATA
// ============================================
$table_config['rows'] = $items ?? array();
$table_config['total_items'] = $total ?? 0;
$table_config['current_page'] = $page ?? 1;
$table_config['total_pages'] = $total_pages ?? 1;
$table_config['search_value'] = $search ?? '';
$table_config['orderby'] = $orderby ?? 'name';
$table_config['order'] = $order ?? 'ASC';

// ============================================
// SEARCH CONFIGURATION
// ============================================
$table_config['search'] = array(
    'enabled' => true,
    'placeholder' => $tr('search_placeholder', 'Hledat oddělení...'),
    'fields' => array('name', 'department_number', 'description'),
    'show_info_banner' => true,
);

// ============================================
// SIDEBAR CONTEXT
// ============================================
$table_config['sidebar_mode'] = $sidebar_mode ?? null;
$table_config['detail_item'] = $detail_item ?? null;
$table_config['form_item'] = $form_item ?? null;
$table_config['detail_tab'] = $detail_tab ?? 'overview';
$table_config['module_config'] = $config;
$table_config['related_data'] = $related_data ?? null;
$table_config['branches'] = $branches;

// ============================================
// ACTIONS
// ============================================
$table_config['actions'] = array('view', 'edit', 'delete');
$table_config['add_new'] = $tr('add_new', 'Nové oddělení');
$table_config['empty_message'] = $tr('empty_message', 'Žádná oddělení nenalezena');

// ============================================
// TABS - Override labels from translations
// ============================================
$table_config['tabs'] = $config['tabs'] ?? null;

if (!empty($table_config['tabs']['tabs'])) {
    if (isset($table_config['tabs']['tabs']['all'])) {
        $table_config['tabs']['tabs']['all']['label'] = $tr('tab_all', 'Všechna');
    }
    if (isset($table_config['tabs']['tabs']['active'])) {
        $table_config['tabs']['tabs']['active']['label'] = $tr('tab_active', 'Aktivní');
    }
    if (isset($table_config['tabs']['tabs']['inactive'])) {
        $table_config['tabs']['tabs']['inactive']['label'] = $tr('tab_inactive', 'Neaktivní');
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

// Current tab
if (!empty($table_config['tabs']['enabled'])) {
    $table_config['current_tab'] = (isset($current_tab) && $current_tab !== null && $current_tab !== '') 
        ? (string)$current_tab 
        : ($table_config['tabs']['default_tab'] ?? 'all');
    $table_config['tab_counts'] = (isset($tab_counts) && is_array($tab_counts)) ? $tab_counts : array();
}

// ============================================
// RENDER
// ============================================
$table = new SAW_Component_Admin_Table($entity, $table_config);
$table->render();