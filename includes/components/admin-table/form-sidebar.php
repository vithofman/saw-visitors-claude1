<?php
/**
 * Form Sidebar Template
 *
 * Displays create/edit form in sidebar.
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
$form_template = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$module_slug}/form-template.php";
?>

<div class="saw-sidebar" data-mode="form" data-entity="<?php echo esc_attr($entity); ?>">
    <div class="saw-sidebar-header">
        <div class="saw-sidebar-title">
            <span><?php echo esc_html($config['icon'] ?? '📝'); ?></span>
            <h2><?php echo $is_edit ? 'Upravit ' : 'Nový '; echo esc_html($config['singular'] ?? 'záznam'); ?></h2>
        </div>
        <a href="<?php echo esc_url(home_url('/admin/' . $config['route'] . '/')); ?>" 
           class="saw-sidebar-close" title="Zavřít (ESC)">✕</a>
    </div>
    
    <div class="saw-sidebar-content">
        <?php
        if (file_exists($form_template)) {
            $GLOBALS['saw_sidebar_form'] = true;
            require $form_template;
            unset($GLOBALS['saw_sidebar_form']);
        } else {
            echo '<p>Form template not found: ' . esc_html($form_template) . '</p>';
        }
        ?>
    </div>
</div>