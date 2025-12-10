<?php
/**
 * Account Types List Template
 * 
 * Uses new SAW Table component system.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @version     4.0.0 - NEW: Using SAW Table component
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
}

// ============================================
// TRANSLATION SETUP
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}

$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'account-types') 
    : [];

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// PREPARE DATA
// ============================================

$entity = $config['entity'] ?? 'account_types';
$route = $config['route'] ?? 'account-types';
$base_url = home_url('/admin/' . $route);

// Table columns - override from config with custom callbacks
$columns = [
    'color' => [
        'label' => $tr('field_color', 'Barva'),
        'type' => 'custom',
        'width' => '80px',
        'align' => 'center',
        'sortable' => false,
        'callback' => function($value) {
            if (empty($value)) {
                return '<span class="saw-table-text-muted">—</span>';
            }
            return '<div class="saw-table-color-badge" style="background-color: ' . esc_attr($value) . ';"></div>';
        }
    ],
    'display_name' => [
        'label' => $tr('field_display_name', 'Zobrazovaný název'),
        'type' => 'text',
        'sortable' => true,
        'bold' => true,
    ],
    'name' => [
        'label' => $tr('field_name', 'Interní název'),
        'type' => 'custom',
        'sortable' => true,
        'callback' => function($value) {
            return '<code class="saw-table-code">' . esc_html($value) . '</code>';
        }
    ],
    'price' => [
        'label' => $tr('field_price', 'Cena'),
        'type' => 'custom',
        'align' => 'right',
        'width' => '120px',
        'sortable' => true,
        'callback' => function($value) {
            $price = floatval($value ?? 0);
            if ($price > 0) {
                return '<strong>' . number_format($price, 0, ',', ' ') . ' Kč</strong>';
            }
            return '<span class="saw-table-text-muted">Zdarma</span>';
        }
    ],
    'features_count' => [
        'label' => $tr('field_features', 'Funkce'),
        'type' => 'custom',
        'align' => 'center',
        'width' => '100px',
        'callback' => function($value, $item) {
            $count = intval($value ?? 0);
            if ($count > 0) {
                return '<span class="saw-table-badge saw-table-badge-info">' . $count . ' funkcí</span>';
            }
            return '<span class="saw-table-text-muted">—</span>';
        }
    ],
    'is_active' => [
        'label' => $tr('field_status', 'Status'),
        'type' => 'badge',
        'align' => 'center',
        'width' => '100px',
        'map' => [
            '1' => ['label' => $tr('status_active', 'Aktivní'), 'color' => 'success'],
            '0' => ['label' => $tr('status_inactive', 'Neaktivní'), 'color' => 'secondary'],
        ],
    ],
];

// Merge columns into config
$config['columns'] = $columns;
$config['table']['columns'] = $columns;

// Actions
$config['actions'] = ['view', 'edit', 'delete'];

// URLs
$config['detail_url'] = $base_url . '/{id}/';
$config['edit_url'] = $base_url . '/{id}/edit';
$config['create_url'] = $base_url . '/create';

// Empty message
$config['empty_message'] = $tr('empty_message', 'Žádné typy účtů nenalezeny');

// Ensure items array
$items = $items ?? [];
$total = $total ?? count($items);

// Sidebar state
$sidebar_mode = $sidebar_mode ?? null;
$detail_item = $detail_item ?? null;
$form_item = $form_item ?? null;
$related_data = $related_data ?? [];

// Tab state (this module doesn't use tabs)
$current_tab = null;
$tab_counts = [];

// ============================================
// RENDER USING SAW TABLE
// ============================================

if (function_exists('saw_table_render_list')) {
    saw_table_render_list($config, $items, [
        'entity' => $entity,
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
    // Fallback - include template directly
    $template = SAW_VISITORS_PLUGIN_DIR . 'includes/components/saw-table/templates/list.php';
    
    if (file_exists($template)) {
        include $template;
    } else {
        echo '<div class="saw-alert saw-alert-danger">SAW Table template not found</div>';
    }
}
