<?php
/**
 * Visits List Template - REFACTORED
 * @version 4.0.0 - Refactored to use new admin-table system with search & filters
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('SAW_Component_Admin_Table')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';
}

global $wpdb;
$customer_id = SAW_Context::get_customer_id();
$branches = array();
if ($customer_id) {
    $branches_data = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name FROM %i WHERE customer_id = %d AND is_active = 1 ORDER BY name ASC",
        $wpdb->prefix . 'saw_branches', $customer_id
    ), ARRAY_A);
    foreach ($branches_data as $branch) {
        $branches[$branch['id']] = $branch['name'];
    }
}

// Prepare config for AdminTable
$table_config = $config;
$base_url = home_url('/admin/' . ($config['route'] ?? 'visits'));

$table_config['title'] = $config['plural'];
$table_config['create_url'] = $base_url . '/create';
$table_config['detail_url'] = $base_url . '/{id}/';
$table_config['edit_url'] = $base_url . '/{id}/edit';

// Search configuration - NEW FORMAT
$table_config['search'] = array(
    'enabled' => true,
    'placeholder' => 'Hledat návštěvy...',
    'fields' => array('id', 'company_name', 'first_visitor_name', 'last_name'), // Fields to search in
    'show_info_banner' => true,
);

// Get filter values - these are passed from render_list_view via get_list_data()
// All GET params except s, orderby, order, paged are passed through
// Filter values are automatically available as $status, $visit_type, etc.

// Filters configuration - NEW FORMAT
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

// Sidebar context
$table_config['sidebar_mode'] = $sidebar_mode ?? null;
$table_config['detail_item'] = $detail_item ?? null;
$table_config['form_item'] = $form_item ?? null;
$table_config['detail_tab'] = $detail_tab ?? 'overview';
$table_config['module_config'] = $config;
$table_config['related_data'] = $related_data ?? null;
$table_config['branches'] = $branches ?? array();
        
// Column definitions
$table_config['columns'] = array(
    // ID column removed as per plan
    'company_person' => array(
        'label' => 'Návštěvník',
        'type' => 'custom',
        'sortable' => false,
        'class' => 'saw-table-cell-bold',
        'callback' => function($value, $item) {
            if (!empty($item['company_id'])) {
                echo '<div style="display: flex; align-items: center; gap: 8px;">';
                echo '<strong>' . esc_html($item['company_name'] ?? 'Firma #' . $item['company_id']) . '</strong>';
                echo '<span class="saw-badge saw-badge-info" style="font-size: 11px;">🏢 Firma</span>';
                echo '</div>';
            } else {
                echo '<div style="display: flex; align-items: center; gap: 8px;">';
                if (!empty($item['first_visitor_name'])) {
                    echo '<strong style="color: #6366f1;">' . esc_html($item['first_visitor_name']) . '</strong>';
                } else {
                    echo '<strong style="color: #6366f1;">Fyzická osoba</strong>';
                }
                echo '<span class="saw-badge" style="background: #6366f1; color: white; font-size: 11px;">👤 Fyzická</span>';
                echo '</div>';
            }
        },
    ),
    'branch_name' => array(
        'label' => 'Pobočka',
        'type' => 'text',
        'sortable' => false,
    ),
    'visit_type' => array(
        'label' => 'Typ',
        'type' => 'badge',
        'width' => '120px',
        'map' => array(
            'planned' => 'info',
            'walk_in' => 'warning',
        ),
        'labels' => array(
            'planned' => 'Plánovaná',
            'walk_in' => 'Walk-in',
        ),
    ),
    'visitor_count' => array(
        'label' => 'Počet',
        'type' => 'custom',
        'width' => '100px',
        'align' => 'center',
        'callback' => function($value, $item) {
            $count = intval($item['visitor_count'] ?? 0);
            if ($count === 0) {
                echo '<span style="color: #999;">—</span>';
            } else {
                echo '<strong style="color: #0066cc;">👥 ' . $count . '</strong>';
            }
        },
    ),
    'status' => array(
        'label' => 'Stav',
        'type' => 'badge',
        'sortable' => true,
        'width' => '140px',
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
    'created_at' => array(
        'label' => 'Vytvořeno',
        'type' => 'date',
        'sortable' => true,
        'width' => '120px',
        'format' => 'd.m.Y',
    ),
);

// Data
$table_config['rows'] = $items;
$table_config['total_items'] = $total;
$table_config['current_page'] = $page;
$table_config['total_pages'] = $total_pages;
$table_config['orderby'] = $orderby;
$table_config['order'] = $order;

// Actions
$table_config['actions'] = array('view', 'edit', 'delete');
$table_config['add_new'] = 'Nová návštěva';
$table_config['empty_message'] = 'Žádné návštěvy nenalezeny';

// TABS configuration - NOVÝ formát (replaces grouping)
$table_config['tabs'] = array(
    'enabled' => true,
    'tab_param' => 'status', // GET parameter pro tab (?status=confirmed)
    'tabs' => array(
        'all' => array(
            'label' => 'Všechny',
            'icon' => '📋',
            'filter_value' => null, // null = no filter (all records)
            'count_query' => true, // Automaticky spočítat
        ),
        'draft' => array(
            'label' => 'Koncept',
            'icon' => '📝',
            'filter_value' => 'draft',
            'count_query' => true,
        ),
        'pending' => array(
            'label' => 'Čekající',
            'icon' => '⏳',
            'filter_value' => 'pending',
            'count_query' => true,
        ),
        'confirmed' => array(
            'label' => 'Potvrzená',
            'icon' => '✅',
            'filter_value' => 'confirmed',
            'count_query' => true,
        ),
        'in_progress' => array(
            'label' => 'Probíhající',
            'icon' => '🔄',
            'filter_value' => 'in_progress',
            'count_query' => true,
        ),
        'completed' => array(
            'label' => 'Dokončená',
            'icon' => '✔️',
            'filter_value' => 'completed',
            'count_query' => true,
        ),
        'cancelled' => array(
            'label' => 'Zrušená',
            'icon' => '❌',
            'filter_value' => 'cancelled',
            'count_query' => true,
        ),
    ),
    'default_tab' => 'all', // Výchozí aktivní tab
);

// Infinite scroll - UPRAVENÉ hodnoty
$table_config['infinite_scroll'] = array(
    'enabled' => true,
    'initial_load' => 100, // NOVÉ: První načtení 100 řádků
    'per_page' => 50, // Poté po 50 řádcích
    'threshold' => 0.7, // NOVÉ: 70% scroll (místo 300px)
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
$table = new SAW_Component_Admin_Table('visits', $table_config);
$table->render();
?>
</div>
