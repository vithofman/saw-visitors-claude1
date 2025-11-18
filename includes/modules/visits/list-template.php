<?php
/**
 * Visits List Template - IMPROVED
 * @version 3.2.0 - More informative columns
 */

if (!defined('ABSPATH')) exit;

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
        
        'enable_search' => true,
        'search_placeholder' => 'Hledat návštěvu...',
        'search_value' => $search_value,
        
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
            ),
            'company_person' => array(
                'label' => 'Návštěvník',
                'type' => 'custom',
                'sortable' => false,
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
            ),
            'visit_type' => array(
                'label' => 'Typ',
                'type' => 'badge',
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
                'label' => 'Počet návštěvníků',
                'type' => 'custom',
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
                'type' => 'text',
                'sortable' => true,
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
