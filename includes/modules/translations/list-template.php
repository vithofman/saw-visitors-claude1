<?php
/**
 * Translations List Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Translations
 * @version     1.0.0
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
    ? saw_get_translations($lang, 'admin', 'translations') 
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
$orderby = $list_data['orderby'] ?? 'translation_key';
$order = $list_data['order'] ?? 'ASC';

// Get available options for filters
$available_languages = (isset($model) && method_exists($model, 'get_available_languages')) 
    ? $model->get_available_languages() 
    : array('cs', 'en', 'de', 'sk');

// Get available contexts - pokud model není dostupný, použijeme fallback, ale pak zkusíme načíst z DB přímo
$available_contexts = array();
if (isset($model) && method_exists($model, 'get_available_contexts')) {
    $available_contexts = $model->get_available_contexts();
}
// Fallback: pokud model nevrátil žádné kontexty, zkusíme načíst přímo z DB
if (empty($available_contexts)) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'saw_ui_translations';
    $contexts_from_db = $wpdb->get_col(
        "SELECT DISTINCT context FROM {$table_name} WHERE context IS NOT NULL AND context != '' ORDER BY context ASC"
    );
    if (!empty($contexts_from_db)) {
        $available_contexts = $contexts_from_db;
    } else {
        // Poslední fallback - základní hodnoty
        $available_contexts = array('terminal', 'invitation', 'admin', 'common', 'email');
    }
}

// Get available sections - podobně jako u kontextů
$available_sections = array();
if (isset($model) && method_exists($model, 'get_available_sections')) {
    $available_sections = $model->get_available_sections();
}
// Fallback: pokud model nevrátil žádné sekce, zkusíme načíst přímo z DB
if (empty($available_sections)) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'saw_ui_translations';
    $sections_from_db = $wpdb->get_col(
        "SELECT DISTINCT section FROM {$table_name} WHERE section IS NOT NULL AND section != '' ORDER BY section ASC"
    );
    if (!empty($sections_from_db)) {
        $available_sections = $sections_from_db;
    }
}

// ============================================
// TABLE CONFIGURATION
// ============================================
$table_config = array(
    'title' => $tr('title', 'Překlady'),
    'create_url' => home_url('/admin/translations/create'),
    'edit_url' => home_url('/admin/translations/{id}/edit'),
    'detail_url' => home_url('/admin/translations/{id}/'),
    
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
    'empty_message' => $tr('empty_message', 'Žádné překlady nenalezeny'),
    'add_new' => $tr('add_new', 'Nový překlad'),
    
    'ajax_enabled' => true,
    'ajax_nonce' => $ajax_nonce,
);

// ============================================
// SEARCH CONFIGURATION
// ============================================
$table_config['search'] = array(
    'enabled' => true,
    'placeholder' => $tr('search_placeholder', 'Hledat překlady...'),
    'fields' => array('translation_key', 'context', 'section', 'translation_text', 'description'),
    'show_info_banner' => true,
);

// ============================================
// FILTERS CONFIGURATION
// ============================================
$table_config['filters'] = array(
    'language_code' => array(
        'label' => $tr('filter_language', 'Jazyk'),
        'type' => 'select',
        'options' => array_merge(
            array('' => $tr('filter_all', 'Všechny')),
            array_combine($available_languages, $available_languages)
        ),
    ),
    'context' => array(
        'label' => $tr('filter_context', 'Kontext'),
        'type' => 'select',
        'options' => array_merge(
            array('' => $tr('filter_all', 'Všechny')),
            array_combine($available_contexts, array_map(function($ctx) {
                // Mapování názvů kontextů na čitelnější formu
                $labels = array(
                    'terminal' => '🖥️ Terminal',
                    'invitation' => '📧 Pozvánka',
                    'admin' => '⚙️ Admin',
                    'common' => '🌐 Společné',
                    'email' => '📧 Email',
                );
                return $labels[$ctx] ?? ucfirst($ctx);
            }, $available_contexts))
        ),
    ),
    'section' => array(
        'label' => $tr('filter_section', 'Sekce'),
        'type' => 'select',
        'options' => array_merge(
            array('' => $tr('filter_all', 'Všechny')),
            array_combine($available_sections, $available_sections)
        ),
    ),
);

// ============================================
// COLUMNS CONFIGURATION
// ============================================
if (isset($controller) && method_exists($controller, 'get_table_columns')) {
    $table_config['columns'] = $controller->get_table_columns();
} else {
    $table_config['columns'] = array(
        'translation_key' => array(
            'label' => $tr('col_translation_key', 'Klíč'),
            'type' => 'text',
            'class' => 'saw-table-cell-bold',
            'sortable' => true,
        ),
        'language_code' => array(
            'label' => $tr('col_language', 'Jazyk'),
            'type' => 'badge',
            'sortable' => true,
            'map' => array(
                'cs' => 'info',
                'en' => 'secondary',
                'de' => 'warning',
                'sk' => 'success',
            ),
            'labels' => array(
                'cs' => '🇨🇿 CS',
                'en' => '🇬🇧 EN',
                'de' => '🇩🇪 DE',
                'sk' => '🇸🇰 SK',
            ),
        ),
        'context' => array(
            'label' => $tr('col_context', 'Kontext'),
            'type' => 'badge',
            'sortable' => true,
            'map' => array(
                'terminal' => 'primary',
                'invitation' => 'info',
                'admin' => 'warning',
                'common' => 'secondary',
            ),
            'labels' => array(
                'terminal' => '🖥️ Terminal',
                'invitation' => '📧 Pozvánka',
                'admin' => '⚙️ Admin',
                'common' => '🌐 Společné',
            ),
        ),
        'section' => array(
            'label' => $tr('col_section', 'Sekce'),
            'type' => 'text',
            'sortable' => true,
        ),
        'translation_text' => array(
            'label' => $tr('col_translation_text', 'Text'),
            'type' => 'text',
            'class' => 'saw-table-cell-truncate',
            'maxlength' => 100,
        ),
        'description' => array(
            'label' => $tr('col_description', 'Popis'),
            'type' => 'text',
            'class' => 'saw-table-cell-truncate',
            'maxlength' => 50,
        ),
        'created_at' => array(
            'label' => $tr('col_created_at', 'Vytvořeno'),
            'type' => 'callback',
            'callback' => function($value) {
                return !empty($value) ? date('d.m.Y H:i', strtotime($value)) : '—';
            },
        ),
    );
}

// ============================================
// TABS CONFIGURATION
// ============================================
$table_config['tabs'] = $config['tabs'] ?? null;

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
$table = new SAW_Component_Admin_Table('translations', $table_config);
$table->render();

