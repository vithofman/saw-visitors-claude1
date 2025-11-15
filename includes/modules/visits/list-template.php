<?php
/**
 * Companies List Template - SIDEBAR VERSION
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Companies
 * @since       1.0.0
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SAW_Component_Admin_Table')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';
}

$ajax_nonce = wp_create_nonce('saw_ajax_nonce');

// Load branches for sidebar
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
?>

<!-- CRITICAL: Module wrapper for proper layout -->
<div class="saw-module-visits">
    <?php
    // Initialize admin table component with sidebar support
    $table = new SAW_Component_Admin_Table('visits', array(
        'title' => 'Návštěvy',
        'create_url' => home_url('/admin/visits/create'),
        'edit_url' => home_url('/admin/visits/{id}/edit'),
        'detail_url' => home_url('/admin/visits/{id}/'),
        
        // CRITICAL: Pass module config for auto-generation
        'module_config' => $this->config,
        
        // CRITICAL: Sidebar support
        'sidebar_mode' => $sidebar_mode ?? null,
        'detail_item' => $detail_item ?? null,
        'form_item' => $form_item ?? null,
        'detail_tab' => $detail_tab ?? 'overview',
        'related_data' => $related_data ?? null,
        'branches' => $branches ?? array(),

        'columns' => array(
            'id' => array(
                'label' => 'ID',
                'type' => 'text',
                'sortable' => true,
                'class' => 'saw-table-cell-bold',
            ),
            'company_name' => array(
                'label' => 'Firma',
                'type' => 'text',
                'class' => 'saw-table-cell-bold',
            ),
            'planned_date_from' => array(
                'label' => 'Datum od',
                'type' => 'date',
                'sortable' => true,
            ),
            'planned_time_from' => array(
                'label' => 'Čas od',
                'type' => 'text',
            ),
            'planned_date_to' => array(
                'label' => 'Datum do',
                'type' => 'date',
            ),
            'planned_time_to' => array(
                'label' => 'Čas do',
                'type' => 'text',
            ),
            'status' => array(
                'label' => 'Stav',
                'type' => 'badge',
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
        ),
        
        'rows' => $items,
        'total_items' => $total,
        'current_page' => $page,
        'total_pages' => $total_pages,
        'orderby' => $orderby,
        'order' => $order,
        
        'actions' => array('view', 'edit', 'delete'),
        'empty_message' => 'Žádné návštěvy nenalezeny',
        'add_new' => 'Nová návštěva',
        
        'ajax_enabled' => true,
        'ajax_nonce' => $ajax_nonce,
    ));

    $table->render();
    ?>
</div>
