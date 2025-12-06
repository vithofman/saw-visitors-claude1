<?php
/**
 * Visitors List Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visitors
 * @version     4.0.0 - Multi-language support
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// TRANSLATIONS SETUP
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}
$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'visitors') 
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

$ajax_nonce = wp_create_nonce('saw_ajax_nonce');

$items = $list_data['items'] ?? array();
$total = $list_data['total'] ?? 0;
$page = $list_data['page'] ?? 1;
$total_pages = $list_data['total_pages'] ?? 0;
$search = $list_data['search'] ?? '';
$orderby = $list_data['orderby'] ?? 'vis.id';
$order = $list_data['order'] ?? 'DESC';

// ============================================
// TABLE CONFIGURATION
// ============================================
$table_config = array(
    'title' => $tr('title', 'Návštěvníci'),
    'create_url' => home_url('/admin/visitors/create'),
    'edit_url' => home_url('/admin/visitors/{id}/edit'),
    'detail_url' => home_url('/admin/visitors/{id}/'),
    
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
    'empty_message' => $tr('empty_message', 'Žádní návštěvníci nenalezeni'),
    'add_new' => $tr('add_new', 'Nový návštěvník'),
    
    'ajax_enabled' => true,
    'ajax_nonce' => $ajax_nonce,
);

// ============================================
// SEARCH CONFIGURATION
// ============================================
$table_config['search'] = array(
    'enabled' => true,
    'placeholder' => $tr('search_placeholder', 'Hledat návštěvníky...'),
    'fields' => array('first_name', 'last_name', 'email'),
    'show_info_banner' => true,
);

// ============================================
// FILTERS CONFIGURATION
// ============================================
$table_config['filters'] = array(
    'training_status' => array(
        'label' => $tr('filter_training', 'Školení'),
        'type' => 'select',
        'options' => array(
            '' => $tr('filter_all', 'Všechny'),
            'completed' => $tr('filter_training_completed', '✅ Dokončeno'),
            'in_progress' => $tr('filter_training_in_progress', '🔄 Probíhá'),
            'skipped' => $tr('filter_training_skipped', '⏭️ Přeskočeno'),
            'not_started' => $tr('filter_training_not_started', '⚪ Nespuštěno'),
        ),
    ),
);

// ============================================
// COLUMNS CONFIGURATION
// ============================================
$table_config['columns'] = array(
    'first_name' => array(
        'label' => $tr('col_first_name', 'Jméno'),
        'type' => 'text',
        'class' => 'saw-table-cell-bold',
        'sortable' => true,
    ),
    'last_name' => array(
        'label' => $tr('col_last_name', 'Příjmení'),
        'type' => 'text',
        'class' => 'saw-table-cell-bold',
        'sortable' => true,
    ),
    'company_name' => array(
        'label' => $tr('col_company', 'Firma'),
        'type' => 'text',
    ),
    'branch_name' => array(
        'label' => $tr('col_branch', 'Pobočka'),
        'type' => 'text',
    ),
    'current_status' => array(
        'label' => $tr('col_current_status', 'Aktuální stav'),
        'type' => 'badge',
        'sortable' => false,
        'map' => array(
            'present' => 'success',
            'checked_out' => 'secondary',
            'confirmed' => 'warning',
            'planned' => 'info',
            'no_show' => 'danger',
        ),
        'labels' => array(
            'present' => $tr('status_present', '✅ Přítomen'),
            'checked_out' => $tr('status_checked_out', '🚪 Odhlášen'),
            'confirmed' => $tr('status_confirmed', '⏳ Potvrzený'),
            'planned' => $tr('status_planned', '📅 Plánovaný'),
            'no_show' => $tr('status_no_show', '❌ Nedostavil se'),
        ),
    ),
    'first_checkin_at' => array(
        'label' => $tr('col_first_checkin', 'První check-in'),
        'type' => 'callback',
        'callback' => function($value) {
            return !empty($value) ? date('d.m.Y H:i', strtotime($value)) : '—';
        },
    ),
    'last_checkout_at' => array(
        'label' => $tr('col_last_checkout', 'Poslední check-out'),
        'type' => 'callback',
        'callback' => function($value) {
            return !empty($value) ? date('d.m.Y H:i', strtotime($value)) : '—';
        },
    ),
    'training_status' => array(
        'label' => $tr('col_training', 'Školení'),
        'type' => 'badge',
        'map' => array(
            'completed' => 'success',
            'in_progress' => 'info',
            'skipped' => 'warning',
            'not_started' => 'secondary',
        ),
        'labels' => array(
            'completed' => $tr('training_completed', '✅ Dokončeno'),
            'in_progress' => $tr('training_in_progress', '🔄 Probíhá'),
            'skipped' => $tr('training_skipped', '⏭️ Přeskočeno'),
            'not_started' => $tr('training_not_started', '⚪ Nespuštěno'),
        ),
    ),
);

// ============================================
// TABS CONFIGURATION
// ============================================
$table_config['tabs'] = $config['tabs'] ?? null;

if (!empty($table_config['tabs']['enabled']) && !empty($table_config['tabs']['tabs'])) {
    $tab_translations = array(
        'all' => $tr('tab_all', 'Všichni'),
        'present' => $tr('tab_present', 'Přítomní'),
        'checked_out' => $tr('tab_checked_out', 'Odhlášení'),
        'confirmed' => $tr('tab_confirmed', 'Potvrzení'),
        'planned' => $tr('tab_planned', 'Plánovaní'),
        'no_show' => $tr('tab_no_show', 'Nedostavili se'),
    );
    
    foreach ($table_config['tabs']['tabs'] as $tab_key => &$tab_config_item) {
        if (isset($tab_translations[$tab_key])) {
            $tab_config_item['label'] = $tab_translations[$tab_key];
        }
    }
    unset($tab_config_item);
}

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
$table = new SAW_Component_Admin_Table('visitors', $table_config);
$table->render();