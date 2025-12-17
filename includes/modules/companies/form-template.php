<?php
/**
 * Companies Form Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Companies
 * @since       1.0.0
 * @version     3.0.0 - REFACTORED: Unified class naming with branches (sa- prefix)
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// TRANSLATIONS
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}
$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'companies') 
    : [];

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// SETUP
// ============================================
$is_edit = !empty($item);
$item = $item ?? array();
$in_sidebar = isset($GLOBALS['saw_sidebar_form']) && $GLOBALS['saw_sidebar_form'];
$is_nested = isset($GLOBALS['saw_nested_inline_create']) && $GLOBALS['saw_nested_inline_create'];

$customer_id = SAW_Context::get_customer_id();
$context_branch_id = SAW_Context::get_branch_id();

// Get branches
$branches = $branches ?? array();
if (empty($branches) && $customer_id) {
    global $wpdb;
    $branches_data = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name FROM %i WHERE customer_id = %d AND is_active = 1 ORDER BY name ASC",
        $wpdb->prefix . 'saw_branches',
        $customer_id
    ), ARRAY_A);
    
    $branches = array();
    foreach ($branches_data as $branch) {
        $branches[$branch['id']] = $branch['name'];
    }
}

// Pre-fill branch
$selected_branch_id = null;
if ($is_edit && !empty($item['branch_id'])) {
    $selected_branch_id = $item['branch_id'];
} elseif (!$is_edit && $context_branch_id) {
    $selected_branch_id = $context_branch_id;
}
?>

<?php if (!$in_sidebar): ?>
<div class="sa-page-header">
    <div class="sa-page-header-content">
        <h1 class="sa-page-title">
            <?php echo $is_edit 
                ? esc_html($tr('form_title_edit', 'Upravit firmu')) 
                : esc_html($tr('form_title_create', 'Nová firma')); ?>
        </h1>
        <a href="<?php echo esc_url(home_url('/admin/companies/')); ?>" class="sa-btn sa-btn--ghost">
            <?php if (class_exists('SAW_Icons')): ?>
                <?php echo SAW_Icons::get('chevron-left'); ?>
            <?php else: ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            <?php endif; ?>
            <?php echo esc_html($tr('btn_back', 'Zpět na seznam')); ?>
        </a>
    </div>
</div>
<?php endif; ?>

<div class="sa-form-container">
    <form method="post" action="" enctype="multipart/form-data" class="sa-form" id="saw-companies-form">
        <?php
        $nonce_action = $is_edit ? 'saw_edit_companies' : 'saw_create_companies';
        wp_nonce_field($nonce_action, '_wpnonce', false);
        ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
        <?php endif; ?>
        
        <input type="hidden" name="customer_id" value="<?php echo esc_attr($customer_id); ?>">
        
        <?php if ($is_nested): ?>
            <input type="hidden" name="_ajax_inline_create" value="1">
        <?php endif; ?>
        
        <!-- ============================================ -->
        <!-- ZÁKLADNÍ INFORMACE -->
        <!-- ============================================ -->
        <details class="sa-form-section" open>
            <summary>
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('settings', 'sa-form-section-icon'); ?>
                <?php else: ?>
                    <span class="dashicons dashicons-admin-generic"></span>
                <?php endif; ?>
                <strong class="sa-form-section-title"><?php echo esc_html($tr('section_basic', 'Základní informace')); ?></strong>
            </summary>
            <div class="sa-form-section-content">
                
                <div class="sa-form-row">
                    <div class="sa-form-group">
                        <label for="name" class="sa-form-label sa-form-label--required">
                            <?php echo esc_html($tr('field_name', 'Název firmy')); ?>
                        </label>
                        <input type="text" id="name" name="name" class="sa-input"
                               value="<?php echo esc_attr($item['name'] ?? ''); ?>" 
                               placeholder="<?php echo esc_attr($tr('field_name_placeholder', 'např. ABC s.r.o.')); ?>"
                               required>
                    </div>
                    
                    <div class="sa-form-group">
                        <label for="ico" class="sa-form-label">
                            <?php echo esc_html($tr('ico_label', 'IČO')); ?>
                        </label>
                        <input type="text" id="ico" name="ico" class="sa-input"
                               value="<?php echo esc_attr($item['ico'] ?? ''); ?>"
                               placeholder="<?php echo esc_attr($tr('field_ico_placeholder', 'např. 12345678')); ?>"
                               maxlength="20">
                    </div>
                </div>
                
                <div class="sa-form-row">
                    <div class="sa-form-group">
                        <label for="branch_id" class="sa-form-label sa-form-label--required">
                            <?php echo esc_html($tr('field_branch', 'Pobočka')); ?>
                        </label>
                        <select name="branch_id" id="branch_id" class="sa-input" required <?php echo $is_edit ? 'disabled' : ''; ?>>
                            <option value="">-- <?php echo esc_html($tr('field_branch_select', 'Vyberte pobočku')); ?> --</option>
                            <?php foreach ($branches as $branch_id => $branch_name): ?>
                                <option value="<?php echo esc_attr($branch_id); ?>" <?php selected($selected_branch_id, $branch_id); ?>>
                                    <?php echo esc_html($branch_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($is_edit): ?>
                            <input type="hidden" name="branch_id" value="<?php echo esc_attr($item['branch_id'] ?? ''); ?>">
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="sa-form-row" style="margin-top: 16px;">
                    <div class="sa-form-group">
                        <label class="sa-checkbox sa-checkbox--highlight">
                            <input type="checkbox" name="is_archived" value="1"
                                   <?php checked(!empty($item['is_archived'])); ?>>
                            <span><?php echo esc_html($tr('field_is_archived', 'Archivovat firmu')); ?></span>
                        </label>
                        <p class="sa-form-help"><?php echo esc_html($tr('field_archive_help', 'Archivované firmy nejsou dostupné pro výběr')); ?></p>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ============================================ -->
        <!-- KONTAKT -->
        <!-- ============================================ -->
        <details class="sa-form-section" open>
            <summary>
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('phone', 'sa-form-section-icon'); ?>
                <?php else: ?>
                    <span class="dashicons dashicons-phone"></span>
                <?php endif; ?>
                <strong class="sa-form-section-title"><?php echo esc_html($tr('section_contact', 'Kontaktní údaje')); ?></strong>
            </summary>
            <div class="sa-form-section-content">
                
                <div class="sa-form-row">
                    <div class="sa-form-group">
                        <label for="phone" class="sa-form-label">
                            <?php echo esc_html($tr('field_phone', 'Telefon')); ?>
                        </label>
                        <input type="text" id="phone" name="phone" class="sa-input"
                               value="<?php echo esc_attr($item['phone'] ?? ''); ?>"
                               placeholder="+420 123 456 789">
                    </div>
                    
                    <div class="sa-form-group">
                        <label for="email" class="sa-form-label">
                            <?php echo esc_html($tr('field_email', 'Email')); ?>
                        </label>
                        <input type="email" id="email" name="email" class="sa-input"
                               value="<?php echo esc_attr($item['email'] ?? ''); ?>"
                               placeholder="info@firma.cz">
                    </div>
                </div>
                
                <div class="sa-form-row">
                    <div class="sa-form-group">
                        <label for="website" class="sa-form-label">
                            <?php echo esc_html($tr('field_website', 'Web')); ?>
                        </label>
                        <input type="url" id="website" name="website" class="sa-input"
                               value="<?php echo esc_attr($item['website'] ?? ''); ?>"
                               placeholder="https://www.firma.cz">
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ============================================ -->
        <!-- ADRESA -->
        <!-- ============================================ -->
        <details class="sa-form-section" open>
            <summary>
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('map-pin', 'sa-form-section-icon'); ?>
                <?php else: ?>
                    <span class="dashicons dashicons-location"></span>
                <?php endif; ?>
                <strong class="sa-form-section-title"><?php echo esc_html($tr('section_address', 'Adresa')); ?></strong>
            </summary>
            <div class="sa-form-section-content">
                
                <div class="sa-form-row">
                    <div class="sa-form-group">
                        <label for="street" class="sa-form-label">
                            <?php echo esc_html($tr('field_street', 'Ulice a č.p.')); ?>
                        </label>
                        <input type="text" id="street" name="street" class="sa-input"
                               value="<?php echo esc_attr($item['street'] ?? ''); ?>"
                               placeholder="<?php echo esc_attr($tr('field_street_placeholder', 'Hlavní 123')); ?>">
                    </div>
                </div>
                
                <div class="sa-form-row" style="margin-top: 12px;">
                    <div class="sa-form-group">
                        <label for="city" class="sa-form-label">
                            <?php echo esc_html($tr('field_city', 'Město')); ?>
                        </label>
                        <input type="text" id="city" name="city" class="sa-input"
                               value="<?php echo esc_attr($item['city'] ?? ''); ?>"
                               placeholder="Praha">
                    </div>
                    
                    <div class="sa-form-group">
                        <label for="zip" class="sa-form-label">
                            <?php echo esc_html($tr('field_zip', 'PSČ')); ?>
                        </label>
                        <input type="text" id="zip" name="zip" class="sa-input"
                               value="<?php echo esc_attr($item['zip'] ?? ''); ?>"
                               placeholder="110 00">
                    </div>
                    
                    <div class="sa-form-group">
                        <label for="country" class="sa-form-label">
                            <?php echo esc_html($tr('field_country', 'Země')); ?>
                        </label>
                        <input type="text" id="country" name="country" class="sa-input"
                               value="<?php echo esc_attr($item['country'] ?? 'Česká republika'); ?>"
                               placeholder="Česká republika">
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ============================================ -->
        <!-- SUBMIT -->
        <!-- ============================================ -->
        <?php 
        // Form actions - only show outside sidebar (sidebar uses FAB save button)
        if (!$in_sidebar): 
        ?>
        <div class="sa-form-actions">
            <button type="submit" class="sa-btn sa-btn--primary">
                <?php echo $is_edit 
                    ? esc_html($tr('btn_save', 'Uložit změny')) 
                    : esc_html($tr('btn_create', 'Vytvořit firmu')); ?>
            </button>
            <a href="<?php echo esc_url(home_url('/admin/companies/')); ?>" class="sa-btn sa-btn--secondary">
                <?php echo esc_html($tr('btn_cancel', 'Zrušit')); ?>
            </a>
        </div>
        <?php endif; ?>
        
    </form>
</div>