<?php
/**
 * Account Types - List Template
 *
 * Uses SAW Table component.
 * CALLBACKS ARE DEFINED HERE, not in config.php!
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @version     4.2.0 - FIXED: Callbacks in template
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// LOAD SAW TABLE
// ============================================

$autoload = SAW_VISITORS_PLUGIN_DIR . 'includes/components/saw-table/autoload.php';
if (!file_exists($autoload)) {
    echo '<div class="notice notice-error"><p>SAW Table component not found!</p></div>';
    return;
}
require_once $autoload;

// ============================================
// VARIABLES (from controller)
// ============================================

$items = $items ?? [];
$total = $total ?? count($items);
$current_tab = $current_tab ?? 'all';
$tab_counts = $tab_counts ?? [];
$detail_item = $detail_item ?? null;
$form_item = $form_item ?? null;
$sidebar_mode = $sidebar_mode ?? null;
$related_data = $related_data ?? [];

// ============================================
// DEFINE CALLBACKS (safe - in template)
// ============================================

// Color swatch
if (isset($config['table']['columns']['color'])) {
    $config['table']['columns']['color']['callback'] = function($value, $item) {
        if (empty($value)) {
            return '<span class="sawt-text-muted">—</span>';
        }
        return sprintf(
            '<div class="sawt-color-swatch" style="background-color:%s;width:24px;height:24px;border-radius:4px;border:2px solid #e5e7eb;"></div>',
            esc_attr($value)
        );
    };
}

// Price
if (isset($config['table']['columns']['price'])) {
    $config['table']['columns']['price']['callback'] = function($value, $item) {
        $price = intval($value ?? 0);
        if ($price === 0) {
            return '<span class="sawt-badge sawt-badge-success">Zdarma</span>';
        }
        return '<strong>' . number_format($price, 0, ',', ' ') . ' Kč</strong>';
    };
}

// Customers count
if (isset($config['table']['columns']['customers_count'])) {
    $config['table']['columns']['customers_count']['callback'] = function($value, $item) {
        $count = intval($value ?? 0);
        if ($count === 0) {
            return '<span class="sawt-text-muted">0</span>';
        }
        return '<span class="sawt-badge sawt-badge-info">' . $count . '</span>';
    };
}

// ============================================
// RENDER
// ============================================

$tr = function($key, $fallback = null) {
    return $fallback ?? $key;
};

if (function_exists('saw_table_render_list')) {
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
} else {
    // Fallback
    echo '<div class="wrap"><h1>' . esc_html($config['plural'] ?? 'Typy účtů') . '</h1>';
    echo '<p>SAW Table render function not available.</p>';
    echo '<pre>' . print_r(array_keys(get_defined_functions()['user']), true) . '</pre>';
    echo '</div>';
}
