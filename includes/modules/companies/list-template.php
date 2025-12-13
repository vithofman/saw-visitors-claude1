<?php
/**
 * Companies List Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Companies
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
    ? saw_get_translations($lang, 'admin', 'companies') 
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
$orderby = $orderby ?? 'created_at';
$order = $order ?? 'DESC';

// ============================================
// TABLE CONFIGURATION
// ============================================
$table_config = array(
    'title' => $tr('title', 'Firmy'),
    'create_url' => home_url('/admin/companies/create'),
    'edit_url' => home_url('/admin/companies/{id}/edit'),
    'detail_url' => home_url('/admin/companies/{id}/'),
    
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
    'empty_message' => $tr('empty_message', 'Žádné firmy nenalezeny'),
    'add_new' => $tr('add_new', 'Nová firma'),
);

// ============================================
// SEARCH CONFIGURATION
// ============================================
$table_config['search'] = array(
    'enabled' => true,
    'placeholder' => $tr('search_placeholder', 'Hledat firmy...'),
    'fields' => array('name', 'ico', 'email', 'phone', 'city'),
    'show_info_banner' => true,
);

// ============================================
// FILTERS CONFIGURATION
// ============================================
$table_config['filters'] = array(
    'is_archived' => array(
        'label' => $tr('filter_status', 'Status'),
        'type' => 'select',
        'options' => array(
            '' => $tr('filter_all', 'Všechny'),
            '0' => $tr('filter_active', 'Aktivní'),
            '1' => $tr('filter_archived', 'Archivované'),
        ),
    ),
);

// ============================================
// COLUMNS CONFIGURATION - ŠÍŘKY V PROCENTECH
// ============================================
$table_config['columns'] = array(
    'name' => array(
        'label' => $tr('col_name', 'Název firmy'),
        'type' => 'text',
        'class' => 'saw-table-cell-bold',
        'sortable' => true,
        'width' => '20%',  // Hlavní identifikátor
    ),
    'ico' => array(
        'label' => $tr('col_ico', 'IČO'),
        'type' => 'text',
        'sortable' => true,
        'width' => '10%',
    ),
    'street' => array(
        'label' => $tr('col_street', 'Ulice'),
        'type' => 'text',
        'class' => 'saw-table-cell-truncate',
        'width' => '13%',
    ),
    'city' => array(
        'label' => $tr('col_city', 'Město'),
        'type' => 'text',
        'sortable' => true,
        'width' => '12%',
    ),
    'zip' => array(
        'label' => $tr('col_zip', 'PSČ'),
        'type' => 'text',
        'width' => '7%',
    ),
    'email' => array(
        'label' => $tr('col_email', 'Email'),
        'type' => 'email',
        'width' => '13%',
    ),
    'phone' => array(
        'label' => $tr('col_phone', 'Telefon'),
        'type' => 'text',
        'width' => '11%',
    ),
    'is_archived' => array(
        'label' => $tr('col_status', 'Status'),
        'type' => 'badge',
        'sortable' => true,
        'width' => '8%',   // Badge je malý
        'align' => 'center',
        'map' => array(
            '1' => 'secondary',
            '0' => 'success',
        ),
        'labels' => array(
            '1' => $tr('status_archived', 'Archivováno'),
            '0' => $tr('status_active', 'Aktivní'),
        ),
    ),
    'created_at' => array(
        'label' => $tr('col_created_at', 'Vytvořeno'),
        'type' => 'date',
        'format' => 'd.m.Y',
        'width' => '6%',
    ),
);
// Součet: 20 + 10 + 13 + 12 + 7 + 13 + 11 + 8 + 6 = 100%

// ============================================
// TABS CONFIGURATION
// ============================================
$table_config['tabs'] = $config['tabs'] ?? null;

if (!empty($table_config['tabs']['enabled'])) {
    // Přepsat labels z configu překlady
    if (!empty($table_config['tabs']['tabs'])) {
        if (isset($table_config['tabs']['tabs']['all'])) {
            $table_config['tabs']['tabs']['all']['label'] = $tr('tab_all', 'Všechny');
        }
        if (isset($table_config['tabs']['tabs']['active'])) {
            $table_config['tabs']['tabs']['active']['label'] = $tr('tab_active', 'Aktivní');
        }
        if (isset($table_config['tabs']['tabs']['archived'])) {
            $table_config['tabs']['tabs']['archived']['label'] = $tr('tab_archived', 'Archivované');
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
$table = new SAW_Component_Admin_Table('companies', $table_config);
$table->render();