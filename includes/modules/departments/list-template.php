<?php
/**
 * Departments List Template - SIDEBAR VERSION
 * 
 * Uses global AdminTable component with sidebar support for detail/create/edit.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Departments
 * @since       1.0.0
 * @version     2.1.0 - FIXED: Added related_data support
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load required components
if (!class_exists('SAW_Component_Search')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/search/class-saw-component-search.php';
}

if (!class_exists('SAW_Component_Selectbox')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/selectbox/class-saw-component-selectbox.php';
}

if (!class_exists('SAW_Component_Admin_Table')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';
}

// Global nonce for AJAX
$ajax_nonce = wp_create_nonce('saw_ajax_nonce');

// Prepare search component HTML
ob_start();
$search_component = new SAW_Component_Search('departments', array(
    'placeholder' => __('Hledat oddělení...', 'saw-visitors'),
    'search_value' => $search,
    'ajax_enabled' => false,
    'show_button' => true,
    'show_info_banner' => true,
    'info_banner_label' => __('Vyhledávání:', 'saw-visitors'),
    'clear_url' => home_url('/admin/departments/'),
));
$search_component->render();
$search_html = ob_get_clean();

// Prepare filter component HTML
ob_start();
if (!empty($this->config['list_config']['filters']['is_active'])) {
    $status_filter = new SAW_Component_Selectbox('is_active-filter', array(
        'options' => array(
            '' => __('Všechny statusy', 'saw-visitors'),
            '1' => __('Aktivní', 'saw-visitors'),
            '0' => __('Neaktivní', 'saw-visitors'),
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

// Fetch branches for dropdown in form
global $wpdb;
$customer_id = SAW_Context::get_customer_id();
$branches = array();
if ($customer_id) {
    $branches = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name FROM %i WHERE customer_id = %d AND is_active = 1 ORDER BY name ASC",
        $wpdb->prefix . 'saw_branches',
        $customer_id
    ), ARRAY_A);
}

// Format branches for form dropdown
$branches_options = array();
foreach ($branches as $branch) {
    $branches_options[$branch['id']] = $branch['name'];
}
?>

<!-- CRITICAL: Module wrapper for proper layout -->
<div class="saw-module-departments">
    <?php
    // Initialize admin table component with sidebar support
    $table = new SAW_Component_Admin_Table('departments', array(
        'title' => __('Oddělení', 'saw-visitors'),
        'create_url' => home_url('/admin/departments/create'),
        'edit_url' => home_url('/admin/departments/{id}/edit'),
        'detail_url' => home_url('/admin/departments/{id}/'),
        
        // CRITICAL: Pass module config for auto-generation
        'module_config' => $this->config,
        
        // ✅ CRITICAL FIX: Sidebar support with related_data
        'sidebar_mode' => $sidebar_mode ?? null,
        'detail_item' => $detail_item ?? null,
        'form_item' => $form_item ?? null,
        'detail_tab' => $detail_tab ?? 'overview',
        'related_data' => $related_data ?? null,
        'is_edit' => $is_edit ?? false,
        
        // Lookups for form (branches dropdown)
        'branches' => $branches_options,
        
        // Custom column formatting
        'column_formats' => array(
            'branch_id' => array(
                'label' => 'Pobočka',
                'type' => 'text',
                'format' => function($value, $item) use ($branches) {
                    foreach ($branches as $branch) {
                        if ($branch['id'] == $value) {
                            return $branch['name'];
                        }
                    }
                    return '-';
                }
            ),
            'training_version' => array(
                'label' => 'Verze školení',
                'type' => 'badge',
                'map' => array(
                    'default' => 'primary'
                ),
                'format' => function($value) {
                    return 'v' . $value;
                }
            ),
            'is_active' => array(
                'label' => 'Status',
                'type' => 'badge',
                'map' => array(
                    '1' => 'success',
                    '0' => 'secondary'
                ),
                'labels' => array(
                    '1' => 'Aktivní',
                    '0' => 'Neaktivní'
                )
            ),
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
        'actions' => array('view', 'edit', 'delete'),
        
        // Messages
        'empty_message' => __('Žádná oddělení nenalezena', 'saw-visitors'),
        'add_new' => __('Nové oddělení', 'saw-visitors'),
        
        // AJAX configuration
        'ajax_enabled' => true,
        'ajax_nonce' => $ajax_nonce,
    ));

    // Render table with sidebar
    $table->render();
    ?>
</div>

<script>
jQuery(document).ready(function($) {
    console.log('[Departments] List template loaded, sidebar mode:', '<?php echo esc_js($sidebar_mode ?? 'none'); ?>');
});
</script>