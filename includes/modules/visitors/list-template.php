<?php
/**
 * Visitors List Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visitors
 * @version     3.0.0 - Refactored to use new admin-table structure
 */

if (!defined('ABSPATH')) {
    exit;
}

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

// Compute current status for each visitor
global $wpdb;
$today = current_time('Y-m-d');

foreach ($items as &$item) {
    // Get today's LATEST log (pro re-entry support)
    $log = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}saw_visit_daily_logs 
         WHERE visitor_id = %d AND log_date = %s
         ORDER BY checked_in_at DESC
         LIMIT 1",
        $item['id'], $today
    ), ARRAY_A);
    
    // Compute dynamic status
    if ($item['participation_status'] === 'confirmed') {
        if ($log && $log['checked_in_at'] && !$log['checked_out_at']) {
            $item['current_status'] = 'present'; // Přítomen
        } elseif ($log && $log['checked_out_at']) {
            $item['current_status'] = 'checked_out'; // Odhlášen
        } else {
            $item['current_status'] = 'confirmed'; // Potvrzený (ale dnes ještě nepřišel)
        }
    } elseif ($item['participation_status'] === 'no_show') {
        $item['current_status'] = 'no_show';
    } else {
        $item['current_status'] = 'planned';
    }
    
    // Compute training status
    if ($item['training_skipped']) {
        $item['training_status'] = 'skipped';
    } elseif ($item['training_completed_at']) {
        $item['training_status'] = 'completed';
    } elseif ($item['training_started_at']) {
        $item['training_status'] = 'in_progress';
    } else {
        $item['training_status'] = 'not_started';
    }
}
unset($item); // Break reference

// Build table config
$table_config = array(
    'title' => 'Návštěvníci',
    'create_url' => home_url('/admin/visitors/create'),
    'edit_url' => home_url('/admin/visitors/{id}/edit'),
    'detail_url' => home_url('/admin/visitors/{id}/'),
    
    'sidebar_mode' => $sidebar_mode ?? null,
    'detail_item' => $detail_item ?? null,
    'form_item' => $form_item ?? null,
    'detail_tab' => $detail_tab ?? 'overview',
    'related_data' => $related_data ?? null,
    
    // Pass module config for sidebar (singular, route, etc.)
    'module_config' => isset($config) ? $config : array(),
    
    'rows' => $items,
    'total_items' => $total,
    'current_page' => $page,
    'total_pages' => $total_pages,
    'orderby' => $orderby,
    'order' => $order,
    
    'actions' => array('view', 'edit', 'delete'),
    'empty_message' => 'Žádní návštěvníci nenalezeni',
    'add_new' => 'Nový návštěvník',
    
    'ajax_enabled' => true,
    'ajax_nonce' => $ajax_nonce,
);

// Search configuration
$table_config['search'] = array(
    'enabled' => true,
    'placeholder' => 'Hledat návštěvníky...',
    'fields' => array('first_name', 'last_name', 'email'),
    'show_info_banner' => true,
);

// Filters configuration
// Removed participation_status filter - not needed due to grouping by current_status
$table_config['filters'] = array(
    'training_status' => array(
        'label' => 'Školení',
        'type' => 'select',
        'options' => array(
            '' => 'Všechny',
            'completed' => '✅ Dokončeno',
            'in_progress' => '🔄 Probíhá',
            'skipped' => '⏭️ Přeskočeno',
            'not_started' => '⚪ Nespuštěno',
        ),
    ),
);

// Columns configuration
$table_config['columns'] = array(
    'first_name' => array(
        'label' => 'Jméno',
        'type' => 'text',
        'class' => 'saw-table-cell-bold',
        'sortable' => true,
    ),
    'last_name' => array(
        'label' => 'Příjmení',
        'type' => 'text',
        'class' => 'saw-table-cell-bold',
        'sortable' => true,
    ),
    'company_name' => array(
        'label' => 'Firma',
        'type' => 'text',
    ),
    'branch_name' => array(
        'label' => 'Pobočka',
        'type' => 'text',
    ),
    'current_status' => array(
        'label' => 'Aktuální stav',
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
            'present' => '✅ Přítomen',
            'checked_out' => '🚪 Odhlášen',
            'confirmed' => '⏳ Potvrzený',
            'planned' => '📅 Plánovaný',
            'no_show' => '❌ Nedostavil se',
        ),
    ),
    'first_checkin_at' => array(
        'label' => 'První check-in',
        'type' => 'callback',
        'callback' => function($value) {
            return !empty($value) ? date('d.m.Y H:i', strtotime($value)) : '—';
        },
    ),
    'last_checkout_at' => array(
        'label' => 'Poslední check-out',
        'type' => 'callback',
        'callback' => function($value) {
            return !empty($value) ? date('d.m.Y H:i', strtotime($value)) : '—';
        },
    ),
    'training_status' => array(
        'label' => 'Školení',
        'type' => 'badge',
        'map' => array(
            'completed' => 'success',
            'in_progress' => 'info',
            'skipped' => 'warning',
            'not_started' => 'secondary',
        ),
        'labels' => array(
            'completed' => '✅ Dokončeno',
            'in_progress' => '🔄 Probíhá',
            'skipped' => '⏭️ Přeskočeno',
            'not_started' => '⚪ Nespuštěno',
        ),
    ),
);

// TABS configuration - loaded from config.php
$table_config['tabs'] = $config['tabs'] ?? null;

// Infinite scroll - UPRAVENÉ hodnoty
$table_config['infinite_scroll'] = array(
    'enabled' => true, // Enable infinite scroll
    'initial_load' => 100, // NOVÉ: První načtení 100 řádků
    'per_page' => 50, // Poté po 50 řádcích
    'threshold' => 0.6, // OPRAVENO 2025-01-22: 60% scroll pro dřívější loading
);

// NOVÉ: Pass tab data from get_list_data() result
// CRITICAL: Ensure current_tab is always a valid string, never null
if (!empty($table_config['tabs']['enabled'])) {
    // Use isset() and !== null/'' to handle all cases
    $table_config['current_tab'] = (isset($current_tab) && $current_tab !== null && $current_tab !== '') 
        ? (string)$current_tab 
        : ($table_config['tabs']['default_tab'] ?? 'all');
    $table_config['tab_counts'] = (isset($tab_counts) && is_array($tab_counts)) ? $tab_counts : array();
}

// Render
$table = new SAW_Component_Admin_Table('visitors', $table_config);
$table->render();
?>
