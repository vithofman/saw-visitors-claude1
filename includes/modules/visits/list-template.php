<?php
/**
 * Visits List Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
 * @version     3.0.0 - REFACTORED: Shows physical person name when no company
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SAW_Component_Admin_Table')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';
}

$ajax_nonce = wp_create_nonce('saw_ajax_nonce');

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

// Připrav data pro search a filtry
$search_value = $search ?? '';
$status_filter = $status_filter ?? '';

?>

<div class="saw-module-visits">
    <?php
    $table = new SAW_Component_Admin_Table('visits', array(
        'title' => 'Návštěvy',
        'icon' => '📅',
        'create_url' => home_url('/admin/visits/create'),
        'edit_url' => home_url('/admin/visits/{id}/edit'),
        'detail_url' => home_url('/admin/visits/{id}/'),
        
        'module_config' => $this->config,
        
        // ✅ ZAPNUTÍ SEARCH
        'enable_search' => true,
        'search_placeholder' => 'Hledat návštěvu...',
        'search_value' => $search_value,
        
        // ✅ ZAPNUTÍ FILTRŮ
        'enable_filters' => true,
        'filters' => array(
            'status' => array(
                'label' => 'Stav',
                'type' => 'select',
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
        ),
        'active_filters' => array(
            'status' => $status_filter,
        ),
        
        // Sidebar
        'sidebar_mode' => $sidebar_mode ?? null,
        'detail_item' => $detail_item ?? null,
        'form_item' => $form_item ?? null,
        'detail_tab' => $detail_tab ?? 'overview',
        'related_data' => $related_data ?? null,
        'branches' => $branches ?? array(),
        
        // Columns
        'columns' => array(
            'company_person' => array(
                'label' => 'Návštěvník',
                'type' => 'custom',
                'sortable' => false,
                'callback' => function($value, $item) {
                    if (!empty($item['company_id'])) {
                        // Legal person (company)
                        echo '<div style="display: flex; align-items: center; gap: 8px;">';
                        echo '<strong>' . esc_html($item['company_name']) . '</strong>';
                        echo '<span class="saw-badge saw-badge-info" style="font-size: 11px;">Firma</span>';
                        echo '</div>';
                    } else {
                        // Physical person
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
            'schedule_dates_formatted' => array(
                'label' => 'Naplánované dny',
                'type' => 'html_raw',
            ),
            'status' => array(
                'label' => 'Stav',
                'type' => 'badge',
                'sortable' => true,
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
        
        // Data
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