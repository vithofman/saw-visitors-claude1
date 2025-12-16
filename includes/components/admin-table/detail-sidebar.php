<?php
/**
 * Detail Sidebar Template - ULTRA MODERN + COLLAPSIBLE
 *
 * Card-based layout with smooth collapse/expand
 *
 * @package     SAW_Visitors
 * @version     5.2.0 - Fixed related links to navigate properly
 * @since       4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$module_slug = str_replace('_', '-', $entity);
$detail_template = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$module_slug}/detail-modal-template.php";

// Close URL: navigate back to list
// Get route from config, fallback to entity
$route = isset($config['route']) && $config['route'] !== '' ? $config['route'] : $entity;
// Ensure route doesn't have 'admin/' prefix or leading/trailing slashes
$route = str_replace('admin/', '', $route);
$route = trim($route, '/');
// Fallback to entity if route is empty
if (empty($route)) {
    $route = $entity;
}
// Ensure route is clean and not empty - final check
$route = trim($route, '/');
// Build close URL - ensure we have a valid route to prevent admin//
if (!empty($route)) {
    $close_url = home_url('/admin/' . $route . '/');
    $edit_url = home_url('/admin/' . $route . '/' . intval($item['id']) . '/edit');
    $delete_url = home_url('/admin/' . $route . '/delete/' . intval($item['id']));
} else {
    // Last resort fallback - use entity directly
    $close_url = home_url('/admin/' . $entity . '/');
    $edit_url = home_url('/admin/' . $entity . '/' . intval($item['id']) . '/edit');
    $delete_url = home_url('/admin/' . $entity . '/delete/' . intval($item['id']));
}

$can_edit = function_exists('saw_can') ? saw_can('edit', $entity) : true;
$can_delete = function_exists('saw_can') ? saw_can('delete', $entity) : true;
?>

<div class="sa-sidebar sa-sidebar--active" data-mode="detail" data-entity="<?php echo esc_attr($entity); ?>" data-current-id="<?php echo esc_attr($item['id']); ?>">
    <div class="sa-sidebar-header">
        <div class="sa-sidebar-title">
            <span class="sa-sidebar-icon"><?php 
                if (class_exists('SAW_Icons')) {
                    $icon_emoji = $config['icon'] ?? '📋';
                    $icon_map = [
                        '📋' => 'clipboard-list',
                        '🏢' => 'building-2',
                        '📝' => 'file-text',
                        '👤' => 'user',
                        '📧' => 'mail',
                        '⚙️' => 'settings',
                        '📊' => 'bar-chart-3',
                        '🔒' => 'lock',
                    ];
                    $icon_name = $icon_map[$icon_emoji] ?? 'clipboard-list';
                    echo SAW_Icons::get($icon_name, 'sa-icon--md');
                } else {
                    echo esc_html($config['icon'] ?? '📋');
                }
            ?></span>
            <div class="sa-sidebar-title-text">
                <div class="sa-sidebar-module-name"><?php echo esc_html($config['plural'] ?? $config['title'] ?? ucfirst($entity)); ?></div>
                <?php 
                // For modules with header_display_name (like OOPP), show just the name
                // Otherwise show "Module #ID"
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
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
            <button type="button" class="sa-sidebar-nav-btn sa-sidebar-next" title="Další">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
            <a href="<?php echo esc_url($close_url); ?>" class="sa-sidebar-close" title="Zavřít">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </a>
        </div>
    </div>
    
    <div class="sa-sidebar-content">
        <?php 
        // Get display name - try to get controller instance from global context
        // Controller is passed via ajax_load_sidebar() in Base Controller
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
        // Check if module provided header_display_name (e.g., OOPP with translations)
        if (!empty($item['header_display_name'])) {
            $display_name = $item['header_display_name'];
        } elseif ($controller_instance && method_exists($controller_instance, 'get_display_name')) {
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
        // For OOPP, we don't want to show ID badge, so if header_meta is explicitly set to empty string, respect it
        $header_meta = $item['header_meta'] ?? null;
        // Only show ID fallback if header_meta is null (not set) or empty and not explicitly set to empty string
        if ($header_meta === null && !empty($item['id'])) {
            $header_meta = '<span class="sa-badge sa-badge--neutral">ID: ' . intval($item['id']) . '</span>';
        } elseif ($header_meta === '') {
            $header_meta = ''; // Explicitly empty, don't show ID
        }
        ?>
        
        <!-- Universal Detail Header - Rendered by admin-table component -->
        <!-- Goes from sidebar header to edges, no margin -->
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
        
        <!-- Module-specific content wrapper (has padding) -->
        <div class="sa-detail-content-wrapper">
            <?php 
            if (file_exists($detail_template)) {
                require $detail_template;
            } else {
                echo '<p>Detail template not found: ' . esc_html($detail_template) . '</p>';
            }
            ?>
        </div>
        
        <?php 
        // Skip relations section for companies - they have custom implementation in detail template
        // Also skip if related_data is empty or invalid
        $skip_relations = ($entity === 'companies');
        if ($skip_relations) {
            $related_data = null; // Ensure it's not used
        }
        ?>
        <?php if (!empty($related_data) && is_array($related_data) && !$skip_relations): ?>
        <div class="sa-detail-related-sections">
            <h3 class="sa-detail-related-sections-title">
                <?php echo esc_html__('Související záznamy', 'saw-visitors'); ?>
            </h3>
            
            <?php foreach ($related_data as $key => $relation): ?>
            <div class="sa-detail-related-section" data-section="<?php echo esc_attr($key); ?>">
                <!-- Collapsible Header -->
                <div class="sa-detail-related-section-header" data-toggle-section>
                    <div class="sa-detail-related-section-toggle"></div>
                    
                    <div class="sa-detail-related-section-icon-wrapper">
                        <span class="sa-detail-related-section-icon"><?php echo esc_html($relation['icon']); ?></span>
                    </div>
                    
                    <div class="sa-detail-related-section-info">
                        <h4 class="sa-detail-related-section-label">
                            <?php echo esc_html($relation['label']); ?>
                        </h4>
                        <div class="sa-detail-related-section-count">
                            <?php 
                            printf(
                                _n('%d záznam', '%d záznamy', $relation['count'], 'saw-visitors'),
                                $relation['count']
                            );
                            ?>
                        </div>
                    </div>
                    
                    <div class="sa-detail-related-section-badge">
                        <?php echo intval($relation['count']); ?>
                    </div>
                </div>
                
                <!-- Collapsible Content -->
                <div class="sa-detail-related-items">
                    <?php if (!empty($relation['items'])): ?>
                        <?php foreach ($relation['items'] as $related_item): ?>
                        <?php 
                        $item_route = str_replace('{id}', $related_item['id'], $relation['route']);
                        $full_url = home_url('/' . ltrim($item_route, '/'));
                        ?>
                        <a href="<?php echo esc_url($full_url); ?>" 
                           class="sa-detail-related-item-link"
                           title="<?php echo esc_attr__('Zobrazit detail', 'saw-visitors'); ?>">
                            <div class="sa-detail-related-item-content">
                                <span class="sa-detail-related-item-dot"></span>
                                <span class="sa-detail-related-item-text">
                                    <?php echo esc_html($related_item['display']); ?>
                                </span>
                            </div>
                            <span class="sa-detail-related-item-arrow">→</span>
                        </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="sa-detail-related-empty">
                            <?php echo esc_html__('Žádné záznamy', 'saw-visitors'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php
        // Include audit history component (after relations section)
        // ✅ OPRAVA: Zkontroluj, jestli se už nezobrazuje jinde v template
        if (empty($GLOBALS['saw_audit_history_included'])) {
            $GLOBALS['saw_audit_history_included'] = true;
            require SAW_VISITORS_PLUGIN_DIR . 'includes/components/detail-audit-history.php';
        }
        ?>
    </div>
    
    <?php if ($can_edit || $can_delete): ?>
    <div class="sa-sidebar-footer">
        <?php if ($can_edit): ?>
        <a href="<?php echo esc_url($edit_url); ?>" 
           class="sa-btn sa-btn--icon sa-btn--primary" 
           title="Upravit">
            <?php if (class_exists('SAW_Icons')): ?>
                <?php echo SAW_Icons::get('pencil'); ?>
            <?php else: ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
            <?php endif; ?>
        </a>
        <?php endif; ?>
        
        <?php if ($can_delete): ?>
        <button type="button" 
                class="sa-btn sa-btn--icon sa-btn--danger sa-delete-btn" 
                data-id="<?php echo intval($item['id']); ?>"
                data-entity="<?php echo esc_attr($entity); ?>"
                data-name="<?php echo esc_attr($item['name'] ?? '#' . $item['id']); ?>"
                title="Smazat">
            <?php if (class_exists('SAW_Icons')): ?>
                <?php echo SAW_Icons::get('trash-2'); ?>
            <?php else: ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
            <?php endif; ?>
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- 
    JS logika byla přesunuta do sidebar.js pro sjednocení.
    Moduly mohou přidat vlastní JS do svých detail-modal-template.php souborů.
-->