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

<div class="saw-sidebar saw-detail-sidebar" data-entity="<?php echo esc_attr($entity); ?>" data-id="<?php echo intval($item['id']); ?>">
    
    <!-- Sidebar Header -->
    <div class="saw-sidebar-header">
        <div class="saw-sidebar-header-content">
            <h2 class="saw-sidebar-title">
                <span class="dashicons dashicons-edit"></span>
                <?php echo esc_html($sidebar_title); ?>
            </h2>
            <a href="<?php echo esc_url($detail_url); ?>" 
               class="saw-sidebar-close" 
               title="<?php echo esc_attr($tr('btn_close', 'Zavřít')); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </a>
        </div>
    </div>

    <!-- Scrollable Content (Form) -->
    <div class="saw-sidebar-content">
        <?php echo $form_html; ?>
    </div>

</div>