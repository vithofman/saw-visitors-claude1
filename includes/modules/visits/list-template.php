<?php
/**
 * Visits List Template - REFACTORED & OPTIMIZED
 * 
 * Features:
 * - Modern admin-table component
 * - Integrated search & filters
 * - Risk information column with warnings
 * - Infinite scroll support
 * - Tab-based filtering
 * - Responsive design
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
 * @version     4.1.0 - ENHANCED: Added risk check column, optimized callbacks
 * @since       4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load admin-table component if not already loaded
if (!class_exists('SAW_Component_Admin_Table')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';
}

// ============================================
// LOAD BRANCHES FOR FILTER
// ============================================
global $wpdb;
$customer_id = SAW_Context::get_customer_id();
$branches = array();

if ($customer_id) {
    $branches_data = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name FROM %i WHERE customer_id = %d AND is_active = 1 ORDER BY name ASC",
        $wpdb->prefix . 'saw_branches',
        $customer_id
    ), ARRAY_A);
    
    foreach ($branches_data as $branch) {
        $branches[$branch['id']] = $branch['name'];
    }
}

// ============================================
// BASE CONFIGURATION
// ============================================
$table_config = $config;
$base_url = home_url('/admin/' . ($config['route'] ?? 'visits'));

$table_config['title'] = $config['plural'];
$table_config['create_url'] = $base_url . '/create';
$table_config['detail_url'] = $base_url . '/{id}/';
$table_config['edit_url'] = $base_url . '/{id}/edit';

// ============================================
// SEARCH CONFIGURATION
// ============================================
$table_config['search'] = array(
    'enabled' => true,
    'placeholder' => 'Hledat návštěvy...',
    'fields' => array('id', 'company_name', 'first_visitor_name', 'last_name'),
    'show_info_banner' => true,
);

// ============================================
// FILTERS CONFIGURATION
// ============================================
$table_config['filters'] = array(
    'status' => array(
        'type' => 'select',
        'label' => 'Stav',
        'options' => array(
            '' => 'Všechny stavy',
            'draft' => 'Koncept',
            'pending' => 'Čekající',
            'confirmed' => 'Potvrzená',
            'in_progress' => 'Probíhající',
            'completed' => 'Dokončená',
            'cancelled' => 'Zrušená',
        ),
    ),
    'visit_type' => array(
        'type' => 'select',
        'label' => 'Typ návštěvy',
        'options' => array(
            '' => 'Všechny typy',
            'planned' => 'Plánovaná',
            'walk_in' => 'Walk-in',
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
$table_config['branches'] = $branches;

// ============================================
// COLUMN DEFINITIONS
// ============================================
$table_config['columns'] = array(
    // Visitor/Company column - combined display
    'company_person' => array(
        'label' => 'Návštěvník',
        'type' => 'custom',
        'sortable' => false,
        'class' => 'saw-table-cell-bold',
        'callback' => function($value, $item) {
            // Company visit
            if (!empty($item['company_id'])) {
                $company_name = esc_html($item['company_name'] ?? 'Firma #' . $item['company_id']);
                echo '<div style="display: flex; align-items: center; gap: 8px;">';
                echo '<strong>' . $company_name . '</strong>';
                echo '<span class="saw-badge saw-badge-info" style="font-size: 11px;">🏢 Firma</span>';
                echo '</div>';
            } 
            // Individual person
            else {
                $person_name = !empty($item['first_visitor_name']) 
                    ? esc_html($item['first_visitor_name']) 
                    : 'Fyzická osoba';
                    
                echo '<div style="display: flex; align-items: center; gap: 8px;">';
                echo '<strong style="color: #6366f1;">' . $person_name . '</strong>';
                echo '<span class="saw-badge" style="background: #6366f1; color: white; font-size: 11px;">👤 Fyzická</span>';
                echo '</div>';
            }
        },
    ),
    
    // Branch name
    'branch_name' => array(
        'label' => 'Pobočka',
        'type' => 'text',
        'sortable' => false,
        'width' => '150px',
    ),
    
    // Visit type
    'visit_type' => array(
        'label' => 'Typ',
        'type' => 'badge',
        'sortable' => false,
        'width' => '110px',
        'map' => array(
            'planned' => 'info',
            'walk_in' => 'warning',
        ),
        'labels' => array(
            'planned' => 'Plánovaná',
            'walk_in' => 'Walk-in',
        ),
    ),
    
    // Visitor count
    'visitor_count' => array(
        'label' => 'Počet',
        'type' => 'custom',
        'width' => '90px',
        'align' => 'center',
        'sortable' => false,
        'callback' => function($value, $item) {
            $count = intval($item['visitor_count'] ?? 0);
            
            if ($count === 0) {
                echo '<span style="color: #999;">—</span>';
            } else {
                echo '<strong style="color: #0066cc;">👥 ' . $count . '</strong>';
            }
        },
    ),
    
    // ✅ NEW: Risk information check
    'has_risks' => array(
        'label' => 'Rizika',
        'type' => 'custom',
        'sortable' => false,
        'width' => '100px',
        'align' => 'center',
        'callback' => function($value, $item) {
            // Get has_risks from virtual column (computed in config.php)
            $has_risks = $item['has_risks'] ?? 'no';
            $status = $item['status'] ?? '';
            
            // Show warning ONLY for confirmed visits without risks
            if ($status === 'confirmed' && $has_risks === 'no') {
                echo '<span class="saw-badge saw-badge-danger" style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px;" title="Chybí informace o rizicích">⚠️ Chybí</span>';
            } 
            // Show OK for visits with risks
            elseif ($has_risks === 'yes') {
                echo '<span class="saw-badge saw-badge-success" style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px;" title="Informace o rizicích jsou dostupné">✅ OK</span>';
            } 
            // For other statuses or unknown, show dash
            else {
                echo '<span style="color: #999;">—</span>';
            }
        },
    ),
    
    // Visit status
    'status' => array(
        'label' => 'Stav',
        'type' => 'badge',
        'sortable' => true,
        'width' => '130px',
        'map' => array(
            'draft' => 'secondary',
            'pending' => 'warning',
            'confirmed' => 'info',
            'in_progress' => 'primary',
            'completed' => 'success',
            'cancelled' => 'danger',
        ),
        'labels' => array(
            'draft' => 'Koncept',
            'pending' => 'Čekající',
            'confirmed' => 'Potvrzená',
            'in_progress' => 'Probíhající',
            'completed' => 'Dokončená',
            'cancelled' => 'Zrušená',
        ),
    ),
    
    // Created date
    'created_at' => array(
        'label' => 'Vytvořeno',
        'type' => 'date',
        'sortable' => true,
        'width' => '110px',
        'format' => 'd.m.Y',
    ),
);

// ============================================
// DATA & PAGINATION
// ============================================
$table_config['rows'] = $items;
$table_config['total_items'] = $total;
$table_config['current_page'] = $page;
$table_config['total_pages'] = $total_pages;
$table_config['orderby'] = $orderby;
$table_config['order'] = $order;

// ============================================
// ACTIONS & MESSAGES
// ============================================
$table_config['actions'] = array('view', 'edit', 'delete');
$table_config['add_new'] = 'Nová návštěva';
$table_config['empty_message'] = 'Žádné návštěvy nenalezeny';

// ============================================
// TABS CONFIGURATION
// Loaded from config.php if enabled
// ============================================
$table_config['tabs'] = $config['tabs'] ?? null;

// Ensure current_tab is always a valid string
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
    'initial_load' => 100,  // First load: 100 rows
    'per_page' => 50,       // Subsequent loads: 50 rows
    'threshold' => 0.6,     // Load more at 60% scroll
);

// ============================================
// RENDER TABLE
// ============================================
$table = new SAW_Component_Admin_Table('visits', $table_config);
$table->render();