<?php
/**
 * Branches List Template
 *
 * REFACTORED v13.1.0 - PRODUCTION READY
 * ✅ Správné načtení context proměnných
 * ✅ Sidebar support
 * ✅ Modal fallback
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches
 * @version     13.1.0
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
    'placeholder' => 'Hledat pobočku...',
    'search_value' => $search ?? '',
    'ajax_enabled' => false,
    'show_button' => true,
    'show_info_banner' => true,
    'info_banner_label' => 'Vyhledávání:',
    'clear_url' => home_url('/admin/branches/'),
));
$search_component->render();
$search_html = ob_get_clean();

$filters_html = '';
?>

<div class="saw-module-branches">
    <?php
    $table = new SAW_Component_Admin_Table('branches', array(
        'title' => 'Pobočky',
        'create_url' => home_url('/admin/branches/create'),
        'edit_url' => home_url('/admin/branches/{id}/edit'),
        'detail_url' => home_url('/admin/branches/{id}/'),
        
        // ✅ CRITICAL: Pass config
        'module_config' => $config ?? array(),
        
        // ✅ CRITICAL: Sidebar variables
        'sidebar_mode' => $sidebar_mode ?? null,
        'detail_item' => $detail_item ?? null,
        'form_item' => $form_item ?? null,
        'detail_tab' => $detail_tab ?? 'overview',
        'related_data' => $related_data ?? null,

        // Columns definition
        'columns' => array(
            'image_url' => array(
                'label' => 'Obrázek',
                'type' => 'custom',
                'width' => '60px',
                'align' => 'center',
                'callback' => function($value) {
                    if (!empty($value)) {
                        $upload_dir = wp_upload_dir();
                        $thumb_url = strpos($value, 'http') === 0 
                            ? $value 
                            : $upload_dir['baseurl'] . '/' . ltrim($value, '/');
                        
                        return sprintf(
                            '<img src="%s" alt="" class="saw-branch-thumbnail" style="margin-right: 0; max-width: 50px; height: auto; border-radius: 4px;">',
                            esc_url($thumb_url)
                        );
                    } else {
                        return '<span class="saw-branch-icon" style="margin-right: 0; font-size: 24px;">🏢</span>';
                    }
                }
            ),
            'name' => array(
                'label' => 'Název pobočky',
                'type' => 'text',
                'sortable' => true,
                'class' => 'saw-table-cell-bold',
            ),
            'is_headquarters' => array(
                'label' => 'Sídlo',
                'type' => 'custom',
                'width' => '80px',
                'align' => 'center',
                'callback' => function($value) {
                    if (empty($value)) {
                        return '<span class="saw-text-muted">—</span>';
                    }
                    return '<span class="saw-badge saw-badge-sm saw-badge-primary">Sídlo</span>';
                }
            ),
            'code' => array(
                'label' => 'Kód',
                'type' => 'custom',
                'width' => '100px',
                'align' => 'center',
                'callback' => function($value) {
                    if (empty($value)) return '<span class="saw-text-muted">—</span>';
                    return sprintf('<span class="saw-code-badge">%s</span>', esc_html($value));
                }
            ),
            'city' => array(
                'label' => 'Město',
                'type' => 'text',
                'sortable' => true,
            ),
            'phone' => array(
                'label' => 'Telefon',
                'type' => 'custom',
                'callback' => function($value) {
                    if (empty($value)) return '<span class="saw-text-muted">—</span>';
                    return sprintf(
                        '<a href="tel:%s" class="saw-phone-link">%s</a>',
                        esc_attr(preg_replace('/[^\d+]/', '', $value)),
                        esc_html($value)
                    );
                }
            ),
            'sort_order' => array(
                'label' => 'Pořadí',
                'type' => 'custom',
                'sortable' => true,
                'width' => '80px',
                'align' => 'center',
                'callback' => function($value) {
                    return sprintf('<span class="saw-sort-order-badge">%d</span>', (int) $value);
                }
            ),
        ),
        
        // List data
        'rows' => $items ?? array(),
        'total_items' => $total ?? 0,
        'current_page' => $page ?? 1,
        'total_pages' => $total_pages ?? 1,
        'orderby' => $orderby ?? 'id',
        'order' => $order ?? 'DESC',
        
        // Search & filters
        'search' => $search_html,
        'filters' => $filters_html,
        
        // Modal configuration (only if no sidebar)
        'enable_modal' => empty($sidebar_mode),
        'modal_id' => 'branch-detail',
        'modal_ajax_action' => 'saw_get_branches_detail',
    ));

    $table->render();
    ?>
</div>

<?php
// Render modal only if not in sidebar mode
if (empty($sidebar_mode)) {
    $modal = new SAW_Component_Modal('branch-detail', array(
        'title' => 'Detail pobočky',
        'size' => 'large',
    ));
    $modal->render();
}