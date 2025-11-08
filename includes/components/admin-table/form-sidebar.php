<?php
/**
 * Form Sidebar Template
 *
 * Renders create/edit form in sidebar.
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
$form_template = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$module_slug}/form-template.php";

if (!empty($config['create_url'])) {
    $close_url = preg_replace('/\/(create|new)$/', '/', $config['create_url']);
} else {
    $route = str_replace('admin/', '', $config['route'] ?? '');
    $close_url = home_url('/admin/' . $route . '/');
}
?>

<div class="saw-sidebar" data-mode="form" data-entity="<?php echo esc_attr($entity); ?>">
    <div class="saw-sidebar-header">
        <div class="saw-sidebar-title">
            <span><?php echo esc_html($config['icon'] ?? '📝'); ?></span>
            <h2><?php echo $is_edit ? 'Upravit ' : 'Nový '; echo esc_html($config['singular'] ?? 'záznam'); ?></h2>
        </div>
        <a href="<?php echo esc_url($close_url); ?>" class="saw-sidebar-close" title="Zavřít">×</a>
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