<?php
/**
 * Visits List Template - SIDEBAR VERSION
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
 * @version     2.1.0 - Added search and filters
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

// Filtry
$search = $search ?? '';
$status_filter = $status_filter ?? '';

$status_options = array(
    '' => 'Všechny stavy',
    'draft' => 'Koncept',
    'pending' => 'Čekající',
    'confirmed' => 'Potvrzená',
    'in_progress' => 'Probíhající',
    'completed' => 'Dokončená',
    'cancelled' => 'Zrušená',
);

// Base URL pro formuláře
$current_url = $_SERVER['REQUEST_URI'] ?? '';
if (strpos($current_url, '?') !== false) {
    $base_url = substr($current_url, 0, strpos($current_url, '?'));
} else {
    $base_url = $current_url;
}

// STATUS FILTER
$filter_html = '<div style="display: inline-flex; gap: 8px; align-items: center;">
    <form method="GET" action="' . esc_url($base_url) . '" class="saw-filters-form" style="display: inline-flex; gap: 8px; align-items: center;">';
        
if (!empty($search)) {
    $filter_html .= '<input type="hidden" name="s" value="' . esc_attr($search) . '">';
}

$filter_html .= '<select name="status" class="saw-select" onchange="this.form.submit()" style="min-width: 180px;">';

foreach ($status_options as $value => $label) {
    $selected = ($status_filter === $value) ? 'selected' : '';
    $filter_html .= '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
}

$filter_html .= '</select>
    </form>';

// Reset button - zobraz jen když je aktivní filtr
if (!empty($status_filter)) {
    $reset_url = $base_url;
    if (!empty($search)) {
        $reset_url .= '?s=' . urlencode($search);
    }
    
    $filter_html .= '<a href="' . esc_url($reset_url) . '" class="saw-button saw-button-secondary" style="padding: 6px 12px;">
        <span class="dashicons dashicons-dismiss"></span>
        Zrušit filtr
    </a>';
}

$filter_html .= '</div>';

// SEARCH
$search_html = '<form method="GET" action="' . esc_url($base_url) . '" class="saw-search-form" style="display: inline-flex; gap: 8px; align-items: center;">';
    
if (!empty($status_filter)) {
    $search_html .= '<input type="hidden" name="status" value="' . esc_attr($status_filter) . '">';
}

$search_html .= '<input type="search" 
           name="s" 
           value="' . esc_attr($search) . '" 
           placeholder="Hledat návštěvu..." 
           class="saw-search-input"
           style="min-width: 250px;">
    <button type="submit" class="saw-button saw-button-primary">
        <span class="dashicons dashicons-search"></span>
    </button>
</form>';

// Reset search button
if (!empty($search)) {
    $reset_search_url = $base_url;
    if (!empty($status_filter)) {
        $reset_search_url .= '?status=' . urlencode($status_filter);
    }
    
    $search_html .= '<a href="' . esc_url($reset_search_url) . '" class="saw-button saw-button-secondary" title="Zrušit vyhledávání">
        <span class="dashicons dashicons-no"></span>
    </a>';
}
?>

<div class="saw-module-visits">
    <?php
    $table = new SAW_Component_Admin_Table('visits', array(
        'title' => 'Návštěvy',
        'create_url' => home_url('/admin/visits/create'),
        'edit_url' => home_url('/admin/visits/{id}/edit'),
        'detail_url' => home_url('/admin/visits/{id}/'),
        
        'module_config' => $this->config,
        
        'sidebar_mode' => $sidebar_mode ?? null,
        'detail_item' => $detail_item ?? null,
        'form_item' => $form_item ?? null,
        'detail_tab' => $detail_tab ?? 'overview',
        'related_data' => $related_data ?? null,
        'branches' => $branches ?? array(),
        
        'filters' => $filter_html,
        'search' => $search_html,
        'search_value' => $search,
        
        'columns' => array(            
            'company_name' => array(
                'label' => 'Firma',
                'type' => 'text',
                'class' => 'saw-table-cell-bold',
                'sortable' => true,
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