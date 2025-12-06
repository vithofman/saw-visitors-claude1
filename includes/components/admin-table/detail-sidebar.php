<?php
/**
 * Detail Sidebar Template - ULTRA MODERN + COLLAPSIBLE + TRANSLATIONS
 *
 * Card-based layout with smooth collapse/expand and multi-language support.
 * Uses hierarchical translation system: common → admin → admin/module
 *
 * @package     SAW_Visitors
 * @subpackage  Components/AdminTable
 * @version     5.4.0 - Uses common translations (global UI keys)
 * @since       4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// TRANSLATIONS SETUP
// ============================================
// Get user's language
$_sidebar_lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $_sidebar_lang = SAW_Component_Language_Switcher::get_user_language();
}

// Load translations - hierarchicky načte:
// 1. common (section=NULL) - globální UI texty
// 2. admin (section=NULL) - admin-wide texty  
// 3. admin/$entity - module-specific texty
$_module_section = $entity ?? 'visits';
$t = function_exists('saw_get_translations') 
    ? saw_get_translations($_sidebar_lang, 'admin', $_module_section) 
    : [];

// Translation helper with fallback
$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// Helper for Czech record count grammar (1 záznam, 2-4 záznamy, 5+ záznamů)
$record_label = function($count) use ($tr) {
    $count = intval($count);
    if ($count === 1) {
        return $tr('record_singular', 'záznam');
    } elseif ($count >= 2 && $count <= 4) {
        return $tr('record_few', 'záznamy');
    } else {
        return $tr('record_many', 'záznamů');
    }
};

// ============================================
// URL SETUP
// ============================================
$module_slug = str_replace('_', '-', $entity);
$detail_template = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$module_slug}/detail-modal-template.php";

// Close URL: navigate back to list
$route = isset($config['route']) && $config['route'] !== '' ? $config['route'] : $entity;
$route = str_replace('admin/', '', $route);
$route = trim($route, '/');

if (empty($route)) {
    $route = $entity;
}

$route = trim($route, '/');

if (!empty($route)) {
    $close_url = home_url('/admin/' . $route . '/');
    $edit_url = home_url('/admin/' . $route . '/' . intval($item['id']) . '/edit');
    $delete_url = home_url('/admin/' . $route . '/delete/' . intval($item['id']));
} else {
    $close_url = home_url('/admin/' . $entity . '/');
    $edit_url = home_url('/admin/' . $entity . '/' . intval($item['id']) . '/edit');
    $delete_url = home_url('/admin/' . $entity . '/delete/' . intval($item['id']));
}

// ============================================
// PERMISSIONS
// ============================================
$can_edit = function_exists('saw_can') ? saw_can('edit', $entity) : true;
$can_delete = function_exists('saw_can') ? saw_can('delete', $entity) : true;
?>

<div class="saw-sidebar saw-sidebar-detail" 
     data-mode="detail" 
     data-entity="<?php echo esc_attr($entity); ?>" 
     data-current-id="<?php echo esc_attr($item['id']); ?>">
    
    <!-- ============================================
         SIDEBAR HEADER
         ============================================ -->
    <div class="saw-sidebar-header">
        <div class="saw-sidebar-title">
            <span class="saw-sidebar-icon"><?php echo esc_html($config['icon'] ?? '📋'); ?></span>
            <h2 class="saw-sidebar-heading">
                <?php echo esc_html($config['singular'] ?? 'Detail'); ?> #<?php echo intval($item['id']); ?>
            </h2>
        </div>
        <div class="saw-sidebar-nav-controls">
            <button type="button" 
                    class="saw-sidebar-nav-btn saw-sidebar-prev" 
                    title="<?php echo esc_attr($tr('sidebar_previous', 'Předchozí')); ?>">&lt;</button>
            <button type="button" 
                    class="saw-sidebar-nav-btn saw-sidebar-next" 
                    title="<?php echo esc_attr($tr('sidebar_next', 'Další')); ?>">&gt;</button>
        </div>
        <a href="<?php echo esc_url($close_url); ?>" 
           class="saw-sidebar-close" 
           title="<?php echo esc_attr($tr('sidebar_close', 'Zavřít')); ?>">&times;</a>
    </div>
    
    <!-- ============================================
         SIDEBAR CONTENT
         ============================================ -->
    <div class="saw-sidebar-content">
        <?php 
        // Get display name - try to get controller instance from global context
        global $saw_current_controller;
        $controller_instance = $saw_current_controller ?? null;
        
        // If not in global, try to instantiate it
        if (!$controller_instance) {
            $controller_class = 'SAW_Module_' . str_replace(' ', '_', ucwords(str_replace('-', ' ', $entity))) . '_Controller';
            $controller_file = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$module_slug}/controller.php";
            
            if (file_exists($controller_file) && class_exists($controller_class)) {
                $controller_instance = new $controller_class();
            }
        }
        
        // Get display name using controller method or fallback
        $display_name = '';
        if ($controller_instance && method_exists($controller_instance, 'get_display_name')) {
            $display_name = $controller_instance->get_display_name($item);
        } else {
            // Fallback to common fields
            $display_name = $item['name'] ?? $item['title'] ?? '';
            if (empty($display_name) && !empty($item['first_name'])) {
                $display_name = trim($item['first_name'] . ' ' . ($item['last_name'] ?? ''));
            }
            if (empty($display_name)) {
                $display_name = '#' . $item['id'];
            }
        }
        
        // Get header meta (badges, additional info) - modules can override via $item['header_meta']
        $header_meta = $item['header_meta'] ?? '';
        if (empty(trim($header_meta)) && !empty($item['id'])) {
            $header_meta = '<span class="saw-badge-transparent">ID: ' . intval($item['id']) . '</span>';
        }
        ?>
        
        <!-- ============================================
             UNIVERSAL DETAIL HEADER
             ============================================ -->
        <div class="saw-detail-header-universal">
            <div class="saw-detail-header-inner">
                <h3 class="saw-detail-header-title"><?php echo esc_html($display_name); ?></h3>
                <?php if (!empty($header_meta)): ?>
                <div class="saw-detail-header-meta">
                    <?php echo $header_meta; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="saw-detail-header-stripe"></div>
        </div>
        
        <!-- ============================================
             MODULE-SPECIFIC CONTENT
             ============================================ -->
        <div class="saw-detail-content-wrapper">
            <?php 
            if (file_exists($detail_template)) {
                require $detail_template;
            } else {
                echo '<p>Detail template not found: ' . esc_html($detail_template) . '</p>';
            }
            ?>
        </div>
        
        <!-- ============================================
             RELATED SECTIONS
             ============================================ -->
        <?php if (!empty($related_data) && is_array($related_data)): ?>
        <div class="saw-related-sections">
            <h3 class="saw-related-sections-title">
                <?php echo esc_html($tr('related_records', 'Související záznamy')); ?>
            </h3>
            
            <?php foreach ($related_data as $key => $relation): ?>
            <div class="saw-related-section" data-section="<?php echo esc_attr($key); ?>">
                <!-- Collapsible Header -->
                <div class="saw-related-section-header" data-toggle-section>
                    <div class="saw-related-section-toggle"></div>
                    
                    <div class="saw-related-section-icon-wrapper">
                        <span class="saw-related-section-icon"><?php echo esc_html($relation['icon']); ?></span>
                    </div>
                    
                    <div class="saw-related-section-info">
                        <h4 class="saw-related-section-label">
                            <?php echo esc_html($relation['label']); ?>
                        </h4>
                        <div class="saw-related-section-count">
                            <?php 
                            $count = intval($relation['count']);
                            echo $count . ' ' . esc_html($record_label($count));
                            ?>
                        </div>
                    </div>
                    
                    <div class="saw-related-section-badge">
                        <?php echo intval($relation['count']); ?>
                    </div>
                </div>
                
                <!-- Collapsible Content -->
                <div class="saw-related-items">
                    <?php if (!empty($relation['items'])): ?>
                        <?php foreach ($relation['items'] as $related_item): ?>
                        <?php 
                        $item_route = str_replace('{id}', $related_item['id'], $relation['route']);
                        $full_url = home_url('/' . ltrim($item_route, '/'));
                        ?>
                        <a href="<?php echo esc_url($full_url); ?>" 
                           class="saw-related-item-link"
                           title="<?php echo esc_attr($tr('view_detail', 'Zobrazit detail')); ?>">
                            <div class="saw-related-item-content">
                                <span class="saw-related-item-dot"></span>
                                <span class="saw-related-item-text">
                                    <?php echo esc_html($related_item['display']); ?>
                                </span>
                            </div>
                            <span class="saw-related-item-arrow">→</span>
                        </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="saw-related-empty">
                            <?php echo esc_html($tr('no_records', 'Žádné záznamy')); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- ============================================
         FLOATING ACTION BUTTONS
         ============================================ -->
    <?php if ($can_edit || $can_delete): ?>
    <div class="saw-sidebar-floating-actions">
        <?php if ($can_edit): ?>
        <a href="<?php echo esc_url($edit_url); ?>" 
           class="saw-floating-action-btn edit" 
           title="<?php echo esc_attr($tr('btn_edit', 'Upravit')); ?>">
            <span class="dashicons dashicons-edit"></span>
        </a>
        <?php endif; ?>
        
        <?php if ($can_delete): ?>
        <button type="button" 
                class="saw-floating-action-btn delete saw-delete-btn" 
                data-id="<?php echo intval($item['id']); ?>"
                data-entity="<?php echo esc_attr($entity); ?>"
                data-name="<?php echo esc_attr($item['name'] ?? '#' . $item['id']); ?>"
                title="<?php echo esc_attr($tr('btn_delete', 'Smazat')); ?>">
            <span class="dashicons dashicons-trash"></span>
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>