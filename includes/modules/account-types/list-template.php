<?php
/**
 * Account Types - List Template
 *
 * Uses SAW Table component for rendering.
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @version     4.0.0 - SAW Table Integration
 * @since       1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// LOAD SAW TABLE COMPONENT
// ============================================

$saw_table_autoload = SAW_VISITORS_PLUGIN_DIR . 'includes/components/saw-table/autoload.php';
if (file_exists($saw_table_autoload)) {
    require_once $saw_table_autoload;
} else {
    echo '<div class="notice notice-error"><p>SAW Table component not found!</p></div>';
    return;
}

// ============================================
// SETUP TRANSLATION
// ============================================

$lang = class_exists('SAW_Component_Language_Switcher') 
    ? SAW_Component_Language_Switcher::get_user_language() 
    : 'cs';

$translations = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'account-types') 
    : [];

$tr = function($key, $fallback = null) use ($translations) {
    return $translations[$key] ?? $fallback ?? $key;
};

// Set translator for all renderers
if (function_exists('saw_table_set_translator')) {
    saw_table_set_translator($tr);
}

// ============================================
// PREPARE DATA
// ============================================

// Variables from controller
$items = $items ?? [];
$total = $total ?? count($items);
$current_tab = $current_tab ?? null;
$tab_counts = $tab_counts ?? [];
$detail_item = $detail_item ?? null;
$form_item = $form_item ?? null;
$sidebar_mode = $sidebar_mode ?? null;
$related_data = $related_data ?? [];

// ============================================
// CUSTOM COLUMN CALLBACKS
// ============================================

// Override color column with custom rendering
$config['table']['columns']['color']['callback'] = function($value, $item) {
    if (empty($value)) {
        return '<span class="sawt-text-muted">—</span>';
    }
    return sprintf(
        '<div class="sawt-color-swatch" style="background-color: %s;" title="%s"></div>',
        esc_attr($value),
        esc_attr($value)
    );
};

// Price with "zdarma" for 0
$config['table']['columns']['price']['callback'] = function($value, $item) {
    if (empty($value) || intval($value) === 0) {
        return '<span class="sawt-badge sawt-badge-success">Zdarma</span>';
    }
    return '<span class="sawt-currency">' . number_format(intval($value), 0, ',', ' ') . ' Kč</span>';
};

// Customers count with badge
$config['table']['columns']['customers_count']['callback'] = function($value, $item) {
    $count = intval($value);
    if ($count === 0) {
        return '<span class="sawt-text-muted">0</span>';
    }
    return '<span class="sawt-badge sawt-badge-info">' . $count . '</span>';
};

// ============================================
// RENDER
// ============================================

echo saw_table_render_list($config, $items, [
    'total' => $total,
    'current_tab' => $current_tab,
    'tab_counts' => $tab_counts,
    'detail_item' => $detail_item,
    'form_item' => $form_item,
    'sidebar_mode' => $sidebar_mode,
    'related_data' => $related_data,
    'tr' => $tr,
]);
