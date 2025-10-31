<?php
/**
 * Customers Form Template
 * 
 * Create/Edit formulář pro zákazníky
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 * @since   4.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_edit = !empty($item);
$item = $item ?? [];
?>

<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo $is_edit ? 'Upravit zákazníka' : 'Nový zákazník'; ?>
        </h1>
        <a href="<?php echo home_url('/admin/settings/customers/'); ?>" class="saw-back-button">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            Zpět na seznam
        </a>
    </div>
</div>

<div class="saw-form-container">
    <form method="post" enctype="multipart/form-data" class="saw-customer-form">
        <?php wp_nonce_field('saw_customers_form', 'saw_nonce'); ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
        <?php endif; ?>
        
        <!-- ZÁKLADNÍ INFORMACE -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-generic"></span>
                <strong>Základní informace</strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-8">
                        <label for="name" class="saw-label saw-required">Název společnosti</label>
                        <input type="text" id="name" name="name" class="saw-input"
                               value="<?php echo esc_attr($item['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="saw-form-group saw-col-4">
                        <label for="status" class="saw-label saw-required">Status</label>
                        <select id="status" name="status" class="saw-input" required>
                            <option value="potential" <?php selected($item['status'] ?? '', 'potential'); ?>>Potenciální</option>
                            <option value="active" <?php selected($item['status'] ?? '', 'active'); ?>>Aktivní</option>
                            <option value="inactive" <?php selected($item['status'] ?? '', 'inactive'); ?>>Neaktivní</option>
                        </select>
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-4">
                        <label for="ico" class="saw-label">IČO</label>
                        <input type="text" id="ico" name="ico" class="saw-input"
                               pattern="\d{8}" placeholder="12345678"
                               value="<?php echo esc_attr($item['ico'] ?? ''); ?>">
                        <span class="saw-help-text">8 číslic</span>
                    </div>
                    
                    <div class="saw-form-group saw-col-4">
                        <label for="dic" class="saw-label">DIČ</label>
                        <input type="text" id="dic" name="dic" class="saw-input"
                               placeholder="CZ12345678"
                               value="<?php echo esc_attr($item['dic'] ?? ''); ?>">
                    </div>
                    
                    <div class="saw-form-group saw-col-4">
                        <label for="subscription_type" class="saw-label">Typ předplatného</label>
                        <select id="subscription_type" name="subscription_type" class="saw-input">
                            <option value="free" <?php selected($item['subscription_type'] ?? 'free', 'free'); ?>>Zdarma</option>
                            <option value="basic" <?php selected($item['subscription_type'] ?? '', 'basic'); ?>>Basic</option>
                            <option value="pro" <?php selected($item['subscription_type'] ?? '', 'pro'); ?>>Pro</option>
                            <option value="enterprise" <?php selected($item['subscription_type'] ?? '', 'enterprise'); ?>>Enterprise</option>
                        </select>
                    </div>
                </div>
            </div>
        </details>
        
        <!-- BRANDING -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-art"></span>
                <strong>Branding a design</strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-branding-grid">
                    <!-- Logo Preview -->
                    <div class="saw-logo-column">
                        <label class="saw-label">Současné logo</label>
                        <div class="saw-logo-preview-current">
                            <?php if (!empty($item['logo_url'])): ?>
                                <img src="<?php echo esc_url($item['logo_url']); ?>" alt="Logo">
                            <?php else: ?>
                                <span class="saw-logo-placeholder">Žádné logo</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Logo Upload -->
                    <div class="saw-upload-column">
                        <label for="logo" class="saw-label">Nahrát nové logo</label>
                        <input type="file" id="logo" name="logo" class="saw-input"
                               accept="image/jpeg,image/png,image/gif">
                        <span class="saw-help-text">Max 2MB, formáty: JPG, PNG, GIF</span>
                    </div>
                    
                    <!-- Primary Color -->
                    <div class="saw-color-column">
                        <label for="primary_color" class="saw-label">Hlavní barva</label>
                        <div class="saw-color-picker-wrapper">
                            <input type="color" id="primary_color" name="primary_color" class="saw-color-picker"
                                   value="<?php echo esc_attr($item['primary_color'] ?? '#1e40af'); ?>">
                            <input type="text" id="primary_color_value" class="saw-color-value"
                                   value="<?php echo esc_attr($item['primary_color'] ?? '#1e40af'); ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>
        </details>
        
        <!-- KONTAKTNÍ ÚDAJE -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-email"></span>
                <strong>Kontaktní údaje</strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="contact_person" class="saw-label">Kontaktní osoba</label>
                        <input type="text" id="contact_person" name="contact_person" class="saw-input"
                               value="<?php echo esc_attr($item['contact_person'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="contact_email" class="saw-label">Email</label>
                        <input type="email" id="contact_email" name="contact_email" class="saw-input"
                               value="<?php echo esc_attr($item['contact_email'] ?? ''); ?>">
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="contact_phone" class="saw-label">Telefon</label>
                        <input type="text" id="contact_phone" name="contact_phone" class="saw-input"
                               value="<?php echo esc_attr($item['contact_phone'] ?? ''); ?>">
                    </div>
                </div>
            </div>
        </details>
        
        <!-- PROVOZNÍ ADRESA -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-location"></span>
                <strong>Provozní adresa</strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-address-fields">
                    <div class="saw-form-group">
                        <label for="address_street" class="saw-label">Ulice</label>
                        <input type="text" id="address_street" name="address_street" class="saw-input"
                               value="<?php echo esc_attr($item['address_street'] ?? ''); ?>">
                    </div>
                    
                    <div class="saw-form-group">
                        <label for="address_number" class="saw-label">Číslo popisné</label>
                        <input type="text" id="address_number" name="address_number" class="saw-input"
                               value="<?php echo esc_attr($item['address_number'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="saw-address-city-zip">
                    <div class="saw-form-group">
                        <label for="address_city" class="saw-label">Město</label>
                        <input type="text" id="address_city" name="address_city" class="saw-input"
                               value="<?php echo esc_attr($item['address_city'] ?? ''); ?>">
                    </div>
                    
                    <div class="saw-form-group">
                        <label for="address_zip" class="saw-label">PSČ</label>
                        <input type="text" id="address_zip" name="address_zip" class="saw-input"
                               pattern="\d{3}\s?\d{2}" placeholder="123 45"
                               value="<?php echo esc_attr($item['address_zip'] ?? ''); ?>">
                    </div>
                </div>
            </div>
        </details>
        
        <!-- FAKTURAČNÍ ADRESA -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-media-document"></span>
                <strong>Fakturační adresa</strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-address-fields">
                    <div class="saw-form-group">
                        <label for="billing_address_street" class="saw-label">Ulice</label>
                        <input type="text" id="billing_address_street" name="billing_address_street" class="saw-input"
                               value="<?php echo esc_attr($item['billing_address_street'] ?? ''); ?>">
                    </div>
                    
                    <div class="saw-form-group">
                        <label for="billing_address_number" class="saw-label">Číslo popisné</label>
                        <input type="text" id="billing_address_number" name="billing_address_number" class="saw-input"
                               value="<?php echo esc_attr($item['billing_address_number'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="saw-address-city-zip">
                    <div class="saw-form-group">
                        <label for="billing_address_city" class="saw-label">Město</label>
                        <input type="text" id="billing_address_city" name="billing_address_city" class="saw-input"
                               value="<?php echo esc_attr($item['billing_address_city'] ?? ''); ?>">
                    </div>
                    
                    <div class="saw-form-group">
                        <label for="billing_address_zip" class="saw-label">PSČ</label>
                        <input type="text" id="billing_address_zip" name="billing_address_zip" class="saw-input"
                               pattern="\d{3}\s?\d{2}" placeholder="123 45"
                               value="<?php echo esc_attr($item['billing_address_zip'] ?? ''); ?>">
                    </div>
                </div>
            </div>
        </details>
        
        <!-- NASTAVENÍ -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-admin-settings"></span>
                <strong>Nastavení</strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="admin_language_default" class="saw-label">Jazyk administrace</label>
                        <select id="admin_language_default" name="admin_language_default" class="saw-input">
                            <option value="cs" <?php selected($item['admin_language_default'] ?? 'cs', 'cs'); ?>>Čeština</option>
                            <option value="en" <?php selected($item['admin_language_default'] ?? '', 'en'); ?>>English</option>
                            <option value="de" <?php selected($item['admin_language_default'] ?? '', 'de'); ?>>Deutsch</option>
                        </select>
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="notes" class="saw-label">Poznámky</label>
                        <textarea id="notes" name="notes" class="saw-textarea" rows="4"><?php echo esc_textarea($item['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </details>
        
        <!-- ACTIONS -->
        <div class="saw-form-actions">
            <button type="submit" class="saw-button saw-button-primary">
                <span class="dashicons dashicons-yes"></span>
                <?php echo $is_edit ? 'Uložit změny' : 'Vytvořit zákazníka'; ?>
            </button>
            <a href="<?php echo home_url('/admin/settings/customers/'); ?>" class="saw-button saw-button-secondary">
                <span class="dashicons dashicons-no-alt"></span>
                Zrušit
            </a>
        </div>
    </form>
</div>