<?php
/**
 * Account Types List Template
 * 
 * @version 6.0.0 - FIXED: echo + callbacks injection into config
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// LOAD SAW TABLE COMPONENT
// ============================================
$autoload_path = SAW_VISITORS_PLUGIN_DIR . 'includes/components/saw-table/autoload.php';
if (file_exists($autoload_path)) {
    require_once $autoload_path;
}

// ============================================
// CHECK IF SAW TABLE IS AVAILABLE
// ============================================
if (!function_exists('saw_table_render_list')) {
    echo '<div class="notice notice-error"><p>SAW Table component not loaded.</p></div>';
    return;
}

// ============================================
// PREPARE VARIABLES
// ============================================
$items = $items ?? [];
$total = $total ?? count($items);
$current_tab = $current_tab ?? 'all';
$tab_counts = $tab_counts ?? [
    'all' => $total,
    'active' => 0,
    'inactive' => 0,
];
$sidebar_mode = $sidebar_mode ?? null;
$detail_item = $detail_item ?? null;

// ============================================
// DEFINE COLUMN CALLBACKS
// ============================================
$column_callbacks = [
    // Color swatch
    'color' => function($value, $item) {
        if (empty($value)) {
            return '<span class="sawt-color-swatch" style="background: #ccc;"></span>';
        }
        return sprintf(
            '<span class="sawt-color-swatch" style="background: %s;"></span>',
            esc_attr($value)
        );
    },
    
    // Price with badge
    'price' => function($value, $item) {
        $price = floatval($value);
        if ($price <= 0) {
            return '<span class="sawt-badge sawt-badge-success">Zdarma</span>';
        }
        return number_format($price, 0, ',', ' ') . ' Kč';
    },
    
    // Customers count badge
    'customers_count' => function($value, $item) {
        $count = intval($value);
        if ($count === 0) {
            return '—';
        }
        return sprintf(
            '<span class="sawt-badge sawt-badge-info">%d</span>',
            $count
        );
    },
];

// ============================================
// INJECT CALLBACKS INTO CONFIG
// This is required because SAW Table expects
// callbacks inside column config with type 'custom'
// ============================================
foreach ($column_callbacks as $key => $callback) {
    if (isset($config['table']['columns'][$key])) {
        $config['table']['columns'][$key]['callback'] = $callback;
        $config['table']['columns'][$key]['type'] = 'custom';
    }
}

// ============================================
// RENDER LIST - MUST USE ECHO!
// ============================================
echo saw_table_render_list([
    'config' => $config,
    'items' => $items,
    'total' => $total,
    'current_tab' => $current_tab,
    'tab_counts' => $tab_counts,
    'sidebar_mode' => $sidebar_mode,
    'detail_item' => $detail_item,
]);
