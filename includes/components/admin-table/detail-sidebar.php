<?php
/**
 * Detail Sidebar Template
 *
 * Renders detail view in sidebar with navigation controls.
 *
 * @package     SAW_Visitors
 * @subpackage  Components/AdminTable
 * @version     2.0.0
 * @since       4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$module_slug = str_replace('_', '-', $entity);
$detail_template = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$module_slug}/detail-modal-template.php";

if (!empty($config['detail_url'])) {
    $close_url = preg_replace('/\{id\}.*$/', '', $config['detail_url']);
} else {
    $route = str_replace('admin/', '', $config['route'] ?? '');
    $close_url = home_url('/admin/' . $route . '/');
}

$edit_url = str_replace('{id}', intval($item['id']), $config['edit_url'] ?? '');
$delete_url = home_url('/admin/' . str_replace('admin/', '', $config['route'] ?? '') . '/delete/' . intval($item['id']));
?>

<div class="saw-sidebar saw-sidebar-detail" data-mode="detail" data-entity="<?php echo esc_attr($entity); ?>" data-current-id="<?php echo esc_attr($item['id']); ?>">
    <div class="saw-sidebar-header">
        <div class="saw-sidebar-title">
            <span class="saw-sidebar-icon"><?php echo esc_html($config['icon'] ?? '📋'); ?></span>
            <h2 class="saw-sidebar-heading"><?php echo esc_html($config['singular'] ?? 'Detail'); ?> #<?php echo intval($item['id']); ?></h2>
        </div>
        <div class="saw-sidebar-nav-controls">
            <button type="button" class="saw-sidebar-nav-btn saw-sidebar-prev" title="Předchozí">‹</button>
            <button type="button" class="saw-sidebar-nav-btn saw-sidebar-next" title="Další">›</button>
        </div>
        <a href="<?php echo esc_url($close_url); ?>" class="saw-sidebar-close" title="Zavřít">×</a>
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
</div>