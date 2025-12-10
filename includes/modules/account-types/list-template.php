<?php
/**
 * Account Types List Template
 * 
 * Uses SAW Table component for rendering.
 * Called by Base Controller's render_list_view().
 * 
 * Variables available from Base Controller:
 * - $items (array) - List items
 * - $total (int) - Total count
 * - $config (array) - Module config
 * - $entity (string) - Entity name
 * - $current_tab (string) - Current tab
 * - $tab_counts (array) - Counts per tab
 * - $sidebar_mode (string|null) - detail/edit/create
 * - $detail_item (array|null) - Item for detail sidebar
 * - $form_item (array|null) - Item for form
 * 
 * @version 5.0.0
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
// COLUMN CALLBACKS (safe in template context)
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
// RENDER LIST
// ============================================
saw_table_render_list([
    'config' => $config,
    'items' => $items,
    'total' => $total,
    'current_tab' => $current_tab,
    'tab_counts' => $tab_counts,
    'sidebar_mode' => $sidebar_mode,
    'detail_item' => $detail_item,
    'column_callbacks' => $column_callbacks,
]);
