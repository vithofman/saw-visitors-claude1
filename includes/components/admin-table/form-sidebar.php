<?php
if (!defined('ABSPATH')) exit;

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
        <?php if (file_exists($form_template)) { $GLOBALS['saw_sidebar_form'] = true; $account_types = $config['account_types'] ?? array(); require $form_template; unset($GLOBALS['saw_sidebar_form']); } ?>
    </div>
</div>
<style>
.saw-sidebar-header{display:flex!important;align-items:center!important;justify-content:space-between!important;height:60px!important;padding:0 16px!important;border-bottom:1px solid #e5e7eb!important;background:#fafbfc!important}
.saw-sidebar-title{display:flex!important;align-items:center!important;gap:12px!important;flex:1!important}
.saw-sidebar-close{display:flex!important;align-items:center!important;justify-content:center!important;width:36px!important;height:36px!important;padding:0!important;background:#dc2626!important;border:none!important;border-radius:6px!important;cursor:pointer!important;flex-shrink:0!important;font-size:24px!important;line-height:1!important;color:#fff!important;font-weight:400!important;text-decoration:none!important}
.saw-sidebar-close:hover{background:#b91c1c!important}
</style>