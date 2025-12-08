<?php
/**
 * Account Types List Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @version     3.2.0 - FIXED: type = 'custom' (not 'callback'!)
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// LOAD ADMIN TABLE COMPONENT
// ============================================
if (!class_exists('SAW_Component_Admin_Table')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';
}

// ============================================
// BUILD TABLE CONFIG
// ============================================
$table_config = $config;
$base_url = home_url('/admin/' . ($config['route'] ?? 'account-types'));

$table_config['title'] = $config['plural'] ?? 'Typy účtů';
$table_config['create_url'] = $base_url . '/create';
$table_config['detail_url'] = $base_url . '/{id}/';
$table_config['edit_url'] = $base_url . '/{id}/edit';

// ============================================
// COLUMNS - MUST USE type: 'custom' (not 'callback'!)
// ============================================
$table_config['columns'] = array(
    'color' => array(
        'label' => 'Barva',
        'type' => 'custom',  // ← MUSÍ BÝT 'custom', NE 'callback'!
        'width' => '80px',
        'align' => 'center',
        'callback' => function($value) {
            if (empty($value)) {
                return '<span class="saw-text-muted">—</span>';
            }
            return '<div style="width: 36px; height: 36px; border-radius: 8px; background-color: ' . esc_attr($value) . '; border: 2px solid #e5e7eb; margin: 0 auto;"></div>';
        }
    ),
    'display_name' => array(
        'label' => 'Název',
        'type' => 'text',
        'sortable' => true,
        'class' => 'saw-table-cell-bold',
    ),
    'name' => array(
        'label' => 'Interní název',
        'type' => 'custom',  // ← MUSÍ BÝT 'custom'!
        'callback' => function($value) {
            return '<span class="saw-badge saw-badge-secondary" style="font-family: monospace; text-transform: uppercase;">' . esc_html($value) . '</span>';
        }
    ),
    'price' => array(
        'label' => 'Cena',
        'type' => 'custom',  // ← MUSÍ BÝT 'custom'!
        'align' => 'right',
        'width' => '120px',
        'sortable' => true,
        'callback' => function($value) {
            $price = floatval($value ?? 0);
            if ($price > 0) {
                return '<strong>' . number_format($price, 0, ',', ' ') . ' Kč</strong>';
            }
            return '<span class="saw-text-muted">Zdarma</span>';
        }
    ),
    'features' => array(
        'label' => 'Funkce',
        'type' => 'custom',  // ← MUSÍ BÝT 'custom'!
        'align' => 'center',
        'width' => '100px',
        'callback' => function($value) {
            $features = !empty($value) ? json_decode($value, true) : array();
            $count = is_array($features) ? count($features) : 0;
            if ($count > 0) {
                return '<span class="saw-badge saw-badge-info">' . $count . '</span>';
            }
            return '<span class="saw-text-muted">—</span>';
        }
    ),
    'sort_order' => array(
        'label' => 'Pořadí',
        'type' => 'text',
        'sortable' => true,
        'align' => 'center',
        'width' => '80px',
    ),
    'is_active' => array(
        'label' => 'Status',
        'type' => 'custom',  // ← MUSÍ BÝT 'custom'!
        'width' => '100px',
        'align' => 'center',
        'callback' => function($value) {
            if (!empty($value)) {
                return '<span class="saw-badge saw-badge-success">Aktivní</span>';
            }
            return '<span class="saw-badge saw-badge-secondary">Neaktivní</span>';
        }
    ),
    'created_at' => array(
        'label' => 'Vytvořeno',
        'type' => 'date',
        'sortable' => true,
        'width' => '120px',
        'format' => 'd.m.Y',
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
$table_config['orderby'] = $orderby ?? 'sort_order';
$table_config['order'] = $order ?? 'ASC';

// ============================================
// SEARCH
// ============================================
$table_config['search'] = array(
    'enabled' => true,
    'placeholder' => 'Hledat typ účtu...',
    'fields' => array('name', 'display_name'),
    'show_info_banner' => true,
);

// ============================================
// SIDEBAR CONTEXT
// ============================================
$table_config['sidebar_mode'] = $sidebar_mode ?? null;
$table_config['detail_item'] = $detail_item ?? null;
$table_config['form_item'] = $form_item ?? null;
$table_config['detail_tab'] = $detail_tab ?? 'overview';
$table_config['module_config'] = $config;

// ============================================
// ACTIONS
// ============================================
$table_config['actions'] = array('view', 'edit', 'delete');
$table_config['add_new'] = 'Nový typ účtu';
$table_config['empty_message'] = 'Žádné typy účtů nenalezeny';

// ============================================
// TABS - Pass from config
// ============================================
$table_config['tabs'] = $config['tabs'] ?? null;

// ============================================
// INFINITE SCROLL
// ============================================
$table_config['infinite_scroll'] = array(
    'enabled' => true,
    'initial_load' => 100,
    'per_page' => 50,
    'threshold' => 0.6,
);

// ============================================
// CURRENT TAB & TAB COUNTS
// ============================================
if (!empty($table_config['tabs']['enabled'])) {
    $table_config['current_tab'] = (isset($current_tab) && $current_tab !== null && $current_tab !== '') 
        ? (string)$current_tab 
        : ($table_config['tabs']['default_tab'] ?? 'all');
    $table_config['tab_counts'] = (isset($tab_counts) && is_array($tab_counts)) ? $tab_counts : array();
}

// ============================================
// RENDER
// ============================================
$entity = $config['entity'] ?? 'account_types';
$table = new SAW_Component_Admin_Table($entity, $table_config);
$table->render();
