<?php
/**
 * Companies List Template
 * @version 5.0.0 - FINAL: Multi-language support
 */

if (!defined('ABSPATH')) exit;

// ============================================
// LOAD TRANSLATIONS
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}
$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'companies') 
    : [];

// Helper s fallbackem
$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// PREPARE CONFIG FOR ADMIN TABLE
// ============================================
$table_config = $config;
$base_url = home_url('/admin/' . ($config['route'] ?? 'companies'));

$table_config['title'] = $tr('title', 'Firmy');
$table_config['create_url'] = $base_url . '/create';
$table_config['detail_url'] = $base_url . '/{id}/';
$table_config['edit_url'] = $base_url . '/{id}/edit';

// ============================================
// COLUMN DEFINITIONS
// ============================================
$table_config['columns'] = array(
    'name' => array(
        'label' => $tr('col_name', 'Název firmy'),
        'type' => 'text',
        'sortable' => true,
        'class' => 'saw-table-cell-bold'
    ),
    'ico' => array(
        'label' => $tr('col_ico', 'IČO'),
        'type' => 'text',
        'sortable' => true,
        'width' => '100px'
    ),
    'street' => array(
        'label' => $tr('col_street', 'Ulice'),
        'type' => 'text'
    ),
    'city' => array(
        'label' => $tr('col_city', 'Město'),
        'type' => 'text',
        'sortable' => true
    ),
    'zip' => array(
        'label' => $tr('col_zip', 'PSČ'),
        'type' => 'text',
        'width' => '80px'
    ),
    'email' => array(
        'label' => $tr('col_email', 'Email'),
        'type' => 'email'
    ),
    'phone' => array(
        'label' => $tr('col_phone', 'Telefon'),
        'type' => 'text',
        'width' => '140px'
    ),
    'is_archived' => array(
        'label' => $tr('col_status', 'Status'),
        'type' => 'custom',
        'width' => '100px',
        'align' => 'center',
        'callback' => function($value) use ($tr) {
            if (!empty($value)) {
                return '<span class="saw-badge saw-badge-secondary">' . esc_html($tr('status_archived', 'Archivováno')) . '</span>';
            }
            return '<span class="saw-badge saw-badge-success">' . esc_html($tr('status_active', 'Aktivní')) . '</span>';
        }
    ),
    'created_at' => array(
        'label' => $tr('col_created_at', 'Vytvořeno'),
        'type' => 'date',
        'width' => '120px',
        'format' => 'd.m.Y'
    )
);

// ============================================
// DATA
// ============================================
$table_config['rows'] = $items;
$table_config['total_items'] = $total;
$table_config['current_page'] = $page;
$table_config['total_pages'] = $total_pages;
$table_config['search_value'] = $search;
$table_config['orderby'] = $orderby;
$table_config['order'] = $order;

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
        'type' => 'select',
        'label' => $tr('filter_status', 'Status'),
        'options' => array(
            '' => $tr('filter_all', 'Všechny'),
            '0' => $tr('filter_active', 'Aktivní'),
            '1' => $tr('filter_archived', 'Archivované'),
        ),
    ),
);

// ============================================
// SIDEBAR CONTEXT
// ============================================
$table_config['sidebar_mode'] = $sidebar_mode;
$table_config['detail_item'] = $detail_item ?? null;
$table_config['form_item'] = $form_item ?? null;
$table_config['detail_tab'] = $detail_tab;
$table_config['module_config'] = $config;

// ============================================
// ACTIONS
// ============================================
$table_config['actions'] = array('view', 'edit', 'delete');
$table_config['add_new'] = $tr('add_new', 'Nová firma');
$table_config['empty_message'] = $tr('empty_message', 'Žádné firmy nenalezeny');

// ============================================
// TABS - Přepsat labels z configu
// ============================================
$table_config['tabs'] = $config['tabs'] ?? null;

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

// ============================================
// INFINITE SCROLL
// ============================================
$table_config['infinite_scroll'] = array(
    'enabled' => true,
    'initial_load' => 100,
    'per_page' => 50,
    'threshold' => 0.6,
);

// Ensure current_tab is always a valid string
if (!empty($table_config['tabs']['enabled'])) {
    $table_config['current_tab'] = (isset($current_tab) && $current_tab !== null && $current_tab !== '') 
        ? (string)$current_tab 
        : ($table_config['tabs']['default_tab'] ?? 'all');
    $table_config['tab_counts'] = (isset($tab_counts) && is_array($tab_counts)) ? $tab_counts : array();
}

// ============================================
// RENDER
// ============================================
if (!class_exists('SAW_Component_Admin_Table')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';
}

$table = new SAW_Component_Admin_Table($entity, $table_config);
$table->render();