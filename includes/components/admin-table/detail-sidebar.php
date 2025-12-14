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

<div class="saw-sidebar saw-sidebar-detail" data-mode="detail" data-entity="<?php echo esc_attr($entity); ?>" data-current-id="<?php echo esc_attr($item['id']); ?>">
    <div class="saw-sidebar-header">
        <div class="saw-sidebar-title">
            <span class="saw-sidebar-icon"><?php echo esc_html($config['icon'] ?? '📋'); ?></span>
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
            <h2 class="saw-sidebar-heading"><?php echo $header_title; ?></h2>
        </div>
        <div class="saw-sidebar-nav-controls">
            <button type="button" class="saw-sidebar-nav-btn saw-sidebar-prev" title="Předchozí">&lt;</button>
            <button type="button" class="saw-sidebar-nav-btn saw-sidebar-next" title="Další">&gt;</button>
        </div>
        <a href="<?php echo esc_url($close_url); ?>" class="saw-sidebar-close" title="Zavřít">&times;</a>
    </div>
    
    <div class="saw-sidebar-content">
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
            $header_meta = '<span class="saw-badge-transparent">ID: ' . intval($item['id']) . '</span>';
        } elseif ($header_meta === '') {
            $header_meta = ''; // Explicitly empty, don't show ID
        }
        ?>
        
        <!-- Universal Detail Header - Rendered by admin-table component -->
        <!-- Goes from sidebar header to edges, no margin -->
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
        
        <!-- Module-specific content wrapper (has padding) -->
        <div class="saw-detail-content-wrapper">
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
        <div class="saw-related-sections">
            <h3 class="saw-related-sections-title">
                <?php echo esc_html__('Související záznamy', 'saw-visitors'); ?>
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
                            printf(
                                _n('%d záznam', '%d záznamy', $relation['count'], 'saw-visitors'),
                                $relation['count']
                            );
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
                           title="<?php echo esc_attr__('Zobrazit detail', 'saw-visitors'); ?>">
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
        require SAW_VISITORS_PLUGIN_DIR . 'includes/components/detail-audit-history.php';
        ?>
    </div>
    
    <?php if ($can_edit || $can_delete): ?>
    <div class="saw-sidebar-floating-actions">
        <?php if ($can_edit): ?>
        <a href="<?php echo esc_url($edit_url); ?>" 
           class="saw-floating-action-btn edit" 
           title="Upravit">
            <span class="dashicons dashicons-edit"></span>
        </a>
        <?php endif; ?>
        
        <?php if ($can_delete): ?>
        <button type="button" 
                class="saw-floating-action-btn delete saw-delete-btn" 
                data-id="<?php echo intval($item['id']); ?>"
                data-entity="<?php echo esc_attr($entity); ?>"
                data-name="<?php echo esc_attr($item['name'] ?? '#' . $item['id']); ?>"
                title="Smazat">
            <span class="dashicons dashicons-trash"></span>
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- 
    JS logika byla přesunuta do sidebar.js pro sjednocení.
    Moduly mohou přidat vlastní JS do svých detail-modal-template.php souborů.
-->