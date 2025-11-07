<?php
/**
 * Detail Sidebar Template
 *
 * Displays item detail in sidebar with navigation controls.
 *
 * @package     SAW_Visitors
 * @subpackage  Components/AdminTable
 * @version     1.0.0
 * @since       4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$module_slug = str_replace('_', '-', $entity);
$detail_template = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$module_slug}/detail-modal-template.php";
?>

<div class="saw-sidebar" data-mode="detail" data-entity="<?php echo esc_attr($entity); ?>" data-current-id="<?php echo esc_attr($item['id']); ?>">
    <div class="saw-sidebar-header">
        <div class="saw-sidebar-title">
            <span><?php echo esc_html($config['icon'] ?? '📋'); ?></span>
            <h2><?php echo esc_html($config['singular'] ?? 'Detail'); ?> #<?php echo $item['id']; ?></h2>
        </div>
        <a href="<?php echo esc_url(home_url('/admin/' . $config['route'] . '/')); ?>" 
           class="saw-sidebar-close" title="Zavřít (ESC)">×</a>
    </div>
    
    <div class="saw-sidebar-content">
        <?php
        if (file_exists($detail_template)) {
            require $detail_template;
        } else {
            echo '<div class="saw-sidebar-section"><h3>Detail</h3><dl>';
            foreach ($item as $key => $value) {
                if ($key === 'id') continue;
                echo '<dt>' . esc_html(ucfirst(str_replace('_', ' ', $key))) . '</dt>';
                echo '<dd>' . esc_html($value) . '</dd>';
            }
            echo '</dl></div>';
        }
        ?>
    </div>
    
    <div class="saw-sidebar-footer">
        <a href="<?php echo esc_url(home_url('/admin/' . $config['route'] . '/' . $item['id'] . '/edit')); ?>" 
           class="saw-button saw-button-primary">✏️ Upravit</a>
        <button type="button" class="saw-button saw-button-secondary" 
                onclick="saw_navigate_sidebar('prev')">↑ Předchozí</button>
        <button type="button" class="saw-button saw-button-secondary" 
                onclick="saw_navigate_sidebar('next')">↓ Další</button>
    </div>
</div>