<?php
/**
 * Companies List Template
 * 
 * Refaktorovaná šablona dle vzoru branches modulu.
 * Používá admin-table komponentu s custom callbacks a Bento stylingem.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Companies
 * @version     3.0.0 - REFACTORED: Bento style dle branches vzoru
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
$orderby = $orderby ?? 'name';
$order = $order ?? 'ASC';

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
// COLUMNS CONFIGURATION - ŠÍŘKY V PROCENTECH
// ============================================
$table_config['columns'] = array(
    'name' => array(
        'label' => $tr('col_name', 'Název firmy'),
        'type' => 'text',
        'sortable' => true,
        'class' => 'sa-text-semibold',
        'width' => '25%',  // Hlavní identifikátor - nejširší
    ),
    'ico' => array(
        'label' => $tr('col_ico', 'IČO'),
        'type' => 'custom',
        'sortable' => true,
        'width' => '12%',
        'callback' => function($value) {
            if (empty($value)) {
                return '<span class="sa-text-muted">—</span>';
            }
            return sprintf('<span class="sa-code-badge">%s</span>', esc_html($value));
        }
    ),
    'city' => array(
        'label' => $tr('col_city', 'Město'),
        'type' => 'text',
        'sortable' => true,
        'width' => '15%',
    ),
    'email' => array(
        'label' => $tr('col_email', 'Email'),
        'type' => 'custom',
        'width' => '18%',
        'callback' => function($value) {
            if (empty($value)) {
                return '<span class="sa-text-muted">—</span>';
            }
            return sprintf(
                '<a href="mailto:%s" class="sa-link sa-link--truncate" title="%s">%s</a>',
                esc_attr($value),
                esc_attr($value),
                esc_html($value)
            );
        }
    ),
    'phone' => array(
        'label' => $tr('col_phone', 'Telefon'),
        'type' => 'custom',
        'width' => '14%',
        'callback' => function($value) {
            if (empty($value)) {
                return '<span class="sa-text-muted">—</span>';
            }
            return sprintf(
                '<a href="tel:%s" class="sa-link">%s</a>',
                esc_attr(preg_replace('/[^\d+]/', '', $value)),
                esc_html($value)
            );
        }
    ),
    'is_archived' => array(
        'label' => $tr('col_status', 'Status'),
        'type' => 'badge',
        'sortable' => true,
        'width' => '10%',
        'align' => 'center',
        'map' => array(
            '1' => 'neutral',
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
        'sortable' => true,
        'format' => 'd.m.Y',
        'width' => '6%',
    ),
);
// Součet: 25 + 12 + 15 + 18 + 14 + 10 + 6 = 100%

// ============================================
// SEARCH CONFIGURATION
// ============================================
$table_config['search'] = array(
    'enabled' => true,
    'placeholder' => $tr('search_placeholder', 'Hledat firmy...'),
    'fields' => array('name', 'ico', 'email', 'phone', 'city', 'street'),
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