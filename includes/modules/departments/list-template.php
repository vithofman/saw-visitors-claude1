<?php
/**
 * Departments List Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Departments
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
    ? saw_get_translations($lang, 'admin', 'departments') 
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
// LOAD BRANCHES FOR FORM (if needed)
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
// TABLE CONFIGURATION
// ============================================
$table_config = array(
    'title' => $tr('title', 'Oddělení'),
    'create_url' => home_url('/admin/departments/create'),
    'edit_url' => home_url('/admin/departments/{id}/edit'),
    'detail_url' => home_url('/admin/departments/{id}/'),
    
    'sidebar_mode' => $sidebar_mode ?? null,
    'detail_item' => $detail_item ?? null,
    'form_item' => $form_item ?? null,
    'detail_tab' => $detail_tab ?? 'overview',
    'related_data' => $related_data ?? null,
    
    'module_config' => isset($config) ? $config : array(),
    'branches' => $branches,
    
    'rows' => $items,
    'total_items' => $total,
    'current_page' => $page,
    'total_pages' => $total_pages,
    'orderby' => $orderby,
    'order' => $order,
    
    'actions' => array('view', 'edit', 'delete'),
    'empty_message' => $tr('empty_message', 'Žádná oddělení nenalezena'),
    'add_new' => $tr('add_new', 'Nové oddělení'),
);

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
// COLUMNS CONFIGURATION - ŠÍŘKY V PROCENTECH
// ============================================
$table_config['columns'] = array(
    'department_number' => array(
        'label' => $tr('col_department_number', 'Číslo oddělení'),
        'type' => 'custom',
        'width' => '20%',  // Malý sloupec pro kód
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
        'width' => '65%',  // Hlavní identifikátor
    ),
    'is_active' => array(
        'label' => $tr('col_status', 'Status'),
        'type' => 'badge',
        'sortable' => true,
        'width' => '15%',  // Badge
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
);
// Součet: 20 + 65 + 15 = 100%

// ============================================
// TABS CONFIGURATION
// ============================================
$table_config['tabs'] = $config['tabs'] ?? null;

if (!empty($table_config['tabs']['enabled'])) {
    // Přepsat labels z configu překlady
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
$table = new SAW_Component_Admin_Table('departments', $table_config);
$table->render();