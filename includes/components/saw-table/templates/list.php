<?php
/**
 * SAW Table - List Template
 * 
 * Main list page template using sawt- CSS prefix.
 * 
 * Variables expected:
 * - $config: Module configuration
 * - $items: Data items array
 * - $entity: Entity name
 * - $total: Total count
 * - $current_tab: Current tab (if tabs enabled)
 * - $tab_counts: Tab counts array (if tabs enabled)
 * - $detail_item: Item for detail sidebar (optional)
 * - $sidebar_mode: 'detail', 'form', or null
 * - $related_data: Related data for detail sidebar
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/SAWTable/Templates
 * @version     2.0.0 - Updated to sawt- prefix
 * @since       3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// SETUP
// ============================================

$config = $config ?? [];
$items = $items ?? [];
$entity = $entity ?? ($config['entity'] ?? 'items');
$total = $total ?? count($items);
$current_tab = $current_tab ?? null;
$tab_counts = $tab_counts ?? [];
$detail_item = $detail_item ?? null;
$form_item = $form_item ?? null;
$sidebar_mode = $sidebar_mode ?? null;
$related_data = $related_data ?? [];

// Extract config
$title = $config['plural'] ?? ucfirst($entity);
$singular = $config['singular'] ?? $entity;
$route = $config['route'] ?? $entity;
$icon = $config['icon'] ?? '📋';

// URLs
$base_url = home_url('/admin/' . $route);
$create_url = $base_url . '/create';
$detail_url_pattern = $base_url . '/{id}/';
$edit_url_pattern = $base_url . '/{id}/edit';

// Current params
$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : ($config['table']['default_order'] ?? 'id');
$order = isset($_GET['order']) ? strtoupper(sanitize_key($_GET['order'])) : ($config['table']['default_order_dir'] ?? 'DESC');

// Tabs
$tabs_enabled = !empty($config['tabs']['enabled']);
$tabs = $config['tabs']['tabs'] ?? [];
$default_tab = $config['tabs']['default_tab'] ?? 'all';

// Infinite scroll
$infinite_scroll = $config['infinite_scroll'] ?? [];

// Columns & Actions
$columns = $config['table']['columns'] ?? $config['columns'] ?? [];
$actions = $config['actions'] ?? ['view', 'edit', 'delete'];

// Permissions
$can_create = true;
if (class_exists('SAW_Table_Permissions')) {
    $can_create = SAW_Table_Permissions::canCreate($entity);
}

// Sidebar state
$has_sidebar = ($sidebar_mode === 'detail' && $detail_item) || $sidebar_mode === 'form';

// Translation helper
$tr = $tr ?? function($key, $fallback = null) use ($config) {
    if (function_exists('saw_get_translations')) {
        static $translations = null;
        if ($translations === null) {
            $lang = class_exists('SAW_Component_Language_Switcher') 
                ? SAW_Component_Language_Switcher::get_user_language() 
                : 'cs';
            $translations = saw_get_translations($lang, 'admin', $config['entity'] ?? 'common');
        }
        return $translations[$key] ?? $fallback ?? $key;
    }
    return $fallback ?? $key;
};

?>
<div class="sawt-root sawt-page<?php echo $has_sidebar ? ' has-sidebar' : ''; ?>" 
     data-entity="<?php echo esc_attr($entity); ?>"
     data-base-url="<?php echo esc_url($base_url); ?>">
    
    <!-- ============================================
         PAGE HEADER
         ============================================ -->
    <header class="sawt-page-header">
        <div class="sawt-page-header-left">
            <span class="sawt-page-header-icon"><?php echo esc_html($icon); ?></span>
            <h1 class="sawt-page-header-title"><?php echo esc_html($title); ?></h1>
            <span class="sawt-page-header-count"><?php echo intval($total); ?></span>
        </div>
        
        <div class="sawt-page-header-right">
            <?php if ($can_create): ?>
                <a href="<?php echo esc_url($create_url); ?>" class="sawt-btn sawt-btn-primary" data-action="create">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php echo esc_html($tr('btn_add_new', 'Nový ' . $singular)); ?>
                </a>
            <?php endif; ?>
        </div>
    </header>
    
    <!-- ============================================
         TABS
         ============================================ -->
    <?php if ($tabs_enabled && !empty($tabs)): ?>
    <nav class="sawt-tabs">
        <?php foreach ($tabs as $tab_key => $tab): ?>
            <?php
            $tab_label = $tab['label'] ?? ucfirst($tab_key);
            $tab_icon = $tab['icon'] ?? '';
            $tab_count = $tab_counts[$tab_key] ?? 0;
            $tab_active = ($current_tab === $tab_key) || ($current_tab === null && $tab_key === $default_tab);
            $tab_url = add_query_arg('tab', $tab_key, $base_url);
            if ($search) $tab_url = add_query_arg('search', $search, $tab_url);
            ?>
            <a href="<?php echo esc_url($tab_url); ?>" 
               class="sawt-tab<?php echo $tab_active ? ' is-active' : ''; ?>"
               data-tab="<?php echo esc_attr($tab_key); ?>">
                <?php if ($tab_icon): ?>
                    <span class="sawt-tab-icon"><?php echo esc_html($tab_icon); ?></span>
                <?php endif; ?>
                <span class="sawt-tab-label"><?php echo esc_html($tab_label); ?></span>
                <span class="sawt-tab-count"><?php echo intval($tab_count); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>
    
    <!-- ============================================
         TOOLBAR (Search / Filters)
         ============================================ -->
    <?php if (!empty($config['search']['enabled']) || !empty($config['filters'])): ?>
    <div class="sawt-toolbar">
        <?php if (!empty($config['search']['enabled'])): ?>
        <div class="sawt-search">
            <form method="get" action="<?php echo esc_url($base_url); ?>">
                <?php if ($current_tab): ?>
                    <input type="hidden" name="tab" value="<?php echo esc_attr($current_tab); ?>">
                <?php endif; ?>
                <input type="text" 
                       name="search" 
                       value="<?php echo esc_attr($search); ?>" 
                       placeholder="<?php echo esc_attr($config['search']['placeholder'] ?? $tr('search_placeholder', 'Hledat...')); ?>"
                       class="sawt-search-input"
                       autocomplete="off">
                <span class="dashicons dashicons-search sawt-search-icon"></span>
                <?php if ($search): ?>
                    <a href="<?php echo esc_url(remove_query_arg('search')); ?>" class="sawt-search-clear">×</a>
                <?php endif; ?>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- ============================================
         MAIN CONTENT
         ============================================ -->
    <div class="sawt-page-content">
        <div class="sawt-table-area">
            <?php
            if (class_exists('SAW_Table_Renderer')) {
                $table_config = [
                    'entity' => $entity,
                    'columns' => $columns,
                    'actions' => $actions,
                    'detail_url' => $detail_url_pattern,
                    'edit_url' => $edit_url_pattern,
                    'default_order' => $orderby,
                    'default_order_dir' => $order,
                    'empty_message' => $config['empty_message'] ?? $tr('empty_message', 'Žádné záznamy'),
                    'infinite_scroll' => $infinite_scroll,
                ];
                
                echo SAW_Table_Renderer::render($table_config, $items, [
                    'orderby' => $orderby,
                    'order' => $order,
                    'base_url' => $base_url,
                ]);
            } else {
                echo '<div class="sawt-alert sawt-alert-danger">SAW_Table_Renderer not found</div>';
            }
            ?>
        </div>
    </div>
    
    <!-- ============================================
         SIDEBAR WRAPPER
         ============================================ -->
    <div class="sawt-sidebar-wrapper<?php echo $has_sidebar ? ' is-open' : ''; ?>" data-entity="<?php echo esc_attr($entity); ?>">
        <div class="sawt-sidebar-backdrop"></div>
        <aside class="sawt-sidebar" 
               data-mode="<?php echo esc_attr($sidebar_mode ?: ''); ?>"
               data-current-id="<?php echo esc_attr($detail_item['id'] ?? $form_item['id'] ?? ''); ?>">
            
            <?php if ($sidebar_mode === 'detail' && $detail_item): ?>
                <?php
                if (class_exists('SAW_Detail_Renderer')) {
                    echo SAW_Detail_Renderer::render($config, $detail_item, $related_data, $entity);
                }
                ?>
            <?php elseif ($sidebar_mode === 'form'): ?>
                <?php
                if (class_exists('SAW_Form_Renderer')) {
                    echo SAW_Form_Renderer::render($config, $form_item, $entity);
                }
                ?>
            <?php else: ?>
                <div class="sawt-sidebar-loading is-active">
                    <div class="sawt-spinner sawt-spinner-lg"></div>
                    <span class="sawt-sidebar-loading-text"><?php echo esc_html($tr('loading', 'Načítání...')); ?></span>
                </div>
            <?php endif; ?>
            
        </aside>
    </div>
    
</div>

<?php
// JavaScript config
$js_config = [
    'entity' => $entity,
    'baseUrl' => $base_url,
    'detailUrl' => $detail_url_pattern,
    'editUrl' => $edit_url_pattern,
    'createUrl' => $create_url,
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('saw_ajax_nonce'),
    'infiniteScroll' => $infinite_scroll,
    'columns' => $columns,
    'actions' => $actions,
    'i18n' => [
        'loading' => $tr('loading', 'Načítání...'),
        'error' => $tr('error', 'Došlo k chybě'),
        'confirmDelete' => $tr('confirm_delete', 'Opravdu chcete smazat tento záznam?'),
        'deleted' => $tr('deleted', 'Záznam byl smazán'),
        'saved' => $tr('saved', 'Záznam byl uložen'),
    ],
];
?>
<script>
    window.sawtConfig = window.sawtConfig || {};
    window.sawtConfig['<?php echo esc_js($entity); ?>'] = <?php echo json_encode($js_config); ?>;
</script>
