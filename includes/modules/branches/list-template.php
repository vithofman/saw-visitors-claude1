<?php
/**
 * Branches List Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches
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
    ? saw_get_translations($lang, 'admin', 'branches') 
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
$orderby = $orderby ?? 'is_headquarters';
$order = $order ?? 'DESC';

// ============================================
// TABLE CONFIGURATION
// ============================================
$table_config = array(
    'title' => $tr('title', 'Pobočky'),
    'create_url' => home_url('/admin/branches/create'),
    'edit_url' => home_url('/admin/branches/{id}/edit'),
    'detail_url' => home_url('/admin/branches/{id}/'),
    
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
    'empty_message' => $tr('empty_message', 'Žádné pobočky nenalezeny'),
    'add_new' => $tr('add_new', 'Nová pobočka'),
);

// ============================================
// COLUMNS CONFIGURATION - ŠÍŘKY V PROCENTECH (PC poměrové, mobil scrolluje)
// ============================================
$table_config['columns'] = array(
    'image_url' => array(
        'label' => $tr('col_image', 'Obrázek'),
        'type' => 'custom',
        'width' => '8%',   // Obrázek malý
        'callback' => function($value) {
            if (!empty($value)) {
                $upload_dir = wp_upload_dir();
                $thumb_url = strpos($value, 'http') === 0 
                    ? $value 
                    : $upload_dir['baseurl'] . '/' . ltrim($value, '/');
                
                // Použít wrapper pro konzistentní zarovnání
                return sprintf(
                    '<div class="sa-table-cell-image-wrapper"><img src="%s" alt="" class="sa-table-cell-image sa-branch-thumbnail"></div>',
                    esc_url($thumb_url)
                );
            } else {
                // Použít placeholder wrapper pro konzistentní zarovnání
                return '<div class="sa-table-cell-image-placeholder"><span class="sa-branch-icon">🏢</span></div>';
            }
        }
    ),
    'name' => array(
        'label' => $tr('col_name', 'Název pobočky'),
        'type' => 'text',
        'sortable' => true,
        'class' => 'sa-text-semibold',
        'width' => '30%',  // Hlavní identifikátor - nejširší
    ),
    'is_headquarters' => array(
        'label' => $tr('col_headquarters', 'Sídlo'),
        'type' => 'custom',
        'width' => '10%',  // Badge
        'callback' => function($value) use ($tr) {
            if (empty($value)) {
                return '<span class="sa-text-muted">—</span>';
            }
            return '<span class="sa-badge sa-badge--info">' . esc_html($tr('badge_headquarters', 'Sídlo')) . '</span>';
        }
    ),
    'code' => array(
        'label' => $tr('col_code', 'Kód'),
        'type' => 'custom',
        'width' => '10%',  // Kód pobočky
        'callback' => function($value) {
            if (empty($value)) return '<span class="sa-text-muted">—</span>';
            return sprintf('<span class="sa-code-badge">%s</span>', esc_html($value));
        }
    ),
    'city' => array(
        'label' => $tr('col_city', 'Město'),
        'type' => 'text',
        'sortable' => true,
        'width' => '16%',  // Město
    ),
    'phone' => array(
        'label' => $tr('col_phone', 'Telefon'),
        'type' => 'custom',
        'width' => '16%',  // Telefon
        'callback' => function($value) {
            if (empty($value)) return '<span class="sa-text-muted">—</span>';
            return sprintf(
                '<a href="tel:%s" class="sa-link">%s</a>',
                esc_attr(preg_replace('/[^\d+]/', '', $value)),
                esc_html($value)
            );
        }
    ),
    'is_active' => array(
        'label' => $tr('col_status', 'Status'),
        'type' => 'badge',
        'sortable' => true,
        'width' => '10%',  // Status badge
        'map' => array(
            '1' => 'success',
            '0' => 'neutral',
        ),
        'labels' => array(
            '1' => $tr('status_active', 'Aktivní'),
            '0' => $tr('status_inactive', 'Neaktivní'),
        ),
    ),
);
// Součet: 8 + 30 + 10 + 10 + 16 + 16 + 10 = 100%

// ============================================
// SEARCH CONFIGURATION
// ============================================
$table_config['search'] = array(
    'enabled' => true,
    'placeholder' => $tr('search_placeholder', 'Hledat pobočky...'),
    'fields' => array('name', 'code', 'city', 'email', 'phone'),
    'show_info_banner' => true,
);

// ============================================
// FILTERS CONFIGURATION
// ============================================
$table_config['filters'] = array(
    'is_active' => array(
        'label' => $tr('filter_status', 'Status'),
        'type' => 'select',
        'options' => array(
            '' => $tr('filter_all', 'Všechny'),
            '1' => $tr('filter_active', 'Aktivní'),
            '0' => $tr('filter_inactive', 'Neaktivní'),
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
        if (isset($table_config['tabs']['tabs']['headquarters'])) {
            $table_config['tabs']['tabs']['headquarters']['label'] = $tr('tab_headquarters', 'Sídla');
        }
        if (isset($table_config['tabs']['tabs']['other'])) {
            $table_config['tabs']['tabs']['other']['label'] = $tr('tab_other', 'Ostatní');
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
$table = new SAW_Component_Admin_Table('branches', $table_config);
$table->render();