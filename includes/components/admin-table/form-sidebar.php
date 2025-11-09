<?php
/**
 * Form Sidebar Template
 *
 * Renders create/edit form in sidebar.
 * FIXED: Correct data-mode (edit/create) and data-current-id for proper navigation
 *
 * @package     SAW_Visitors
 * @subpackage  Components/AdminTable
 * @version     3.2.0 - FIXED: data-mode shows edit/create, added data-current-id
 * @since       4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$module_slug = str_replace('_', '-', $entity);
$form_template = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$module_slug}/form-template.php";

// Close URL is now handled by JavaScript
$close_url = '#';

// CRITICAL FIX: Use actual mode (edit/create), not generic "form"
$actual_mode = $is_edit ? 'edit' : 'create';
$current_id = $is_edit && !empty($item['id']) ? intval($item['id']) : 0;
?>

<div class="saw-sidebar" 
     data-mode="<?php echo esc_attr($actual_mode); ?>" 
     data-entity="<?php echo esc_attr($entity); ?>"
     data-current-id="<?php echo esc_attr($current_id); ?>">
    <div class="saw-sidebar-header">
        <div class="saw-sidebar-title">
            <span><?php echo esc_html($config['icon'] ?? '📝'); ?></span>
            <h2><?php echo $is_edit ? 'Upravit ' : 'Nový '; echo esc_html($config['singular'] ?? 'záznam'); ?></h2>
        </div>
        <a href="<?php echo esc_url($close_url); ?>" class="saw-sidebar-close" title="Zavřít">&times;</a>
    </div>
    <div class="saw-sidebar-content">
        <?php 
        if (file_exists($form_template)) {
            $GLOBALS['saw_sidebar_form'] = true;
            $account_types = $config['account_types'] ?? array();
            require $form_template;
            unset($GLOBALS['saw_sidebar_form']);
        } else {
            echo '<p>Form template not found: ' . esc_html($form_template) . '</p>';
        }
        ?>
    </div>
</div>