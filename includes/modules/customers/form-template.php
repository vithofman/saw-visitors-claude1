<?php
/**
 * Customers Form Template - SIDEBAR OPTIMIZED
 * 
 * Create/Edit form for customers with complete data structure.
 * Optimized for both standalone page and sidebar display.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Customers/Templates
 * @since       1.0.0
 * @version     4.0.0 - SIDEBAR SUPPORT
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_edit = !empty($item);
$item = $item ?? array();
$in_sidebar = isset($GLOBALS['saw_sidebar_form']) && $GLOBALS['saw_sidebar_form'];
?>

<?php if (!$in_sidebar): ?>
<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo $is_edit ? esc_html__('Upravit zákazníka', 'saw-visitors') : esc_html__('Nový zákazník', 'saw-visitors'); ?>
        </h1>
        <a href="<?php echo esc_url(home_url('/admin/settings/customers/')); ?>" class="saw-back-button">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            <?php echo esc_html__('Zpět na seznam', 'saw-visitors'); ?>
        </a>
    </div>
</div>
<?php endif; ?>

<div class="saw-form-container">
    <form method="post" enctype="multipart/form-data" class="saw-customer-form">
        <?php wp_nonce_field('saw_customers_form', 'saw_nonce'); ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
        <?php endif; ?>
        
        <!-- BASIC INFORMATION -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-generic"></span>
                <strong><?php echo esc_html__('Základní informace', 'saw-visitors'); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-8">
                        <label for="name" class="saw-label saw-required"><?php echo esc_html__('Název společnosti', 'saw-visitors'); ?></label>
                        <input type="text" id="name" name="name" class="saw-input"
                               value="<?php echo esc_attr($item['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="saw-form-group saw-col-4">
                        <label for="status" class="saw-label saw-required"><?php echo esc_html__('Status', 'saw-visitors'); ?></label>
                        <select id="status" name="status" class="saw-input" required>
                            <option value="potential" <?php selected($item['status'] ?? '', 'potential'); ?>><?php echo esc_html__('Potenciální', 'saw-visitors'); ?></option>
                            <option value="active" <?php selected($item['status'] ?? '', 'active'); ?>><?php echo esc_html__('Aktivní', 'saw-visitors'); ?></option>
                            <option value="inactive" <?php selected($item['status'] ?? '', 'inactive'); ?>><?php echo esc_html__('Neaktivní', 'saw-visitors'); ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-4">
                        <label for="ico" class="saw-label"><?php echo esc_html__('IČO', 'saw-visitors'); ?></label>
                        <input type="text" id="ico" name="ico" class="saw-input"
                               pattern="\d{8}" placeholder="12345678"
                               value="<?php echo esc_attr($item['ico'] ?? ''); ?>">
                        <span class="saw-help-text"><?php echo esc_html__('8 číslic', 'saw-visitors'); ?></span>
                    </div>
                    
                    <div class="saw-form-group saw-col-4">
                        <label for="dic" class="saw-label"><?php echo esc_html__('DIČ', 'saw-visitors'); ?></label>
                        <input type="text" id="dic" name="dic" class="saw-input"
                               placeholder="CZ12345678"
                               value="<?php echo esc_attr($item['dic'] ?? ''); ?>">
                    </div>
                    
                    <div class="saw-form-group saw-col-4">
                        <label for="account_type_id" class="saw-label"><?php echo esc_html__('Typ předplatného', 'saw-visitors'); ?></label>
                        <select id="account_type_id" name="account_type_id" class="saw-input">
                            <option value="">-- <?php echo esc_html__('Vyberte typ', 'saw-visitors'); ?> --</option>
                            <?php if (!empty($account_types)): ?>
                                <?php foreach ($account_types as $type): ?>
                                    <option 
                                        value="<?php echo esc_attr($type['id']); ?>"
                                        <?php selected($item['account_type_id'] ?? '', $type['id']); ?>
                                    >
                                        <?php echo esc_html($type['display_name']); ?>
                                        <?php if (!empty($type['price']) && $type['price'] > 0): ?>
                                            (<?php echo esc_html(number_format($type['price'], 0, ',', ' ')); ?> <?php echo esc_html__('Kč/měs.', 'saw-visitors'); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <span class="saw-help-text"><?php echo esc_html__('Výběr typu předplatného', 'saw-visitors'); ?></span>
                    </div>
                </div>
            </div>
        </details>
        
        <!-- BRANDING -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-art"></span>
                <strong><?php echo esc_html__('Branding a design', 'saw-visitors'); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-branding-grid">
                    <div class="saw-branding-upload">
                        <?php
                        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/class-saw-file-uploader.php';
                        $id = 'logo';
                        $name = 'logo';
                        $current_file_url = $item['logo_url'] ?? '';
                        $label = __('Nahrát nové logo', 'saw-visitors');
                        $current_label = __('Současné logo', 'saw-visitors');
                        include SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/file-upload-input.php';
                        ?>
                    </div>
                    
                    <div class="saw-branding-color">
                        <?php
                        $id = 'primary_color';
                        $name = 'primary_color';
                        $value = $item['primary_color'] ?? '#1e40af';
                        $label = __('Hlavní barva', 'saw-visitors');
                        include SAW_VISITORS_PLUGIN_DIR . 'includes/components/color-picker/color-picker-input.php';
                        ?>
                    </div>
                </div>
            </div>
        </details>
        
        <!-- CONTACT INFORMATION -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-email"></span>
                <strong><?php echo esc_html__('Kontaktní údaje', 'saw-visitors'); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="contact_person" class="saw-label"><?php echo esc_html__('Kontaktní osoba', 'saw-visitors'); ?></label>
                        <input type="text" id="contact_person" name="contact_person" class="saw-input"
                               value="<?php echo esc_attr($item['contact_person'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="contact_email" class="saw-label"><?php echo esc_html__('Email', 'saw-visitors'); ?></label>
                        <input type="email" id="contact_email" name="contact_email" class="saw-input"
                               value="<?php echo esc_attr($item['contact_email'] ?? ''); ?>">
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="contact_phone" class="saw-label"><?php echo esc_html__('Telefon', 'saw-visitors'); ?></label>
                        <input type="text" id="contact_phone" name="contact_phone" class="saw-input"
                               value="<?php echo esc_attr($item['contact_phone'] ?? ''); ?>">
                    </div>
                </div>
            </div>
        </details>
        
        <!-- OPERATING ADDRESS -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-location"></span>
                <strong><?php echo esc_html__('Provozní adresa', 'saw-visitors'); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-address-fields">
                    <div class="saw-form-group">
                        <label for="address_street" class="saw-label"><?php echo esc_html__('Ulice', 'saw-visitors'); ?></label>
                        <input type="text" id="address_street" name="address_street" class="saw-input"
                               value="<?php echo esc_attr($item['address_street'] ?? ''); ?>">
                    </div>
                    
                    <div class="saw-form-group">
                        <label for="address_number" class="saw-label"><?php echo esc_html__('Číslo popisné', 'saw-visitors'); ?></label>
                        <input type="text" id="address_number" name="address_number" class="saw-input"
                               value="<?php echo esc_attr($item['address_number'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="saw-address-city-zip">
                    <div class="saw-form-group">
                        <label for="address_city" class="saw-label"><?php echo esc_html__('Město', 'saw-visitors'); ?></label>
                        <input type="text" id="address_city" name="address_city" class="saw-input"
                               value="<?php echo esc_attr($item['address_city'] ?? ''); ?>">
                    </div>
                    
                    <div class="saw-form-group">
                        <label for="address_zip" class="saw-label"><?php echo esc_html__('PSČ', 'saw-visitors'); ?></label>
                        <input type="text" id="address_zip" name="address_zip" class="saw-input"
                               pattern="\d{3}\s?\d{2}" placeholder="123 45"
                               value="<?php echo esc_attr($item['address_zip'] ?? ''); ?>">
                    </div>
                </div>
            </div>
        </details>
        
        <!-- BILLING ADDRESS -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-media-document"></span>
                <strong><?php echo esc_html__('Fakturační adresa', 'saw-visitors'); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-address-fields">
                    <div class="saw-form-group">
                        <label for="billing_address_street" class="saw-label"><?php echo esc_html__('Ulice', 'saw-visitors'); ?></label>
                        <input type="text" id="billing_address_street" name="billing_address_street" class="saw-input"
                               value="<?php echo esc_attr($item['billing_address_street'] ?? ''); ?>">
                    </div>
                    
                    <div class="saw-form-group">
                        <label for="billing_address_number" class="saw-label"><?php echo esc_html__('Číslo popisné', 'saw-visitors'); ?></label>
                        <input type="text" id="billing_address_number" name="billing_address_number" class="saw-input"
                               value="<?php echo esc_attr($item['billing_address_number'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="saw-address-city-zip">
                    <div class="saw-form-group">
                        <label for="billing_address_city" class="saw-label"><?php echo esc_html__('Město', 'saw-visitors'); ?></label>
                        <input type="text" id="billing_address_city" name="billing_address_city" class="saw-input"
                               value="<?php echo esc_attr($item['billing_address_city'] ?? ''); ?>">
                    </div>
                    
                    <div class="saw-form-group">
                        <label for="billing_address_zip" class="saw-label"><?php echo esc_html__('PSČ', 'saw-visitors'); ?></label>
                        <input type="text" id="billing_address_zip" name="billing_address_zip" class="saw-input"
                               pattern="\d{3}\s?\d{2}" placeholder="123 45"
                               value="<?php echo esc_attr($item['billing_address_zip'] ?? ''); ?>">
                    </div>
                </div>
            </div>
        </details>
        
        <!-- SETTINGS -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-admin-settings"></span>
                <strong><?php echo esc_html__('Nastavení', 'saw-visitors'); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="admin_language_default" class="saw-label"><?php echo esc_html__('Jazyk administrace', 'saw-visitors'); ?></label>
                        <select id="admin_language_default" name="admin_language_default" class="saw-input">
                            <option value="cs" <?php selected($item['admin_language_default'] ?? 'cs', 'cs'); ?>><?php echo esc_html__('Čeština', 'saw-visitors'); ?></option>
                            <option value="en" <?php selected($item['admin_language_default'] ?? '', 'en'); ?>><?php echo esc_html__('English', 'saw-visitors'); ?></option>
                            <option value="de" <?php selected($item['admin_language_default'] ?? '', 'de'); ?>><?php echo esc_html__('Deutsch', 'saw-visitors'); ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="notes" class="saw-label"><?php echo esc_html__('Poznámky', 'saw-visitors'); ?></label>
                        <textarea id="notes" name="notes" class="saw-textarea" rows="4"><?php echo esc_textarea($item['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </details>
        
        <!-- ACTIONS -->
        <div class="saw-form-actions">
            <button type="submit" class="saw-button saw-button-primary">
                <span class="dashicons dashicons-yes"></span>
                <?php echo $is_edit ? esc_html__('Uložit změny', 'saw-visitors') : esc_html__('Vytvořit zákazníka', 'saw-visitors'); ?>
            </button>
            
            <?php if (!$in_sidebar): ?>
            <a href="<?php echo esc_url(home_url('/admin/settings/customers/')); ?>" class="saw-button saw-button-secondary">
                <span class="dashicons dashicons-no-alt"></span>
                <?php echo esc_html__('Zrušit', 'saw-visitors'); ?>
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>