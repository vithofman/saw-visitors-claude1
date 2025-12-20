<?php
/**
 * Detail Sidebar Template - MODERN REDESIGN
 *
 * Clean, modern design with consistent SVG icons and blue theme
 * Fixed duplicate "Související záznamy" for branches module
 *
 * @package     SAW_Visitors
 * @version     6.0.0 - REDESIGN: Modern UI, SVG icons, no duplicates
 * @since       4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

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

$can_edit = function_exists('saw_can') ? saw_can('edit', $entity) : true;
$can_delete = function_exists('saw_can') ? saw_can('delete', $entity) : true;

// ============================================
// SVG ICONS - Lucide style, consistent design
// ============================================
function saw_sidebar_icon($name, $size = 20, $class = '') {
    $icons = [
        'clipboard-list' => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect><path d="M12 11h4"></path><path d="M12 16h4"></path><path d="M8 11h.01"></path><path d="M8 16h.01"></path>',
        'building-2' => '<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"></path><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"></path><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"></path><path d="M10 6h4"></path><path d="M10 10h4"></path><path d="M10 14h4"></path><path d="M10 18h4"></path>',
        'file-text' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline>',
        'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
        'settings' => '<circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>',
        'bar-chart' => '<line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line><line x1="6" y1="20" x2="6" y2="16"></line>',
        'lock' => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>',
        'chevron-left' => '<polyline points="15 18 9 12 15 6"></polyline>',
        'chevron-right' => '<polyline points="9 18 15 12 9 6"></polyline>',
        'x' => '<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>',
        'edit' => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>',
        'trash-2' => '<polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line>',
        'folder' => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>',
        'arrow-right' => '<line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline>',
        'mail' => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline>',
        'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>',
        'map-pin' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle>',
        'info' => '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line>',
        'clock' => '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>',
        'message-square' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>',
        'file-signature' => '<path d="M20 19.5v.5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8.5L18 5.5"></path><path d="M16 2v4a2 2 0 0 0 2 2h4"></path><path d="M12 18v-6"></path><path d="m9 15 3 3 3-3"></path>',
        'factory' => '<path d="M2 20a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8l-7 5V8l-7 5V4a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"></path><path d="M17 18h1"></path><path d="M12 18h1"></path><path d="M7 18h1"></path>',
    ];
    
    $path = $icons[$name] ?? $icons['clipboard-list'];
    $class_attr = $class ? ' class="' . esc_attr($class) . '"' : '';
    
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"' . $class_attr . '>' . $path . '</svg>';
}

// Map emoji to icon names
$icon_map = [
    '📋' => 'clipboard-list',
    '🏢' => 'building-2',
    '📄' => 'file-text',
    '👤' => 'user',
    '👥' => 'users',
    '⚙️' => 'settings',
    '📊' => 'bar-chart',
    '🔒' => 'lock',
    '🛡️' => 'shield',
    '📧' => 'mail',
    '📞' => 'phone',
    '📍' => 'map-pin',
    'ℹ️' => 'info',
    '🕐' => 'clock',
    '💬' => 'message-square',
    '📝' => 'file-signature',
    '🏭' => 'factory',
];

$icon_emoji = $config['icon'] ?? '📋';
$icon_name = $icon_map[$icon_emoji] ?? 'clipboard-list';
?>

<?php 
// Check if module uses Bento design system (for header hiding)
// Include both underscore and hyphen variants to handle entity vs slug naming
$bento_enabled_modules = ['branches', 'companies', 'training_languages', 'training-languages', 'translations', 'visits'];
$is_bento_module = in_array($entity, $bento_enabled_modules) && function_exists('saw_bento');
$bento_class = $is_bento_module ? ' bento-active' : '';
?>
<div class="sa-sidebar sa-sidebar--active sa-sidebar--modern<?php echo $bento_class; ?>" data-mode="detail" data-entity="<?php echo esc_attr($entity); ?>" data-current-id="<?php echo esc_attr($item['id']); ?>">
    
    <?php if (!$is_bento_module): ?>
    <!-- HEADER (only for non-Bento modules) -->
    <div class="sa-sidebar-header">
        <div class="sa-sidebar-title">
            <span class="sa-sidebar-icon">
                <?php echo saw_sidebar_icon($icon_name, 20); ?>
            </span>
            <div class="sa-sidebar-title-text">
                <div class="sa-sidebar-module-name"><?php echo esc_html($config['plural'] ?? $config['title'] ?? ucfirst($entity)); ?></div>
                <?php 
                $header_title = '';
                if (!empty($item['header_display_name'])) {
                    $header_title = esc_html($item['header_display_name']);
                } else {
                    $header_title = esc_html($config['singular'] ?? 'Detail') . ' #' . intval($item['id']);
                }
                ?>
                <h2 class="sa-sidebar-heading"><?php echo $header_title; ?></h2>
            </div>
        </div>
        <div class="sa-sidebar-nav">
            <button type="button" class="sa-sidebar-nav-btn sa-sidebar-prev" title="Předchozí">
                <?php echo saw_sidebar_icon('chevron-left', 16); ?>
            </button>
            <button type="button" class="sa-sidebar-nav-btn sa-sidebar-next" title="Další">
                <?php echo saw_sidebar_icon('chevron-right', 16); ?>
            </button>
            <a href="<?php echo esc_url($close_url); ?>" class="sa-sidebar-close" title="Zavřít">
                <?php echo saw_sidebar_icon('x', 18); ?>
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- CONTENT -->
    <div class="sa-sidebar-content">
        <?php 
        // Get display name
        global $saw_current_controller;
        $controller_instance = $saw_current_controller ?? null;
        
        if (!$controller_instance) {
            $controller_class = 'SAW_Module_' . str_replace(' ', '_', ucwords(str_replace('-', ' ', $entity))) . '_Controller';
            $controller_file = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$module_slug}/controller.php";
            
            if (file_exists($controller_file) && class_exists($controller_class)) {
                $controller_instance = new $controller_class();
            }
        }
        
        $display_name = '';
        if (!empty($item['header_display_name'])) {
            $display_name = $item['header_display_name'];
        } elseif ($controller_instance && method_exists($controller_instance, 'get_display_name')) {
            $display_name = $controller_instance->get_display_name($item);
        } else {
            $display_name = $item['name'] ?? $item['title'] ?? '';
            if (empty($display_name) && !empty($item['first_name'])) {
                $display_name = trim($item['first_name'] . ' ' . ($item['last_name'] ?? ''));
            }
            if (empty($display_name)) {
                $display_name = '#' . $item['id'];
            }
        }
        
        $header_meta = $item['header_meta'] ?? null;
        if ($header_meta === null && !empty($item['id'])) {
            $header_meta = '<span class="sa-badge sa-badge--neutral">ID: ' . intval($item['id']) . '</span>';
        } elseif ($header_meta === '') {
            $header_meta = '';
        }
        ?>
        
        <?php 
        // Check if module uses Bento design system
        $bento_modules = ['branches', 'companies', 'training_languages', 'training-languages', 'translations', 'visits']; // Modules with Bento design
        $use_bento = in_array($entity, $bento_modules) && function_exists('saw_bento');
        ?>
        
        <?php if (!$use_bento): ?>
        <!-- Detail Header (legacy) -->
        <div class="sa-detail-header">
            <div class="sa-detail-header-inner">
                <h3 class="sa-detail-header-title"><?php echo esc_html($display_name); ?></h3>
                <?php if (!empty($header_meta)): ?>
                <div class="sa-detail-header-meta">
                    <?php echo $header_meta; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="sa-detail-header-stripe"></div>
        </div>
        <?php endif; ?>
        
        <!-- Module-specific content -->
        <div class="sa-detail-content-wrapper <?php echo $use_bento ? 'bento-content-wrapper' : ''; ?>">
            <?php 
            if (file_exists($detail_template)) {
                require $detail_template;
            } else {
                echo '<p>Detail template not found: ' . esc_html($detail_template) . '</p>';
            }
            ?>
        </div>
        
        <?php 
        // ⭐ OPRAVA: Skip relations for modules that have their own implementation
        // branches has "Oddělení" section, companies has custom implementation
        $skip_relations_modules = ['companies', 'branches', 'departments', 'visitors', 'visits'];
        $skip_relations = in_array($entity, $skip_relations_modules);
        
        if ($skip_relations) {
            $related_data = null;
        }
        ?>
        
        <?php if (!empty($related_data) && is_array($related_data) && !$skip_relations): ?>
        <div class="sa-detail-related-sections">
            <h3 class="sa-detail-related-sections-title">
                <?php echo saw_sidebar_icon('folder', 16); ?>
                <span><?php echo esc_html__('Související záznamy', 'saw-visitors'); ?></span>
            </h3>
            
            <?php foreach ($related_data as $key => $relation): ?>
            <div class="sa-detail-related-section" data-section="<?php echo esc_attr($key); ?>">
                <div class="sa-detail-related-section-header" data-toggle-section>
                    <div class="sa-detail-related-section-icon-wrapper">
                        <?php 
                        $rel_icon = $icon_map[$relation['icon']] ?? 'folder';
                        echo saw_sidebar_icon($rel_icon, 18);
                        ?>
                    </div>
                    
                    <div class="sa-detail-related-section-info">
                        <h4 class="sa-detail-related-section-label">
                            <?php echo esc_html($relation['label']); ?>
                        </h4>
                        <div class="sa-detail-related-section-count">
                            <?php printf(_n('%d záznam', '%d záznamy', $relation['count'], 'saw-visitors'), $relation['count']); ?>
                        </div>
                    </div>
                    
                    <div class="sa-detail-related-section-badge">
                        <?php echo intval($relation['count']); ?>
                    </div>
                </div>
                
                <div class="sa-detail-related-items">
                    <?php if (!empty($relation['items'])): ?>
                        <?php foreach ($relation['items'] as $related_item): ?>
                        <?php 
                        $item_route = str_replace('{id}', $related_item['id'], $relation['route']);
                        $full_url = home_url('/' . ltrim($item_route, '/'));
                        ?>
                        <a href="<?php echo esc_url($full_url); ?>" class="sa-detail-related-item-link">
                            <span class="sa-detail-related-item-dot"></span>
                            <span class="sa-detail-related-item-text"><?php echo esc_html($related_item['display']); ?></span>
                            <?php echo saw_sidebar_icon('arrow-right', 14); ?>
                        </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="sa-detail-related-empty"><?php echo esc_html__('Žádné záznamy', 'saw-visitors'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php
        if (empty($GLOBALS['saw_audit_history_included'])) {
            $GLOBALS['saw_audit_history_included'] = true;
            require SAW_VISITORS_PLUGIN_DIR . 'includes/components/detail-audit-history.php';
        }
        ?>
    </div>
    
    <!-- FAB BUTTONS -->
    <?php if ($can_edit || $can_delete): ?>
    <div class="sa-sidebar-fab-container">
        <?php if ($can_edit): ?>
        <a href="<?php echo esc_url($edit_url); ?>" class="sa-sidebar-fab sa-sidebar-fab--edit" title="Upravit">
            <?php echo saw_sidebar_icon('edit', 22); ?>
        </a>
        <?php endif; ?>
        
        <?php if ($can_delete): ?>
        <button type="button" 
                class="sa-sidebar-fab sa-sidebar-fab--delete saw-delete-btn" 
                data-id="<?php echo intval($item['id']); ?>"
                data-entity="<?php echo esc_attr($entity); ?>"
                data-name="<?php echo esc_attr($item['name'] ?? '#' . $item['id']); ?>"
                title="Smazat">
            <?php echo saw_sidebar_icon('trash-2', 22); ?>
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
</div>