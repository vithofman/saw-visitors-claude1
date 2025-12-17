<?php
/**
 * Customers Form Template
 * 
 * Create/Edit form for customers with complete data structure.
 * Optimized for both standalone page and sidebar display.
 * Uses translation system for multi-language support.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Customers/Templates
 * @since       1.0.0
 * @version     3.0.0 - REFACTOR: Translations, emoji icons, proper structure
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// TRANSLATION SETUP
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}

$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'customers') 
    : array();

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// FORM STATE
// ============================================
$is_edit = !empty($item);
$item = $item ?? array();
$in_sidebar = isset($GLOBALS['saw_sidebar_form']) && $GLOBALS['saw_sidebar_form'];

// Ensure account_types exists
if (!isset($account_types)) {
    $account_types = array();
}

// Status options
$status_options = array(
    'potential' => $tr('status_potential', 'Potenciální'),
    'active' => $tr('status_active', 'Aktivní'),
    'inactive' => $tr('status_inactive', 'Neaktivní'),
);

// Language options (for admin_language_default)
$language_options = array(
    'cs' => $tr('lang_cs', '🇨🇿 Čeština'),
    'en' => $tr('lang_en', '🇬🇧 English'),
    'de' => $tr('lang_de', '🇩🇪 Deutsch'),
    'sk' => $tr('lang_sk', '🇸🇰 Slovenčina'),
);
?>

<?php if (!$in_sidebar): ?>
<!-- ============================================
     PAGE HEADER (standalone mode)
     ============================================ -->
<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo $is_edit 
                ? esc_html($tr('form_title_edit', 'Upravit zákazníka')) 
                : esc_html($tr('form_title_create', 'Nový zákazník')); ?>
        </h1>
        <?php
        $back_url = $is_edit 
            ? home_url('/admin/customers/' . ($item['id'] ?? '') . '/') 
            : home_url('/admin/customers/');
        ?>
        <a href="<?php echo esc_url($back_url); ?>" class="saw-back-button">
            <span class="saw-back-arrow">←</span>
            <?php echo esc_html($tr('btn_back_to_list', 'Zpět na seznam')); ?>
        </a>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
     FORM CONTAINER
     ============================================ -->
<div class="saw-form-container">
    <form method="post" action="" enctype="multipart/form-data" class="saw-customer-form">
        <?php 
        // Nonce field matching Base Controller expectations
        $nonce_action = $is_edit ? 'saw_edit_customers' : 'saw_create_customers';
        wp_nonce_field($nonce_action, '_wpnonce', false);
        ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
        <?php endif; ?>
        
        <!-- ============================================
             SECTION: BASIC INFORMATION
             ============================================ -->
        <details class="saw-form-section" open>
            <summary>
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('building-2', 'saw-section-icon'); ?>
                <?php else: ?>
                    <span class="saw-section-emoji">🏢</span>
                <?php endif; ?>
                <strong><?php echo esc_html($tr('section_basic_info', 'Základní informace')); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Name + Status -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-8">
                        <label for="name" class="saw-label saw-required">
                            <?php echo esc_html($tr('field_name', 'Název společnosti')); ?>
                        </label>
                        <input type="text" id="name" name="name" class="saw-input"
                               value="<?php echo esc_attr($item['name'] ?? ''); ?>" 
                               placeholder="<?php echo esc_attr($tr('placeholder_name', 'Zadejte název společnosti')); ?>"
                               required>
                    </div>
                    
                    <div class="saw-form-group saw-col-4">
                        <label for="status" class="saw-label saw-required">
                            <?php echo esc_html($tr('field_status', 'Status')); ?>
                        </label>
                        <select id="status" name="status" class="saw-input" required>
                            <?php foreach ($status_options as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" 
                                        <?php selected($item['status'] ?? 'potential', $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Account Type -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="account_type_id" class="saw-label">
                            <?php echo esc_html($tr('field_account_type', 'Typ účtu')); ?>
                        </label>
                        <select id="account_type_id" name="account_type_id" class="saw-input">
                            <option value=""><?php echo esc_html($tr('select_account_type', '-- Vyberte typ účtu --')); ?></option>
                            <?php if (!empty($account_types) && is_array($account_types)): ?>
                                <?php foreach ($account_types as $type): ?>
                                    <option value="<?php echo esc_attr($type['id']); ?>" 
                                            <?php selected($item['account_type_id'] ?? '', $type['id']); ?>>
                                        <?php echo esc_html($type['display_name'] ?? $type['name']); ?>
                                        <?php if (!empty($type['price'])): ?>
                                            (<?php echo esc_html(number_format($type['price'], 0, ',', ' ')); ?> Kč/měs)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled><?php echo esc_html($tr('no_account_types', 'Žádné typy účtů k dispozici')); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ============================================
             SECTION: COMPANY DETAILS
             ============================================ -->
        <details class="saw-form-section">
            <summary>
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('clipboard-list', 'saw-section-icon'); ?>
                <?php else: ?>
                    <span class="saw-section-emoji">📋</span>
                <?php endif; ?>
                <strong><?php echo esc_html($tr('section_company_details', 'Údaje společnosti')); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- IČO + DIČ -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="ico" class="saw-label">
                            <?php echo esc_html($tr('field_ico', 'IČO')); ?>
                        </label>
                        <input type="text" id="ico" name="ico" class="saw-input"
                               value="<?php echo esc_attr($item['ico'] ?? ''); ?>"
                               placeholder="<?php echo esc_attr($tr('placeholder_ico', '12345678')); ?>">
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="dic" class="saw-label">
                            <?php echo esc_html($tr('field_dic', 'DIČ')); ?>
                        </label>
                        <input type="text" id="dic" name="dic" class="saw-input"
                               value="<?php echo esc_attr($item['dic'] ?? ''); ?>"
                               placeholder="<?php echo esc_attr($tr('placeholder_dic', 'CZ12345678')); ?>">
                    </div>
                </div>
                
                <!-- Logo Upload -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <?php
                        $id = 'logo';
                        $name = 'logo';
                        $current_file_url = $item['logo_url'] ?? '';
                        $label = $tr('field_logo', 'Logo společnosti');
                        $current_label = $tr('label_current_logo', 'Současné logo');
                        $help_text = $tr('hint_logo', 'Nahrajte logo ve formátu JPG, PNG, SVG nebo WebP (max 2MB)');
                        $accept = 'image/jpeg,image/png,image/svg+xml,image/webp';
                        $show_preview = true;
                        $config = array();
                        
                        require SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/file-upload-input.php';
                        ?>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ============================================
             SECTION: ADDRESS
             ============================================ -->
        <details class="saw-form-section">
            <summary>
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('map-pin', 'saw-section-icon'); ?>
                <?php else: ?>
                    <span class="saw-section-emoji">📍</span>
                <?php endif; ?>
                <strong><?php echo esc_html($tr('section_address', 'Sídlo společnosti')); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Street -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="address_street" class="saw-label">
                            <?php echo esc_html($tr('field_address_street', 'Ulice a číslo popisné')); ?>
                        </label>
                        <input type="text" id="address_street" name="address_street" class="saw-input"
                               value="<?php echo esc_attr($item['address_street'] ?? ''); ?>"
                               placeholder="<?php echo esc_attr($tr('placeholder_street', 'Příkladová 123/4')); ?>">
                    </div>
                </div>
                
                <!-- City + ZIP -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-8">
                        <label for="address_city" class="saw-label">
                            <?php echo esc_html($tr('field_address_city', 'Město')); ?>
                        </label>
                        <input type="text" id="address_city" name="address_city" class="saw-input"
                               value="<?php echo esc_attr($item['address_city'] ?? ''); ?>"
                               placeholder="<?php echo esc_attr($tr('placeholder_city', 'Praha')); ?>">
                    </div>
                    
                    <div class="saw-form-group saw-col-4">
                        <label for="address_zip" class="saw-label">
                            <?php echo esc_html($tr('field_address_zip', 'PSČ')); ?>
                        </label>
                        <input type="text" id="address_zip" name="address_zip" class="saw-input"
                               value="<?php echo esc_attr($item['address_zip'] ?? ''); ?>"
                               placeholder="<?php echo esc_attr($tr('placeholder_zip', '110 00')); ?>">
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ============================================
             SECTION: BILLING ADDRESS
             ============================================ -->
        <details class="saw-form-section">
            <summary>
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('badge-check', 'saw-section-icon'); ?>
                <?php else: ?>
                    <span class="saw-section-emoji">💳</span>
                <?php endif; ?>
                <strong><?php echo esc_html($tr('section_billing_address', 'Fakturační adresa')); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <p class="saw-help-text">
                    <?php echo esc_html($tr('hint_billing_address', 'Vyplňte pouze pokud se liší od sídla společnosti.')); ?>
                </p>
                
                <!-- Billing Street -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="billing_address_street" class="saw-label">
                            <?php echo esc_html($tr('field_billing_street', 'Ulice a číslo popisné')); ?>
                        </label>
                        <input type="text" id="billing_address_street" name="billing_address_street" class="saw-input"
                               value="<?php echo esc_attr($item['billing_address_street'] ?? ''); ?>">
                    </div>
                </div>
                
                <!-- Billing City + ZIP -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-8">
                        <label for="billing_address_city" class="saw-label">
                            <?php echo esc_html($tr('field_billing_city', 'Město')); ?>
                        </label>
                        <input type="text" id="billing_address_city" name="billing_address_city" class="saw-input"
                               value="<?php echo esc_attr($item['billing_address_city'] ?? ''); ?>">
                    </div>
                    
                    <div class="saw-form-group saw-col-4">
                        <label for="billing_address_zip" class="saw-label">
                            <?php echo esc_html($tr('field_billing_zip', 'PSČ')); ?>
                        </label>
                        <input type="text" id="billing_address_zip" name="billing_address_zip" class="saw-input"
                               value="<?php echo esc_attr($item['billing_address_zip'] ?? ''); ?>">
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ============================================
             SECTION: CONTACT
             ============================================ -->
        <details class="saw-form-section">
            <summary>
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('mail', 'saw-section-icon'); ?>
                <?php else: ?>
                    <span class="saw-section-emoji">📧</span>
                <?php endif; ?>
                <strong><?php echo esc_html($tr('section_contact', 'Kontaktní údaje')); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Contact Person -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="contact_person" class="saw-label">
                            <?php echo esc_html($tr('field_contact_person', 'Kontaktní osoba')); ?>
                        </label>
                        <input type="text" id="contact_person" name="contact_person" class="saw-input"
                               value="<?php echo esc_attr($item['contact_person'] ?? ''); ?>"
                               placeholder="<?php echo esc_attr($tr('placeholder_contact_person', 'Jan Novák')); ?>">
                    </div>
                </div>
                
                <!-- Email + Phone -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="contact_email" class="saw-label">
                            <?php echo esc_html($tr('field_contact_email', 'E-mail')); ?>
                        </label>
                        <input type="email" id="contact_email" name="contact_email" class="saw-input"
                               value="<?php echo esc_attr($item['contact_email'] ?? ''); ?>"
                               placeholder="<?php echo esc_attr($tr('placeholder_email', 'info@firma.cz')); ?>">
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="contact_phone" class="saw-label">
                            <?php echo esc_html($tr('field_contact_phone', 'Telefon')); ?>
                        </label>
                        <input type="text" id="contact_phone" name="contact_phone" class="saw-input"
                               value="<?php echo esc_attr($item['contact_phone'] ?? ''); ?>"
                               placeholder="<?php echo esc_attr($tr('placeholder_phone', '+420 123 456 789')); ?>">
                    </div>
                </div>
                
                <!-- Website -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="website" class="saw-label">
                            <?php echo esc_html($tr('field_website', 'Webové stránky')); ?>
                        </label>
                        <input type="url" id="website" name="website" class="saw-input"
                               value="<?php echo esc_attr($item['website'] ?? ''); ?>"
                               placeholder="https://www.firma.cz">
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ============================================
             SECTION: SETTINGS
             ============================================ -->
        <details class="saw-form-section">
            <summary>
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('settings', 'saw-section-icon'); ?>
                <?php else: ?>
                    <span class="saw-section-emoji">⚙️</span>
                <?php endif; ?>
                <strong><?php echo esc_html($tr('section_settings', 'Nastavení')); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Default Language -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="admin_language_default" class="saw-label">
                            <?php echo esc_html($tr('field_default_language', 'Výchozí jazyk')); ?>
                        </label>
                        <select id="admin_language_default" name="admin_language_default" class="saw-input">
                            <?php foreach ($language_options as $code => $label): ?>
                                <option value="<?php echo esc_attr($code); ?>" 
                                        <?php selected($item['admin_language_default'] ?? 'cs', $code); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="saw-help-text">
                            <?php echo esc_html($tr('hint_default_language', 'Jazyk použitý pro administraci zákazníka')); ?>
                        </p>
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="primary_color" class="saw-label">
                            <?php echo esc_html($tr('field_primary_color', 'Primární barva')); ?>
                        </label>
                        <input type="color" id="primary_color" name="primary_color" class="saw-input saw-color-input"
                               value="<?php echo esc_attr($item['primary_color'] ?? '#4F46E5'); ?>">
                        <p class="saw-help-text">
                            <?php echo esc_html($tr('hint_primary_color', 'Barva použitá v brandingu zákazníka')); ?>
                        </p>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ============================================
             SECTION: NOTES
             ============================================ -->
        <details class="saw-form-section">
            <summary>
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('file-text', 'saw-section-icon'); ?>
                <?php else: ?>
                    <span class="saw-section-emoji">📝</span>
                <?php endif; ?>
                <strong><?php echo esc_html($tr('section_notes', 'Poznámky')); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="notes" class="saw-label">
                            <?php echo esc_html($tr('field_notes', 'Interní poznámky')); ?>
                        </label>
                        <textarea id="notes" name="notes" class="saw-input" rows="5"
                                  placeholder="<?php echo esc_attr($tr('placeholder_notes', 'Poznámky viditelné pouze administrátorům...')); ?>"
                        ><?php echo esc_textarea($item['notes'] ?? ''); ?></textarea>
                        <p class="saw-help-text">
                            <?php echo esc_html($tr('hint_notes', 'Interní poznámky viditelné pouze administrátorům')); ?>
                        </p>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ============================================
             FORM ACTIONS
             ============================================ -->
        <?php 
        // Form actions - only show outside sidebar (sidebar uses FAB save button)
        if (!$in_sidebar): 
        ?>
        <div class="saw-form-actions">
            <button type="submit" class="saw-button saw-button-primary">
                <span class="saw-btn-icon">💾</span>
                <?php echo $is_edit 
                    ? esc_html($tr('btn_save', 'Uložit změny')) 
                    : esc_html($tr('btn_create', 'Vytvořit zákazníka')); ?>
            </button>
            <a href="<?php echo esc_url(home_url('/admin/customers/')); ?>" class="saw-button saw-button-secondary">
                <?php echo esc_html($tr('btn_cancel', 'Zrušit')); ?>
            </a>
        </div>
        <?php endif; ?>
        
    </form>
</div>

<!-- ============================================
     FORM STYLES
     ============================================ -->
<style>
/* Section Emoji Icons */
.saw-section-emoji {
    font-size: 18px;
    line-height: 1;
    margin-right: 8px;
}

/* Back Arrow */
.saw-back-arrow {
    font-size: 14px;
    margin-right: 4px;
}

/* Button Icon */
.saw-btn-icon {
    font-size: 14px;
    margin-right: 6px;
}

/* Color Input */
.saw-color-input {
    width: 100%;
    height: 42px;
    padding: 4px;
    cursor: pointer;
}

/* Help Text */
.saw-help-text {
    font-size: 13px;
    color: #64748b;
    margin-top: 4px;
    margin-bottom: 0;
}
</style>