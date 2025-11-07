<?php
/**
 * Departments List Template
 * 
 * Displays a searchable, filterable, paginated list of departments.
 * Uses SAW Component system for search, filters, table, and modal.
 * 
 * Available Variables:
 * @var array $items List of department records
 * @var int $total Total number of departments (for pagination)
 * @var int $page Current page number
 * @var int $total_pages Total number of pages
 * @var string $search Current search query
 * @var string $orderby Current sort column
 * @var string $order Current sort direction (ASC/DESC)
 * @var object $this Controller instance
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Departments
 * @since       1.0.0
 * @author      SAW Visitors Dev Team
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ================================================
// LOAD REQUIRED COMPONENTS
// ================================================

if (!class_exists('SAW_Component_Search')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/search/class-saw-component-search.php';
}

if (!class_exists('SAW_Component_Selectbox')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/selectbox/class-saw-component-selectbox.php';
}

if (!class_exists('SAW_Component_Admin_Table')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';
}

if (!class_exists('SAW_Component_Modal')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/modal/class-saw-component-modal.php';
}

// ================================================
// PREPARE SEARCH COMPONENT
// ================================================

ob_start();
$search_component = new SAW_Component_Search('departments', array(
    'placeholder' => 'Hledat oddělení...',
    'search_value' => $search,
    'ajax_enabled' => false,
    'ajax_action' => 'saw_search_departments',
    'show_button' => true,
    'show_info_banner' => true,
    'info_banner_label' => 'Vyhledávání:',
    'clear_url' => home_url('/admin/departments/'),
));
$search_component->render();
$search_html = ob_get_clean();

// ================================================
// PREPARE FILTERS
// ================================================

ob_start();

// Status filter (Active/Inactive)
if (!empty($this->config['list_config']['filters']['is_active'])) {
    $status_filter = new SAW_Component_Selectbox('is_active-filter', array(
        'options' => array(
            '' => 'Všechny statusy',
            '1' => 'Aktivní',
            '0' => 'Neaktivní',
        ),
        'selected' => $_GET['is_active'] ?? '',
        'on_change' => 'redirect',
        'allow_empty' => true,
        'custom_class' => 'saw-filter-select',
        'name' => 'is_active',
    ));
    $status_filter->render();
}

$filters_html = ob_get_clean();

// ================================================
// FETCH BRANCH NAMES FOR ITEMS
// ================================================

if (!empty($items)) {
    global $wpdb;
    $branch_ids = array_unique(array_column($items, 'branch_id'));
    
    if (!empty($branch_ids)) {
        // Build placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($branch_ids), '%d'));
        
        // Fetch branch names
        $branches_data = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name FROM %i WHERE id IN ($placeholders)",
            $wpdb->prefix . 'saw_branches',
            ...$branch_ids
        ), ARRAY_A);
        
        // Create branch ID => name map
        $branches_map = array();
        foreach ($branches_data as $branch) {
            $branches_map[$branch['id']] = $branch['name'];
        }
        
        // Add branch_name to each item
        foreach ($items as &$item) {
            $item['branch_name'] = $branches_map[$item['branch_id']] ?? 'N/A';
        }
    }
}

// ================================================
// INITIALIZE ADMIN TABLE
// ================================================

$table = new SAW_Component_Admin_Table('departments', array(
    'title' => 'Oddělení',
    'create_url' => home_url('/admin/departments/new/'),
    'edit_url' => home_url('/admin/departments/edit/{id}/'),
    
    // Column definitions
    'columns' => array(
        
        // Department Number
        'department_number' => array(
            'label' => 'Číslo',
            'type' => 'custom',
            'width' => '100px',
            'callback' => function($value) {
                if (!empty($value)) {
                    return '<span class="saw-code-badge">' . esc_html($value) . '</span>';
                }
                return '<span class="saw-text-muted">—</span>';
            }
        ),
        
        // Department Name
        'name' => array(
            'label' => 'Název oddělení',
            'type' => 'text',
            'sortable' => true,
            'bold' => true
        ),
        
        // Branch Name
        'branch_name' => array(
            'label' => 'Pobočka',
            'type' => 'text',
        ),
        
        // Training Version
        'training_version' => array(
            'label' => 'Verze školení',
            'type' => 'custom',
            'align' => 'center',
            'width' => '120px',
            'callback' => function($value) {
                return '<span class="saw-badge saw-badge-info">v' . esc_html($value) . '</span>';
            }
        ),
        
        // Active Status
        'is_active' => array(
            'label' => 'Status',
            'type' => 'badge',
            'width' => '100px',
            'align' => 'center',
            'map' => array(
                '1' => 'success',
                '0' => 'secondary'
            ),
            'labels' => array(
                '1' => 'Aktivní',
                '0' => 'Neaktivní'
            )
        ),
        
        // Created Date
        'created_at' => array(
            'label' => 'Vytvořeno',
            'type' => 'date',
            'format' => 'd.m.Y'
        )
    ),
    
    // Data and pagination
    'rows' => $items,
    'total_items' => $total,
    'current_page' => $page,
    'total_pages' => $total_pages,
    'orderby' => $orderby,
    'order' => $order,
    
    // Search and filters
    'search' => $search_html,
    'filters' => $filters_html,
    
    // Actions
    'actions' => array('edit', 'delete'),
    
    // Messages
    'empty_message' => 'Žádná oddělení nenalezena',
    'add_new' => 'Nové oddělení',
    
    // Modal configuration
    'enable_modal' => true,
    'modal_id' => 'department-detail',
    'modal_ajax_action' => 'saw_get_departments_detail',
));

// Render table
$table->render();

// ================================================
// MODAL COMPONENT
// ================================================

$department_modal = new SAW_Component_Modal('department-detail', array(
    'title' => 'Detail oddělení',
    'ajax_enabled' => true,
    'ajax_action' => 'saw_get_departments_detail',
    'size' => 'large',
    'show_close' => true,
    'close_on_backdrop' => true,
    'close_on_escape' => true,
    
    // Header actions (edit, delete)
    'header_actions' => array(
        array(
            'type' => 'edit',
            'label' => '',
            'icon' => 'dashicons-edit',
            'url' => home_url('/admin/departments/edit/{id}/'),
        ),
        array(
            'type' => 'delete',
            'label' => '',
            'icon' => 'dashicons-trash',
            'confirm' => true,
            'confirm_message' => 'Opravdu chcete smazat toto oddělení?',
            'ajax_action' => 'saw_delete_departments',
        ),
    ),
));

// Render modal
$department_modal->render();