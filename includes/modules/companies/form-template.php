<?php
/**
 * Companies Form Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Companies
 * @since       1.0.0
 * @version     2.0.0 - ADDED: Multi-language support
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// LOAD TRANSLATIONS
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
// CONTEXT
// ============================================
$in_sidebar = isset($GLOBALS['saw_sidebar_form']) && $GLOBALS['saw_sidebar_form'];
$is_nested = isset($GLOBALS['saw_nested_inline_create']) && $GLOBALS['saw_nested_inline_create'];
$is_edit = !empty($item);
$item = $item ?? array();

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

$form_action = $is_edit 
    ? home_url('/admin/companies/' . $item['id'] . '/edit')
    : home_url('/admin/companies/create');
?>

<?php if (!$in_sidebar): ?>
<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo $is_edit ? esc_html($tr('form_title_edit', 'Upravit firmu')) : esc_html($tr('form_title_create', 'Nová firma')); ?>
        </h1>
        <a href="<?php echo esc_url(home_url('/admin/companies/')); ?>" class="saw-back-button">
            <?php if (class_exists('SAW_Icons')): ?>
                <?php echo SAW_Icons::get('chevron-left'); ?>
            <?php else: ?>
                <span class="dashicons dashicons-arrow-left-alt2"></span>
            <?php endif; ?>
            <?php echo esc_html($tr('btn_back_to_list', 'Zpět na seznam')); ?>
        </a>
    </div>
</div>
<?php endif; ?>

<div class="saw-form-container saw-module-companies">
    <form method="POST" action="<?php echo esc_url($form_action); ?>" class="saw-company-form" data-module="companies">
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
        
        <!-- ================================================ -->
        <!-- ZÁKLADNÍ INFORMACE -->
        <!-- ================================================ -->
        <details class="saw-form-section" open>
            <summary>
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('settings', 'saw-section-icon'); ?>
                <?php else: ?>
                    <span class="dashicons dashicons-admin-generic"></span>
                <?php endif; ?>
                <strong><?php echo esc_html($tr('form_section_basic', 'Základní informace')); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Branch Selection + IČO -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-8">
                        <label for="branch_id" class="saw-label saw-required">
                            <?php echo esc_html($tr('form_branch', 'Pobočka')); ?>
                        </label>
                        <select 
                            name="branch_id" 
                            id="branch_id" 
                            class="saw-input" 
                            required
                            <?php echo $is_edit ? 'disabled' : ''; ?>
                        >
                            <option value="">-- <?php echo esc_html($tr('form_select_branch', 'Vyberte pobočku')); ?> --</option>
                            <?php foreach ($branches as $branch_id => $branch_name): ?>
                                <option 
                                    value="<?php echo esc_attr($branch_id); ?>"
                                    <?php selected($selected_branch_id, $branch_id); ?>
                                >
                                    <?php echo esc_html($branch_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($is_edit): ?>
                            <input type="hidden" name="branch_id" value="<?php echo esc_attr($item['branch_id'] ?? ''); ?>">
                        <?php endif; ?>
                        
                        <?php if (!$is_edit && !$context_branch_id): ?>
                            <p class="saw-help-text saw-help-text-error">
                                ⚠️ <?php echo esc_html($tr('form_no_branch_warning', 'Není vybrána žádná pobočka v branch switcheru. Vyberte pobočku manuálně.')); ?>
                            </p>
                        <?php elseif (!$is_edit && $context_branch_id): ?>
                            <p class="saw-help-text saw-help-text-success">
                                ✅ <?php echo esc_html($tr('form_branch_prefilled', 'Pobočka předvyplněna z branch switcheru')); ?>
                            </p>
                        <?php else: ?>
                            <p class="saw-help-text"><?php echo esc_html($tr('form_branch_help', 'Pobočka ke které firma patří')); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="saw-form-group saw-col-4">
                        <label for="ico" class="saw-label">
                            <?php echo esc_html($tr('ico_label', 'IČO')); ?>
                        </label>
                        <input 
                            type="text" 
                            name="ico" 
                            id="ico" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['ico'] ?? ''); ?>"
                            placeholder="<?php echo esc_attr($tr('form_ico_placeholder', 'např. 12345678')); ?>"
                            maxlength="20"
                        >
                    </div>
                </div>
                
                <!-- Company Name -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="name" class="saw-label saw-required">
                            <?php echo esc_html($tr('form_name', 'Název firmy')); ?>
                        </label>
                        <input 
                            type="text" 
                            name="name" 
                            id="name" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['name'] ?? ''); ?>"
                            placeholder="<?php echo esc_attr($tr('form_name_placeholder', 'např. ABC s.r.o., XYZ a.s.')); ?>"
                            required
                        >
                    </div>
                </div>
                
                <!-- Archived Status -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label class="saw-checkbox-label">
                            <input 
                                type="checkbox" 
                                name="is_archived" 
                                id="is_archived" 
                                value="1"
                                <?php checked(isset($item['is_archived']) ? $item['is_archived'] : 0, 1); ?>
                            >
                            <span><?php echo esc_html($tr('form_archive_company', 'Archivovat firmu')); ?></span>
                        </label>
                        <p class="saw-help-text"><?php echo esc_html($tr('form_archive_help', 'Archivované firmy nejsou dostupné pro výběr')); ?></p>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ================================================ -->
        <!-- ADRESA -->
        <!-- ================================================ -->
        <details class="saw-form-section">
            <summary>
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('map-pin'); ?>
                <?php else: ?>
                    <span class="dashicons dashicons-location"></span>
                <?php endif; ?>
                <strong><?php echo esc_html($tr('form_section_address', 'Adresa sídla')); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Street -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="street" class="saw-label">
                            <?php echo esc_html($tr('form_street', 'Ulice a číslo popisné')); ?>
                        </label>
                        <input 
                            type="text" 
                            name="street" 
                            id="street" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['street'] ?? ''); ?>"
                            placeholder="<?php echo esc_attr($tr('form_street_placeholder', 'např. Hlavní 123')); ?>"
                        >
                    </div>
                </div>
                
                <!-- City + ZIP -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-8">
                        <label for="city" class="saw-label">
                            <?php echo esc_html($tr('form_city', 'Město')); ?>
                        </label>
                        <input 
                            type="text" 
                            name="city" 
                            id="city" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['city'] ?? ''); ?>"
                            placeholder="<?php echo esc_attr($tr('form_city_placeholder', 'např. Praha, Brno')); ?>"
                        >
                    </div>
                    
                    <div class="saw-form-group saw-col-4">
                        <label for="zip" class="saw-label">
                            <?php echo esc_html($tr('form_zip', 'PSČ')); ?>
                        </label>
                        <input 
                            type="text" 
                            name="zip" 
                            id="zip" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['zip'] ?? ''); ?>"
                            placeholder="<?php echo esc_attr($tr('form_zip_placeholder', 'např. 110 00')); ?>"
                            maxlength="20"
                        >
                    </div>
                </div>
                
                <!-- Country -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="country" class="saw-label">
                            <?php echo esc_html($tr('form_country', 'Země')); ?>
                        </label>
                        <input 
                            type="text" 
                            name="country" 
                            id="country" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['country'] ?? $tr('form_country_default', 'Česká republika')); ?>"
                            placeholder="<?php echo esc_attr($tr('form_country_default', 'Česká republika')); ?>"
                        >
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ================================================ -->
        <!-- KONTAKTNÍ ÚDAJE -->
        <!-- ================================================ -->
        <details class="saw-form-section">
            <summary>
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('mail'); ?>
                <?php else: ?>
                    <span class="dashicons dashicons-email"></span>
                <?php endif; ?>
                <strong><?php echo esc_html($tr('form_section_contact', 'Kontaktní údaje')); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Email -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="email" class="saw-label">
                            <?php echo esc_html($tr('field_email', 'Email')); ?>
                        </label>
                        <input 
                            type="email" 
                            name="email" 
                            id="email" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['email'] ?? ''); ?>"
                            placeholder="<?php echo esc_attr($tr('form_email_placeholder', 'např. info@firma.cz')); ?>"
                        >
                    </div>
                </div>
                
                <!-- Phone -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="phone" class="saw-label">
                            <?php echo esc_html($tr('field_phone', 'Telefon')); ?>
                        </label>
                        <input 
                            type="text" 
                            name="phone" 
                            id="phone" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['phone'] ?? ''); ?>"
                            placeholder="<?php echo esc_attr($tr('form_phone_placeholder', 'např. +420 123 456 789')); ?>"
                            maxlength="50"
                        >
                    </div>
                </div>
                
                <!-- Website -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="website" class="saw-label">
                            <?php echo esc_html($tr('field_website', 'Web')); ?>
                        </label>
                        <input 
                            type="url" 
                            name="website" 
                            id="website" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['website'] ?? ''); ?>"
                            placeholder="<?php echo esc_attr($tr('form_website_placeholder', 'např. https://www.firma.cz')); ?>"
                        >
                        <p class="saw-help-text"><?php echo esc_html($tr('form_website_help', 'Webová stránka firmy (včetně https://)')); ?></p>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ================================================ -->
        <!-- FORM ACTIONS -->
        <!-- ================================================ -->
        <div class="saw-form-actions">
            <button type="submit" class="saw-button saw-button-primary">
                <?php echo $is_edit ? esc_html($tr('btn_save_changes', 'Uložit změny')) : esc_html($tr('btn_create_company', 'Vytvořit firmu')); ?>
            </button>
            
            <?php if (!$in_sidebar): ?>
                <a href="<?php echo esc_url(home_url('/admin/companies/')); ?>" class="saw-button saw-button-secondary">
                    <?php echo esc_html($tr('btn_cancel', 'Zrušit')); ?>
                </a>
            <?php endif; ?>
        </div>
        
    </form>
</div>