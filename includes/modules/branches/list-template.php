<?php
/**
 * Branches List Template
 *
 * MODERNIZOVANÁ VERZE s překlady, tabs a infinite scroll
 * Struktura shodná s companies modulem
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches
 * @version     15.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// LOAD TRANSLATIONS
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}
$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'branches') 
    : [];

// Helper s fallbackem
$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// ENSURE COMPONENTS ARE LOADED
// ============================================
if (!class_exists('SAW_Component_Admin_Table')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';
}

// ============================================
// PREPARE CONFIG FOR ADMIN TABLE
// ============================================
$entity = $config['entity'] ?? 'branches';
$table_config = $config;
$base_url = home_url('/admin/' . ($config['route'] ?? 'branches'));

$table_config['title'] = $tr('title', 'Pobočky');
$table_config['create_url'] = $base_url . '/create';
$table_config['detail_url'] = $base_url . '/{id}/';
$table_config['edit_url'] = $base_url . '/{id}/edit';

// ============================================
// COLUMN DEFINITIONS
// ============================================
$table_config['columns'] = array(
    'image_url' => array(
        'label' => $tr('col_image', 'Obrázek'),
        'type' => 'custom',
        'width' => '60px',
        'align' => 'center',
        'callback' => function($value) {
            if (!empty($value)) {
                $upload_dir = wp_upload_dir();
                $thumb_url = strpos($value, 'http') === 0 
                    ? $value 
                    : $upload_dir['baseurl'] . '/' . ltrim($value, '/');
                
                return sprintf(
                    '<img src="%s" alt="" class="saw-branch-thumbnail" style="margin-right: 0; max-width: 50px; height: auto; border-radius: 4px;">',
                    esc_url($thumb_url)
                );
            } else {
                return '<span class="saw-branch-icon" style="margin-right: 0; font-size: 24px;">🏢</span>';
            }
        }
    ),
    'name' => array(
        'label' => $tr('col_name', 'Název pobočky'),
        'type' => 'text',
        'sortable' => true,
        'class' => 'saw-table-cell-bold',
    ),
    'is_headquarters' => array(
        'label' => $tr('col_headquarters', 'Sídlo'),
        'type' => 'custom',
        'width' => '80px',
        'align' => 'center',
        'callback' => function($value) use ($tr) {
            if (empty($value)) {
                return '<span class="saw-text-muted">—</span>';
            }
            return '<span class="saw-badge saw-badge-sm saw-badge-primary">' . esc_html($tr('badge_headquarters', 'Sídlo')) . '</span>';
        }
    ),
    'code' => array(
        'label' => $tr('col_code', 'Kód'),
        'type' => 'custom',
        'width' => '100px',
        'align' => 'center',
        'callback' => function($value) {
            if (empty($value)) return '<span class="saw-text-muted">—</span>';
            return sprintf('<span class="saw-code-badge">%s</span>', esc_html($value));
        }
    ),
    'city' => array(
        'label' => $tr('col_city', 'Město'),
        'type' => 'text',
        'sortable' => true,
    ),
    'phone' => array(
        'label' => $tr('col_phone', 'Telefon'),
        'type' => 'custom',
        'callback' => function($value) {
            if (empty($value)) return '<span class="saw-text-muted">—</span>';
            return sprintf(
                '<a href="tel:%s" class="saw-phone-link">%s</a>',
                esc_attr(preg_replace('/[^\d+]/', '', $value)),
                esc_html($value)
            );
        }
    ),
    'is_active' => array(
        'label' => $tr('col_status', 'Status'),
        'type' => 'custom',
        'width' => '100px',
        'align' => 'center',
        'callback' => function($value) use ($tr) {
            if (empty($value)) {
                return '<span class="saw-badge saw-badge-secondary">' . esc_html($tr('status_inactive', 'Neaktivní')) . '</span>';
            }
            return '<span class="saw-badge saw-badge-success">' . esc_html($tr('status_active', 'Aktivní')) . '</span>';
        }
    ),
);

// ============================================
// DATA
// ============================================
$table_config['rows'] = $items ?? array();
$table_config['total_items'] = $total ?? 0;
$table_config['current_page'] = $page ?? 1;
$table_config['total_pages'] = $total_pages ?? 1;
$table_config['search_value'] = $search ?? '';
$table_config['orderby'] = $orderby ?? 'is_headquarters';
$table_config['order'] = $order ?? 'DESC';

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
        'type' => 'select',
        'label' => $tr('filter_status', 'Status'),
        'options' => array(
            '' => $tr('filter_all', 'Všechny'),
            '1' => $tr('filter_active', 'Aktivní'),
            '0' => $tr('filter_inactive', 'Neaktivní'),
        ),
    ),
);

// ============================================
// SIDEBAR CONTEXT
// ============================================
$table_config['sidebar_mode'] = $sidebar_mode ?? null;
$table_config['detail_item'] = $detail_item ?? null;
$table_config['form_item'] = $form_item ?? null;
$table_config['detail_tab'] = $detail_tab ?? 'overview';
$table_config['module_config'] = $config;
$table_config['related_data'] = $related_data ?? null;

// ============================================
// ACTIONS
// ============================================
$table_config['actions'] = array('view', 'edit', 'delete');
$table_config['add_new'] = $tr('add_new', 'Nová pobočka');
$table_config['empty_message'] = $tr('empty_message', 'Žádné pobočky nenalezeny');

// ============================================
// TABS - Přepsat labels z configu
// ============================================
$table_config['tabs'] = $config['tabs'] ?? null;

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
$table = new SAW_Component_Admin_Table($entity, $table_config);
$table->render();