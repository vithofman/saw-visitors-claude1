<?php
/**
 * Edit Sidebar Template
 * 
 * Wraps edit form in proper sidebar structure with header, scrollable content, and close button.
 * 
 * @package SAW_Visitors
 * @since 1.0.0
 * @version 2.0.0 - ADDED: Multi-language support
 * 
 * @var array  $item    Item data
 * @var string $entity  Entity name (e.g., 'visitors', 'branches')
 * @var array  $config  Module configuration
 * @var string $form_html The rendered form HTML from form-template.php
 */

if (!defined('ABSPATH')) exit;

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
$detail_url = home_url('/admin/' . $entity . '/' . $item['id'] . '/');
$item_name = $item['name'] ?? trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? '')) ?: '#' . $item['id'];

// Get title from translations, fallback to "Upravit: [name]"
$edit_prefix = $tr('form_edit_prefix', 'Upravit');
$sidebar_title = $edit_prefix . ': ' . $item_name;
?>

<div class="sa-sidebar sa-sidebar--active" data-mode="edit" data-entity="<?php echo esc_attr($entity); ?>" data-id="<?php echo intval($item['id']); ?>" data-current-id="<?php echo intval($item['id']); ?>">
    
    <!-- Sidebar Header -->
    <div class="sa-sidebar-header">
        <div class="sa-sidebar-title">
            <span class="sa-sidebar-icon">
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('pencil', 'sa-icon--md'); ?>
                <?php else: ?>
                    <span class="dashicons dashicons-edit"></span>
                <?php endif; ?>
            </span>
            <div class="sa-sidebar-title-text">
                <div class="sa-sidebar-module-name"><?php echo esc_html($config['plural'] ?? $config['title'] ?? ucfirst($entity)); ?></div>
                <h2 class="sa-sidebar-heading"><?php echo esc_html($sidebar_title); ?></h2>
            </div>
        </div>
        <div class="sa-sidebar-nav">
            <button type="button" class="sa-sidebar-nav-btn sa-sidebar-prev" title="Předchozí">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
            <button type="button" class="sa-sidebar-nav-btn sa-sidebar-next" title="Další">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
            <a href="<?php echo esc_url($detail_url); ?>" 
               class="sa-sidebar-close" 
               title="<?php echo esc_attr($tr('btn_close', 'Zavřít')); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </a>
        </div>
    </div>

    <!-- Scrollable Content (Form) -->
    <div class="sa-sidebar-content">
        <?php echo $form_html; ?>
    </div>

</div>