<?php
if (!defined('ABSPATH')) exit;

$module_slug = str_replace('_', '-', $entity);
$detail_template = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$module_slug}/detail-modal-template.php";

// Build close URL from list template URLs if available
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
            <button type="button" class="saw-sidebar-nav-btn" onclick="saw_navigate_sidebar('prev')" title="Předchozí">‹</button>
            <button type="button" class="saw-sidebar-nav-btn" onclick="saw_navigate_sidebar('next')" title="Další">›</button>
        </div>
        <a href="<?php echo esc_url($close_url); ?>" class="saw-sidebar-close" title="Zavřít">×</a>
    </div>
    <div class="saw-sidebar-content">
        <?php if (file_exists($detail_template)) { require $detail_template; } ?>
    </div>
</div>
<style>
.saw-sidebar-header{display:flex!important;align-items:center!important;justify-content:space-between!important;padding:0 16px!important;border-bottom:1px solid #e5e7eb!important;background:#fafbfc!important;height:60px!important;gap:16px!important}
.saw-sidebar-title{display:flex!important;align-items:center!important;gap:12px!important;flex:1!important}
.saw-sidebar-icon{font-size:20px!important;line-height:1!important}
.saw-sidebar-heading{margin:0!important;font-size:16px!important;font-weight:600!important;color:#111827!important}
.saw-sidebar-nav-controls{display:flex!important;align-items:center!important;gap:4px!important}
.saw-sidebar-nav-btn{display:flex!important;align-items:center!important;justify-content:center!important;width:36px!important;height:36px!important;padding:0!important;background:transparent!important;border:1px solid #e5e7eb!important;border-radius:6px!important;cursor:pointer!important;font-size:24px!important;line-height:1!important;color:#6b7280!important;font-weight:300!important}
.saw-sidebar-nav-btn:hover{background:#f3f4f6!important;border-color:#d1d5db!important;color:#111827!important}
.saw-sidebar-close{display:flex!important;align-items:center!important;justify-content:center!important;width:36px!important;height:36px!important;padding:0!important;background:#dc2626!important;border:none!important;border-radius:6px!important;cursor:pointer!important;flex-shrink:0!important;font-size:24px!important;line-height:1!important;color:#fff!important;font-weight:400!important;text-decoration:none!important}
.saw-sidebar-close:hover{background:#b91c1c!important}
</style>