<?php
/**
 * Detail Sidebar Template
 *
 * Renders detail view in sidebar with navigation controls and floating action buttons.
 *
 * @package     SAW_Visitors
 * @subpackage  Components/AdminTable
 * @version     4.0.0 - RELATED DATA SUPPORT ADDED
 * @since       4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$module_slug = str_replace('_', '-', $entity);
$detail_template = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$module_slug}/detail-modal-template.php";

// Close URL is now handled by JavaScript
$close_url = '#';

// FIXED: Generate absolute URLs using home_url() like delete button
$edit_url = home_url('/admin/' . str_replace('admin/', '', $config['route'] ?? '') . '/' . intval($item['id']) . '/edit');
$delete_url = home_url('/admin/' . str_replace('admin/', '', $config['route'] ?? '') . '/delete/' . intval($item['id']));

// Check permissions
$can_edit = function_exists('saw_can') ? saw_can('edit', $entity) : true;
$can_delete = function_exists('saw_can') ? saw_can('delete', $entity) : true;
?>

<div class="saw-sidebar saw-sidebar-detail" data-mode="detail" data-entity="<?php echo esc_attr($entity); ?>" data-current-id="<?php echo esc_attr($item['id']); ?>">
    <div class="saw-sidebar-header">
        <div class="saw-sidebar-title">
            <span class="saw-sidebar-icon"><?php echo esc_html($config['icon'] ?? '📋'); ?></span>
            <h2 class="saw-sidebar-heading"><?php echo esc_html($config['singular'] ?? 'Detail'); ?> #<?php echo intval($item['id']); ?></h2>
        </div>
        <div class="saw-sidebar-nav-controls">
            <button type="button" class="saw-sidebar-nav-btn saw-sidebar-prev" title="Předchozí">&lt;</button>
            <button type="button" class="saw-sidebar-nav-btn saw-sidebar-next" title="Další">&gt;</button>
        </div>
        <a href="<?php echo esc_url($close_url); ?>" class="saw-sidebar-close" title="Zavřít">&times;</a>
    </div>
    
    <div class="saw-sidebar-content">
        <?php 
        if (file_exists($detail_template)) {
            require $detail_template;
        } else {
            echo '<p>Detail template not found: ' . esc_html($detail_template) . '</p>';
        }
        ?>
        
        <?php
        // Related Data Sections
        if (!empty($related_data) && is_array($related_data)):
        ?>
        <div class="saw-related-sections">
            <h3 class="saw-related-sections-title">
                <?php echo esc_html__('Související záznamy', 'saw-visitors'); ?>
            </h3>
            
            <?php foreach ($related_data as $key => $relation): ?>
            <details class="saw-related-section" open>
                <summary class="saw-related-section-summary">
                    <span class="saw-related-section-icon"><?php echo esc_html($relation['icon']); ?></span>
                    <span class="saw-related-section-label"><?php echo esc_html($relation['label']); ?></span>
                    <span class="saw-badge saw-badge-info"><?php echo intval($relation['count']); ?></span>
                </summary>
                
                <div class="saw-related-items">
                    <?php if (!empty($relation['items'])): ?>
                        <?php foreach ($relation['items'] as $related_item): ?>
                        <a href="#" 
                           class="saw-related-item-link"
                           data-entity="<?php echo esc_attr($relation['entity']); ?>"
                           data-id="<?php echo intval($related_item['id']); ?>"
                           title="<?php echo esc_attr__('Zobrazit detail', 'saw-visitors'); ?>">
                            <span class="saw-related-item-text">
                                <?php echo esc_html($related_item['display']); ?>
                            </span>
                            <span class="saw-related-item-arrow">→</span>
                        </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="saw-related-empty">
                            <?php echo esc_html__('Žádné záznamy', 'saw-visitors'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </details>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
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