<?php
/**
 * Branches List Template - REFACTORED
 * * Uses SAW_Component_Admin_Table for consistency.
 * * UPDATED (v12.0.2) to match 'schema-branches.php' and be compatible
 * * with the 1-argument callback limit in class-saw-component-admin-table.php.
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches/Templates
 * @since       9.0.0 (Refactored)
 * @version     12.0.2 (Compatibility-Fix)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load required components
require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/search/class-saw-component-search.php';
require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/selectbox/class-saw-component-selectbox.php';
require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';
require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/modal/class-saw-component-modal.php';

// Prepare search component HTML
ob_start();
$search_component = new SAW_Component_Search('branches', array(
    'placeholder' => __('Hledat pobočku...', 'saw-visitors'),
    'search_value' => $search,
    'ajax_enabled' => false,
    'show_button' => true,
    'show_info_banner' => true,
    'info_banner_label' => __('Vyhledávání:', 'saw-visitors'),
    'clear_url' => home_url('/admin/branches/'),
));
$search_component->render();
$search_html = ob_get_clean();

$filters_html = '';
?>

<div class="saw-module-branches">
    <?php
    $table = new SAW_Component_Admin_Table('branches', array(
        'title' => __('Pobočky', 'saw-visitors'),
        'create_url' => home_url('/admin/branches/create'),
        'edit_url' => home_url('/admin/branches/{id}/edit'),
        'detail_url' => home_url('/admin/branches/{id}/'),
        
        'module_config' => $this->config,
        
        'sidebar_mode' => $sidebar_mode ?? null,
        'detail_item' => $detail_item ?? null,
        'form_item' => $form_item ?? null,
        'detail_tab' => $detail_tab ?? 'overview',
	    'related_data' => $related_data ?? null,        

        // *** CRITICAL FIX ***
        // Sloupce jsou definovány manuálně, aby odpovídaly struktuře customers-template.php
        // a používaly pouze callbacky s 1 argumentem, které podporuje admin-table.php.
        'columns' => array(
            'image_url' => array(
                'label' => __('Obrázek', 'saw-visitors'),
                'type' => 'custom', // Použije custom pro zobrazení placeholderu
                'width' => '60px',
                'align' => 'center',
                'callback' => function($value) { // Pouze 1 argument ($value)
                    if (!empty($value)) {
                        $upload_dir = wp_upload_dir();
                        $thumb_url = strpos($value, 'http') === 0 
                            ? $value 
                            : $upload_dir['baseurl'] . '/' . ltrim($value, '/');
                        
                        return sprintf(
                            '<img src="%s" alt="" class="saw-branch-thumbnail" style="margin-right: 0;">',
                            esc_url($thumb_url)
                        );
                    } else {
                        return '<span class="saw-branch-icon" style="margin-right: 0;">🏢</span>';
                    }
                }
            ),
            'name' => array(
                'label' => __('Název pobočky', 'saw-visitors'),
                'type' => 'text', // Žádný callback
                'sortable' => true,
                'class' => 'saw-table-cell-bold',
            ),
             'is_headquarters' => array(
                'label' => __('Sídlo', 'saw-visitors'),
                'type' => 'custom',
                'width' => '80px',
                'align' => 'center',
                'callback' => function($value) { // Pouze 1 argument ($value)
                    if (empty($value)) {
                        return '<span class="saw-text-muted">—</span>';
                    }
                    return sprintf(
                        '<span class="saw-badge saw-badge-sm saw-badge-primary">%s</span>',
                        __('Sídlo', 'saw-visitors')
                    );
                }
            ),
            'code' => array(
                'label' => __('Kód', 'saw-visitors'),
                'type' => 'custom',
                'width' => '100px',
                'align' => 'center',
                'callback' => function($value) { // Pouze 1 argument ($value)
                    if (empty($value)) return '<span class="saw-text-muted">—</span>';
                    return sprintf('<span class="saw-code-badge">%s</span>', esc_html($value));
                }
            ),
            'city' => array(
                'label' => __('Město', 'saw-visitors'),
                'type' => 'text', // Žádný callback
                'sortable' => true,
            ),
            'phone' => array(
                'label' => __('Telefon', 'saw-visitors'),
                'type' => 'custom',
                'callback' => function($value) { // Pouze 1 argument ($value)
                    if (empty($value)) return '<span class="saw-text-muted">—</span>';
                    return sprintf(
                        '<a href="tel:%s" class="saw-phone-link">%s</a>',
                        esc_attr(preg_replace('/[^\d+]/', '', $value)),
                        esc_html($value)
                    );
                }
            ),
            'sort_order' => array(
                'label' => __('Pořadí', 'saw-visitors'),
                'type' => 'custom',
                'sortable' => true,
                'width' => '80px',
                'align' => 'center',
                'callback' => function($value) { // Pouze 1 argument ($value)
                    return sprintf('<span class="saw-sort-order-badge">%d</span>', (int) $value);
                }
            ),
        ),
        
        'rows' => $items,
        'total_items' => $total,
        'current_page' => $page,
        'total_pages' => $total_pages,
        'orderby' => $orderby,
        'order' => $order,
        
        'search' => $search_html,
        'filters' => $filters_html,
        
        'enable_modal' => empty($sidebar_mode),
        'modal_id' => 'branch-detail',
        'modal_ajax_action' => 'saw_get_branches_detail',
    ));

    $table->render();
    ?>
</div>

<?php
if (empty($sidebar_mode)) {
    $modal = new SAW_Component_Modal('branch-detail', array(
        'title' => __('Detail pobočky', 'saw-visitors'),
        'size' => 'large',
    ));
    $modal->render();
}