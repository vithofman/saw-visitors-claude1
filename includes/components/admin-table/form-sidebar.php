<?php
/**
 * Form Sidebar Template
 *
 * Renders create/edit form in sidebar.
 *
 * @package     SAW_Visitors
 * @subpackage  Components/AdminTable
 * @version     4.1.0 - FIXED: Use config['route'] instead of entity for URLs
 * @since       4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// LOAD TRANSLATIONS FOR MODULE
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}

// Load translations for specific module (e.g., admin/companies, admin/visitors)
$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', $entity) 
    : [];

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// SETUP
// ============================================
$module_slug = str_replace('_', '-', $entity);
$form_template = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$module_slug}/form-template.php";

// ============================================
// URL SETUP - Use route from config, fallback to entity
// ============================================
$route = isset($config['route']) && $config['route'] !== '' ? $config['route'] : $entity;
$route = str_replace('admin/', '', $route);
$route = trim($route, '/');

if (empty($route)) {
    $route = $entity;
}

// Close URL: for create mode go back to list, for edit mode go back to detail
if ($is_edit && !empty($item['id'])) {
    $close_url = home_url('/admin/' . $route . '/' . intval($item['id']) . '/');
} else {
    $close_url = home_url('/admin/' . $route . '/');
}

// Get sidebar title from translations
if ($is_edit) {
    $sidebar_title = $tr('form_title_edit', 'Upravit ' . ($config['singular'] ?? 'záznam'));
} else {
    $sidebar_title = $tr('form_title_create', 'Nový ' . ($config['singular'] ?? 'záznam'));
}

$is_nested = isset($GLOBALS['saw_nested_inline_create']) && $GLOBALS['saw_nested_inline_create'];
?>

<div class="sa-sidebar sa-sidebar--active" data-mode="<?php echo $is_edit ? 'edit' : 'create'; ?>" data-entity="<?php echo esc_attr($entity); ?>" data-module="<?php echo esc_attr($entity); ?>" data-is-nested="<?php echo $is_nested ? '1' : '0'; ?>" <?php if ($is_edit && !empty($item['id'])): ?>data-current-id="<?php echo intval($item['id']); ?>"<?php endif; ?>>
    <div class="sa-sidebar-header">
        <div class="sa-sidebar-title">
            <span class="sa-sidebar-icon"><?php 
                if (class_exists('SAW_Icons')) {
                    $icon_emoji = $config['icon'] ?? '📝';
                    $icon_map = [
                        '📝' => 'file-text',
                        '📋' => 'clipboard-list',
                        '🏢' => 'building-2',
                        '👤' => 'user',
                        '📧' => 'mail',
                        '⚙️' => 'settings',
                    ];
                    $icon_name = $icon_map[$icon_emoji] ?? 'file-text';
                    echo SAW_Icons::get($icon_name, 'sa-icon--md');
                } else {
                    echo esc_html($config['icon'] ?? '📝');
                }
            ?></span>
            <h2 class="sa-sidebar-heading"><?php echo esc_html($sidebar_title); ?></h2>
        </div>
        <a href="<?php echo esc_url($close_url); ?>" class="sa-sidebar-close" title="<?php echo esc_attr($tr('btn_close', 'Zavřít')); ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </a>
    </div>
    <div class="sa-sidebar-content">
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