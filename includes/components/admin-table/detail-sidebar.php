<?php
/**
 * Detail Sidebar Template
 *
 * Renders detail view in sidebar with navigation controls and floating action buttons.
 *
 * @package     SAW_Visitors
 * @subpackage  Components/AdminTable
 * @version     3.3.0 - FIXED: Edit URL generation with home_url() like delete button
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