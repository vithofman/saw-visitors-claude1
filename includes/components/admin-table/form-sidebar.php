<?php
/**
 * Form Sidebar Template
 *
 * Renders create/edit form in sidebar.
 *
 * @package     SAW_Visitors
 * @subpackage  Components/AdminTable
 * @version     3.1.0 - FIXED: Vertical alignment of close button with span wrapper
 * @since       4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$module_slug = str_replace('_', '-', $entity);
$form_template = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$module_slug}/form-template.php";

// Close URL: for create mode go back to list, for edit mode go back to detail
if ($is_edit && !empty($item['id'])) {
    // Edit mode: navigate back to detail
    $close_url = home_url('/admin/' . $entity . '/' . intval($item['id']) . '/');
} else {
    // Create mode: navigate back to list
    $close_url = home_url('/admin/' . $entity . '/');
}
?>

<?php 
// Check if this is a nested inline create
$is_nested = isset($GLOBALS['saw_nested_inline_create']) && $GLOBALS['saw_nested_inline_create'];
?>
<div class="saw-sidebar" data-mode="<?php echo $is_edit ? 'edit' : 'create'; ?>" data-entity="<?php echo esc_attr($entity); ?>" data-module="<?php echo esc_attr($entity); ?>" data-is-nested="<?php echo $is_nested ? '1' : '0'; ?>" <?php if ($is_edit && !empty($item['id'])): ?>data-current-id="<?php echo intval($item['id']); ?>"<?php endif; ?>>
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