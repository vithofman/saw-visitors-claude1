<?php
/**
 * Account Types List Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
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
    ? saw_get_translations($lang, 'admin', 'account-types') 
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
$orderby = $orderby ?? 'sort_order';
$order = $order ?? 'ASC';

// ============================================
// TABLE CONFIGURATION
// ============================================
$base_url = home_url('/admin/' . ($config['route'] ?? 'account-types'));

$table_config = array(
    'title' => $config['plural'] ?? $tr('page_title', 'Typy účtů'),
    'create_url' => $base_url . '/create',
    'detail_url' => $base_url . '/{id}/',
    'edit_url' => $base_url . '/{id}/edit',
    
    'sidebar_mode' => $sidebar_mode ?? null,
    'detail_item' => $detail_item ?? null,
    'form_item' => $form_item ?? null,
    'detail_tab' => $detail_tab ?? 'overview',
    'module_config' => $config ?? array(),
    
    'rows' => $items,
    'total_items' => $total,
    'current_page' => $page,
    'total_pages' => $total_pages,
    'orderby' => $orderby,
    'order' => $order,
    
    'actions' => array('view', 'edit', 'delete'),
    'empty_message' => $tr('empty_message', 'Žádné typy účtů nenalezeny'),
    'add_new' => $tr('btn_add_new', 'Nový typ účtu'),
);

// ============================================
// COLUMNS CONFIGURATION - ŠÍŘKY V PROCENTECH
// ============================================
$table_config['columns'] = array(
    'color' => array(
        'label' => $tr('col_color', 'Barva'),
        'type' => 'custom',
        'width' => '8%',   // Barevný čtverec malý
        'align' => 'center',
        'callback' => function($value) {
            if (empty($value)) {
                return '<span class="saw-text-muted">—</span>';
            }
            return '<div style="width: 36px; height: 36px; border-radius: 8px; background-color: ' . esc_attr($value) . '; border: 2px solid #e5e7eb; margin: 0 auto;"></div>';
        }
    ),
    'display_name' => array(
        'label' => $tr('col_name', 'Název'),
        'type' => 'text',
        'sortable' => true,
        'class' => 'saw-table-cell-bold',
        'width' => '25%',  // Hlavní název
    ),
    'name' => array(
        'label' => $tr('col_internal_name', 'Interní název'),
        'type' => 'custom',
        'width' => '15%',  // Monospace badge
        'callback' => function($value) {
            return '<span class="saw-badge saw-badge-secondary" style="font-family: monospace; text-transform: uppercase;">' . esc_html($value) . '</span>';
        }
    ),
    'price' => array(
        'label' => $tr('col_price', 'Cena'),
        'type' => 'custom',
        'align' => 'right',
        'width' => '12%',  // Cena
        'sortable' => true,
        'callback' => function($value) use ($tr) {
            $price = floatval($value ?? 0);
            if ($price > 0) {
                return '<strong>' . number_format($price, 0, ',', ' ') . ' Kč</strong>';
            }
            return '<span class="saw-text-muted">' . $tr('free', 'Zdarma') . '</span>';
        }
    ),
    'features' => array(
        'label' => $tr('col_features', 'Funkce'),
        'type' => 'custom',
        'align' => 'center',
        'width' => '10%',  // Počet funkcí
        'callback' => function($value) {
            $features = !empty($value) ? json_decode($value, true) : array();
            $count = is_array($features) ? count($features) : 0;
            if ($count > 0) {
                return '<span class="saw-badge saw-badge-info">' . $count . '</span>';
            }
            return '<span class="saw-text-muted">—</span>';
        }
    ),
    'sort_order' => array(
        'label' => $tr('col_sort_order', 'Pořadí'),
        'type' => 'text',
        'sortable' => true,
        'align' => 'center',
        'width' => '8%',   // Číslo malé
    ),
    'is_active' => array(
        'label' => $tr('col_status', 'Status'),
        'type' => 'badge',
        'width' => '10%',  // Badge
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
    'created_at' => array(
        'label' => $tr('col_created', 'Vytvořeno'),
        'type' => 'date',
        'sortable' => true,
        'width' => '12%',  // Datum
        'format' => 'd.m.Y',
    ),
);
// Součet: 8 + 25 + 15 + 12 + 10 + 8 + 10 + 12 = 100%

// ============================================
// SEARCH CONFIGURATION
// ============================================
$table_config['search'] = array(
    'enabled' => true,
    'placeholder' => $tr('search_placeholder', 'Hledat typ účtu...'),
    'fields' => array('name', 'display_name'),
    'show_info_banner' => true,
);

// ============================================
// FILTERS CONFIGURATION
// ============================================
$table_config['filters'] = array(
    'price_type' => array(
        'label' => $tr('filter_price', 'Cena'),
        'type' => 'select',
        'options' => array(
            '' => $tr('filter_all', 'Všechny'),
            'free' => $tr('free', 'Zdarma'),
            'paid' => $tr('paid', 'Placené'),
        ),
    ),
);

// ============================================
// TABS CONFIGURATION
// ============================================
$table_config['tabs'] = $config['tabs'] ?? null;

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
// CURRENT TAB & TAB COUNTS
// ============================================
if (!empty($table_config['tabs']['enabled'])) {
    $table_config['current_tab'] = (isset($current_tab) && $current_tab !== null && $current_tab !== '') 
        ? (string)$current_tab 
        : ($table_config['tabs']['default_tab'] ?? 'all');
    $table_config['tab_counts'] = (isset($tab_counts) && is_array($tab_counts)) ? $tab_counts : array();
}

// ============================================
// RENDER TABLE
// ============================================
$entity = $config['entity'] ?? 'account_types';
$table = new SAW_Component_Admin_Table($entity, $table_config);
$table->render();
