<?php
/**
 * Visitors List Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visitors
 * @version     1.0.0
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
$status_filter = $list_data['participation_status'] ?? '';
$orderby = $list_data['orderby'] ?? 'vis.id';
$order = $list_data['order'] ?? 'DESC';

$base_url = home_url('/admin/visitors/');

// Build filter HTML
$filter_html = '<div class="saw-filters-container" style="display: flex; gap: 12px; align-items: center;">';

$filter_html .= '<select name="participation_status" class="saw-filter-select" onchange="window.location.href=\'' . esc_url($base_url) . '?participation_status=\' + this.value' . ($search ? ' + \'&s=' . esc_js($search) . '\'' : '') . '">
    <option value=""' . ($status_filter === '' ? ' selected' : '') . '>Všechny stavy účasti</option>
    <option value="planned"' . ($status_filter === 'planned' ? ' selected' : '') . '>Plánovaný</option>
    <option value="confirmed"' . ($status_filter === 'confirmed' ? ' selected' : '') . '>Potvrzený</option>
    <option value="no_show"' . ($status_filter === 'no_show' ? ' selected' : '') . '>Nedostavil se</option>
</select>';

if (!empty($status_filter)) {
    $reset_filter_url = $base_url;
    if (!empty($search)) {
        $reset_filter_url .= '?s=' . urlencode($search);
    }
    
    $filter_html .= '<a href="' . esc_url($reset_filter_url) . '" 
        class="saw-button saw-button-secondary" style="padding: 6px 12px;">
        <span class="dashicons dashicons-dismiss"></span>
        Zrušit filtr
    </a>';
}

$filter_html .= '</div>';

// Build search HTML
$search_html = '<form method="GET" action="' . esc_url($base_url) . '" class="saw-search-form" style="display: inline-flex; gap: 8px; align-items: center;">';
    
if (!empty($status_filter)) {
    $search_html .= '<input type="hidden" name="participation_status" value="' . esc_attr($status_filter) . '">';
}

$search_html .= '<input type="search" 
           name="s" 
           value="' . esc_attr($search) . '" 
           placeholder="Hledat návštěvníka..." 
           class="saw-search-input"
           style="min-width: 250px;">
    <button type="submit" class="saw-button saw-button-primary">
        <span class="dashicons dashicons-search"></span>
    </button>
</form>';

if (!empty($search)) {
    $reset_search_url = $base_url;
    if (!empty($status_filter)) {
        $reset_search_url .= '?participation_status=' . urlencode($status_filter);
    }
    
    $search_html .= '<a href="' . esc_url($reset_search_url) . '" class="saw-button saw-button-secondary" title="Zrušit vyhledávání">
        <span class="dashicons dashicons-no"></span>
    </a>';
}
?>

<div class="saw-module-visitors">
    <?php
    $table = new SAW_Component_Admin_Table('visitors', array(
        'title' => 'Návštěvníci',
        'create_url' => home_url('/admin/visitors/create'),
        'edit_url' => home_url('/admin/visitors/{id}/edit'),
        'detail_url' => home_url('/admin/visitors/{id}/'),
        
        'module_config' => $this->config,
        
        'sidebar_mode' => $sidebar_mode ?? null,
        'detail_item' => $detail_item ?? null,
        'form_item' => $form_item ?? null,
        'detail_tab' => $detail_tab ?? 'overview',
        'related_data' => $related_data ?? null,
        'visits' => $visits ?? array(),
        
        'filters' => $filter_html,
        'search' => $search_html,
        'search_value' => $search,
        
        'columns' => array(
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
            'participation_status' => array(
                'label' => 'Stav účasti',
                'type' => 'badge',
                'sortable' => true,
                'map' => array(
                    'planned' => 'warning',
                    'confirmed' => 'success',
                    'no_show' => 'danger',
                ),
                'labels' => array(
                    'planned' => 'Plánovaný',
                    'confirmed' => 'Potvrzený',
                    'no_show' => 'Nedostavil se',
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
        'empty_message' => 'Žádní návštěvníci nenalezeni',
        'add_new' => 'Nový návštěvník',
        
        'ajax_enabled' => true,
        'ajax_nonce' => $ajax_nonce,
    ));
    
    $table->render();
    ?>
</div>
