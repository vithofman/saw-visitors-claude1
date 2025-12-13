<?php
/**
 * Customers List Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Customers
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
    ? saw_get_translations($lang, 'admin', 'customers') 
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
$orderby = $orderby ?? 'name';
$order = $order ?? 'ASC';

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
// TABLE CONFIGURATION
// ============================================
$table_config = array(
    'title' => $tr('page_title', 'Zákazníci'),
    'create_url' => home_url('/admin/customers/create'),
    'edit_url' => home_url('/admin/customers/{id}/edit'),
    'detail_url' => home_url('/admin/customers/{id}/'),
    
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
    'empty_message' => $tr('empty_message', 'Žádní zákazníci nenalezeni'),
    'add_new' => $tr('btn_add_new', 'Nový zákazník'),
    
    'enable_modal' => empty($sidebar_mode),
    'modal_id' => 'customer-detail',
    'modal_ajax_action' => 'saw_get_customers_detail',
);

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
        'label' => $tr('filter_status', 'Status'),
        'type' => 'select',
        'options' => $status_options,
    ),
);

// ============================================
// COLUMNS CONFIGURATION - ŠÍŘKY V PROCENTECH
// ============================================
$table_config['columns'] = array(
    'logo_url' => array(
        'label' => $tr('col_logo', 'Logo'),
        'type' => 'image',
        'width' => '8%',   // Logo malý
        'align' => 'center',
    ),
    'name' => array(
        'label' => $tr('col_name', 'Název'),
        'type' => 'text',
        'sortable' => true,
        'class' => 'saw-table-cell-bold',
        'width' => '30%',  // Hlavní identifikátor
    ),
    'ico' => array(
        'label' => $tr('col_ico', 'IČO'),
        'type' => 'text',
        'width' => '12%',
    ),
    'status' => array(
        'label' => $tr('col_status', 'Status'),
        'type' => 'badge',
        'width' => '12%',  // Badge střední
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
        'width' => '20%',  // Badge barevný
        'color_field' => 'account_type_color',
        'empty_text' => '—',
    ),
    'created_at' => array(
        'label' => $tr('col_created', 'Vytvořeno'),
        'type' => 'date',
        'sortable' => true,
        'width' => '18%',  // Date sloupec
        'format' => 'd.m.Y',
    ),
);
// Součet: 8 + 30 + 12 + 12 + 20 + 18 = 100%

// ============================================
// TABS CONFIGURATION
// ============================================
$table_config['tabs'] = $config['tabs'] ?? null;

// Override tab labels with translations
if (!empty($table_config['tabs']['tabs'])) {
    // "All" tab
    if (isset($table_config['tabs']['tabs']['all'])) {
        $table_config['tabs']['tabs']['all']['label'] = $tr('tab_all', 'Všichni');
    }
}

// DYNAMIC TABS - Load account types from DB
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

// GENERATE TAB COUNTS (for dynamic tabs)
// Base controller doesn't know about dynamic tabs,
// so we generate counts here in template.
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

if (!empty($table_config['tabs']['enabled'])) {
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
$table = new SAW_Component_Admin_Table('customers', $table_config);
$table->render();