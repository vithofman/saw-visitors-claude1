<?php
/**
 * Customers List Template
 * 
 * Modern list view with:
 * - Tabs by account type (dynamically loaded)
 * - Status filter
 * - Infinite scroll
 * - Translation system
 * 
 * Pattern: Matches users/companies/oopp modules
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Customers/Templates
 * @since       1.0.0
 * @version     3.0.0 - REFACTOR: Tabs, infinite scroll, filters, translations
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// TRANSLATION SETUP
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}

$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'customers') 
    : array();

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// LOAD ADMIN TABLE COMPONENT
// ============================================
if (!class_exists('SAW_Component_Admin_Table')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';
}

// ============================================
// ENTITY & CONFIG
// ============================================
$entity = $config['entity'] ?? 'customers';

// ============================================
// BUILD TABLE CONFIG
// ============================================
$table_config = array();

// ============================================
// TITLE & URLS
// ============================================
$table_config['title'] = $tr('page_title', 'Zákazníci');
$table_config['create_url'] = home_url('/admin/customers/create');
$table_config['edit_url'] = home_url('/admin/customers/{id}/edit');
$table_config['detail_url'] = home_url('/admin/customers/{id}/');

// ============================================
// SEARCH CONFIGURATION
// ============================================
$table_config['search'] = array(
    'enabled' => true,
    'placeholder' => $tr('search_placeholder', 'Hledat zákazníka...'),
    'fields' => array('name', 'ico', 'dic', 'contact_person', 'contact_email'),
    'show_info_banner' => true,
);

// ============================================
// FILTERS CONFIGURATION
// ============================================
$status_options = array(
    '' => $tr('filter_all_statuses', 'Všechny statusy'),
    'active' => $tr('status_active', 'Aktivní'),
    'inactive' => $tr('status_inactive', 'Neaktivní'),
    'potential' => $tr('status_potential', 'Potenciální'),
);

$table_config['filters'] = array(
    'status' => array(
        'type' => 'select',
        'label' => $tr('filter_status', 'Status'),
        'options' => $status_options,
    ),
);

// ============================================
// LOAD ACCOUNT TYPES FOR COLUMNS
// ============================================
global $wpdb;
$account_types_lookup = array();
$account_types_raw = $wpdb->get_results(
    "SELECT id, name, display_name, color 
     FROM {$wpdb->prefix}saw_account_types 
     WHERE is_active = 1 
     ORDER BY sort_order ASC, display_name ASC",
    ARRAY_A
);

if ($account_types_raw) {
    foreach ($account_types_raw as $type) {
        $account_types_lookup[$type['id']] = $type;
    }
}

// ============================================
// PRE-PROCESS ITEMS (add account_type display data)
// ============================================
if (!empty($items)) {
    foreach ($items as &$item) {
        $item['account_type_display'] = '';
        $item['account_type_color'] = '';
        
        if (!empty($item['account_type_id']) && isset($account_types_lookup[$item['account_type_id']])) {
            $type = $account_types_lookup[$item['account_type_id']];
            $item['account_type_display'] = $type['display_name'];
            $item['account_type_color'] = $type['color'] ?? '#6b7280';
        }
    }
    unset($item);
}

// ============================================
// COLUMNS CONFIGURATION (NO CLOSURE!)
// ============================================
$table_config['columns'] = array(
    'logo_url' => array(
        'label' => $tr('col_logo', 'Logo'),
        'type' => 'image',
        'width' => '60px',
        'align' => 'center',
    ),
    'name' => array(
        'label' => $tr('col_name', 'Název'),
        'type' => 'text',
        'sortable' => true,
        'class' => 'saw-table-cell-bold',
    ),
    'ico' => array(
        'label' => $tr('col_ico', 'IČO'),
        'type' => 'text',
        'width' => '120px',
    ),
    'status' => array(
        'label' => $tr('col_status', 'Status'),
        'type' => 'badge',
        'width' => '120px',
        'map' => array(
            'active' => 'success',
            'inactive' => 'secondary',
            'potential' => 'warning',
        ),
        'labels' => array(
            'active' => $tr('status_active', 'Aktivní'),
            'inactive' => $tr('status_inactive', 'Neaktivní'),
            'potential' => $tr('status_potential', 'Potenciální'),
        ),
    ),
    'account_type_display' => array(
        'label' => $tr('col_account_type', 'Typ účtu'),
        'type' => 'badge_colored',
        'width' => '150px',
        'color_field' => 'account_type_color',
        'empty_text' => '—',
    ),
    'created_at' => array(
        'label' => $tr('col_created', 'Vytvořeno'),
        'type' => 'date',
        'sortable' => true,
        'width' => '120px',
    ),
);

// ============================================
// DATA
// ============================================
$table_config['rows'] = isset($items) ? $items : array();
$table_config['total_items'] = isset($total) ? $total : 0;
$table_config['current_page'] = isset($page) ? $page : 1;
$table_config['total_pages'] = isset($total_pages) ? $total_pages : 1;
$table_config['search_value'] = isset($search) ? $search : '';
$table_config['orderby'] = isset($orderby) ? $orderby : 'name';
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
$table_config['add_new'] = $tr('btn_add_new', 'Nový zákazník');
$table_config['empty_message'] = $tr('empty_message', 'Žádní zákazníci nenalezeni');

// ============================================
// TABS CONFIGURATION
// Load from config, override labels with translations
// ============================================
$table_config['tabs'] = isset($config['tabs']) ? $config['tabs'] : null;

// Override tab labels with translations
if (!empty($table_config['tabs']['tabs'])) {
    // "All" tab
    if (isset($table_config['tabs']['tabs']['all'])) {
        $table_config['tabs']['tabs']['all']['label'] = $tr('tab_all', 'Všichni');
    }
}

// ============================================
// DYNAMIC TABS - Load account types from DB
// ============================================
if (!empty($table_config['tabs']['dynamic']) && !empty($account_types_raw)) {
    foreach ($account_types_raw as $type) {
        $tab_key = 'type_' . $type['id'];
        $table_config['tabs']['tabs'][$tab_key] = array(
            'label' => $type['display_name'],
            'filter_value' => $type['id'],
            'color' => $type['color'] ?? null,
        );
    }
}

// ============================================
// GENERATE TAB COUNTS (for dynamic tabs)
// Base controller doesn't know about dynamic tabs,
// so we generate counts here in template.
// ============================================
// Note: $wpdb is already available from above, but be explicit
$generated_tab_counts = array();

// Count for "all" tab
$generated_tab_counts['all'] = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}saw_customers"
);

// Count per account_type_id
$type_counts = $wpdb->get_results(
    "SELECT account_type_id, COUNT(*) as cnt 
     FROM {$wpdb->prefix}saw_customers 
     WHERE account_type_id IS NOT NULL 
     GROUP BY account_type_id",
    ARRAY_A
);

if ($type_counts) {
    foreach ($type_counts as $tc) {
        $generated_tab_counts['type_' . $tc['account_type_id']] = (int) $tc['cnt'];
    }
}

// Ensure all tabs have a count (even 0)
foreach ($account_types_raw as $type) {
    $tab_key = 'type_' . $type['id'];
    if (!isset($generated_tab_counts[$tab_key])) {
        $generated_tab_counts[$tab_key] = 0;
    }
}

// Use generated counts instead of controller counts
$tab_counts = $generated_tab_counts;

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
// MODAL SETTINGS (backward compatible)
// ============================================
$table_config['enable_modal'] = empty($sidebar_mode);
$table_config['modal_id'] = 'customer-detail';
$table_config['modal_ajax_action'] = 'saw_get_customers_detail';

// ============================================
// RENDER
// ============================================
$table = new SAW_Component_Admin_Table($entity, $table_config);
$table->render();