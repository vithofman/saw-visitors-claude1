<?php
/**
 * Customers Form Template
 * 
 * @package SAW_Visitors
 * @version 4.6.1 ENHANCED
 */

if (!defined('ABSPATH')) {
    exit;
}

$form_action = $is_edit 
    ? home_url('/admin/settings/customers/edit/' . $customer['id'] . '/')
    : home_url('/admin/settings/customers/new/');
?>

<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo $is_edit ? 'Upravit zákazníka' : 'Přidat nového zákazníka'; ?>
        </h1>
        <a href="<?php echo esc_url($back_url); ?>" class="saw-back-button">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            Zpět na seznam
        </a>
    </div>
</div>

<?php if (isset($_GET['message'])): ?>
    <div class="saw-alert <?php echo isset($_GET['message_type']) && $_GET['message_type'] === 'error' ? 'saw-alert-error' : ''; ?>">
        <?php echo esc_html(urldecode($_GET['message'])); ?>
        <button type="button" class="saw-alert-close">&times;</button>
    </div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url($form_action); ?>" enctype="multipart/form-data" class="saw-form" id="saw-customer-form">
    <?php wp_nonce_field('saw_customer_save', 'saw_customer_nonce'); ?>
    
    <!-- ========================================= -->
    <!-- SEKCE 1: ZÁKLADNÍ ÚDAJE -->
    <!-- ========================================= -->
    <details open class="saw-form-section">
        <summary>
            <span class="dashicons dashicons-businessman"></span>
            <strong>Základní údaje</strong>
        </summary>
        <div class="saw-form-section-content">
            <div class="saw-form-row">
                <div class="saw-form-group saw-col-8">
                    <label for="name" class="saw-label saw-required">Název zákazníka</label>
                    <input 
                        type="text" 
                        id="name" 
                        name="name" 
                        class="saw-input" 
                        value="<?php echo esc_attr($customer['name'] ?? ''); ?>" 
                        required
                        placeholder="např. ACME s.r.o."
                    >
                    <small class="saw-help-text">Zadejte název firmy nebo organizace</small>
                </div>
                
                <div class="saw-form-group saw-col-4">
                    <label for="ico" class="saw-label">IČO</label>
                    <input 
                        type="text" 
                        id="ico" 
                        name="ico" 
                        class="saw-input" 
                        value="<?php echo esc_attr($customer['ico'] ?? ''); ?>"
                        placeholder="12345678"
                        pattern="[0-9]{6,12}"
                    >
                    <small class="saw-help-text">6-12 číslic</small>
                </div>
            </div>
            
            <div class="saw-form-row">
                <div class="saw-form-group saw-col-6">
                    <label for="dic" class="saw-label">DIČ</label>
                    <input 
                        type="text" 
                        id="dic" 
                        name="dic" 
                        class="saw-input" 
                        value="<?php echo esc_attr($customer['dic'] ?? ''); ?>"
                        placeholder="CZ12345678"
                    >
                    <small class="saw-help-text">Daňové identifikační číslo</small>
                </div>
            </div>
        </div>
    </details>
    
    <!-- ========================================= -->
    <!-- SEKCE 2: PROVOZNÍ ADRESA -->
    <!-- ========================================= -->
    <details open class="saw-form-section">
        <summary>
            <span class="dashicons dashicons-location"></span>
            <strong>Provozní adresa</strong>
        </summary>
        <div class="saw-form-section-content">
            <div class="saw-form-row">
                <div class="saw-form-group saw-col-8">
                    <label for="address_street" class="saw-label">Ulice</label>
                    <input 
                        type="text" 
                        id="address_street" 
                        name="address_street" 
                        class="saw-input" 
                        value="<?php echo esc_attr($customer['address_street'] ?? ''); ?>"
                        placeholder="Karlovo náměstí"
                    >
                </div>
                
                <div class="saw-form-group saw-col-4">
                    <label for="address_number" class="saw-label">Číslo popisné</label>
                    <input 
                        type="text" 
                        id="address_number" 
                        name="address_number" 
                        class="saw-input" 
                        value="<?php echo esc_attr($customer['address_number'] ?? ''); ?>"
                        placeholder="123/45"
                    >
                </div>
            </div>
            
            <div class="saw-form-row">
                <div class="saw-form-group saw-col-6">
                    <label for="address_city" class="saw-label">Město</label>
                    <input 
                        type="text" 
                        id="address_city" 
                        name="address_city" 
                        class="saw-input" 
                        value="<?php echo esc_attr($customer['address_city'] ?? ''); ?>"
                        placeholder="Praha"
                    >
                </div>
                
                <div class="saw-form-group saw-col-3">
                    <label for="address_zip" class="saw-label">PSČ</label>
                    <input 
                        type="text" 
                        id="address_zip" 
                        name="address_zip" 
                        class="saw-input" 
                        value="<?php echo esc_attr($customer['address_zip'] ?? ''); ?>"
                        placeholder="120 00"
                    >
                </div>
                
                <div class="saw-form-group saw-col-3">
                    <label for="address_country" class="saw-label">Země</label>
                    <input 
                        type="text" 
                        id="address_country" 
                        name="address_country" 
                        class="saw-input" 
                        value="<?php echo esc_attr($customer['address_country'] ?? 'Česká republika'); ?>"
                        placeholder="Česká republika"
                    >
                </div>
            </div>
        </div>
    </details>
    
    <!-- ========================================= -->
    <!-- SEKCE 3: FAKTURAČNÍ ADRESA -->
    <!-- ========================================= -->
    <details class="saw-form-section" id="billing-section">
        <summary>
            <span class="dashicons dashicons-media-document"></span>
            <strong>Fakturační adresa</strong>
        </summary>
        <div class="saw-form-section-content">
            <div class="saw-form-group">
                <label>
                    <input 
                        type="checkbox" 
                        id="billing-different" 
                        name="billing_different"
                        <?php echo !empty($customer['billing_address_street']) ? 'checked' : ''; ?>
                    >
                    Fakturační adresa se liší od provozní adresy
                </label>
            </div>
            
            <div id="billing-fields" style="display: <?php echo !empty($customer['billing_address_street']) ? 'block' : 'none'; ?>;">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-8">
                        <label for="billing_address_street" class="saw-label">Ulice</label>
                        <input 
                            type="text" 
                            id="billing_address_street" 
                            name="billing_address_street" 
                            class="saw-input" 
                            value="<?php echo esc_attr($customer['billing_address_street'] ?? ''); ?>"
                            placeholder="Karlovo náměstí"
                        >
                    </div>
                    
                    <div class="saw-form-group saw-col-4">
                        <label for="billing_address_number" class="saw-label">Číslo popisné</label>
                        <input 
                            type="text" 
                            id="billing_address_number" 
                            name="billing_address_number" 
                            class="saw-input" 
                            value="<?php echo esc_attr($customer['billing_address_number'] ?? ''); ?>"
                            placeholder="123/45"
                        >
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="billing_address_city" class="saw-label">Město</label>
                        <input 
                            type="text" 
                            id="billing_address_city" 
                            name="billing_address_city" 
                            class="saw-input" 
                            value="<?php echo esc_attr($customer['billing_address_city'] ?? ''); ?>"
                            placeholder="Praha"
                        >
                    </div>
                    
                    <div class="saw-form-group saw-col-3">
                        <label for="billing_address_zip" class="saw-label">PSČ</label>
                        <input 
                            type="text" 
                            id="billing_address_zip" 
                            name="billing_address_zip" 
                            class="saw-input" 
                            value="<?php echo esc_attr($customer['billing_address_zip'] ?? ''); ?>"
                            placeholder="120 00"
                        >
                    </div>
                    
                    <div class="saw-form-group saw-col-3">
                        <label for="billing_address_country" class="saw-label">Země</label>
                        <input 
                            type="text" 
                            id="billing_address_country" 
                            name="billing_address_country" 
                            class="saw-input" 
                            value="<?php echo esc_attr($customer['billing_address_country'] ?? ''); ?>"
                            placeholder="Česká republika"
                        >
                    </div>
                </div>
            </div>
        </div>
    </details>
    
    <!-- ========================================= -->
    <!-- SEKCE 4: KONTAKTNÍ OSOBA -->
    <!-- ========================================= -->
    <details open class="saw-form-section">
        <summary>
            <span class="dashicons dashicons-admin-users"></span>
            <strong>Kontaktní osoba</strong>
        </summary>
        <div class="saw-form-section-content">
            <div class="saw-form-row">
                <div class="saw-form-group saw-col-6">
                    <label for="contact_person" class="saw-label">Jméno a příjmení</label>
                    <input 
                        type="text" 
                        id="contact_person" 
                        name="contact_person" 
                        class="saw-input" 
                        value="<?php echo esc_attr($customer['contact_person'] ?? ''); ?>"
                        placeholder="Jan Novák"
                    >
                </div>
                
                <div class="saw-form-group saw-col-6">
                    <label for="contact_position" class="saw-label">Funkce / Pozice</label>
                    <input 
                        type="text" 
                        id="contact_position" 
                        name="contact_position" 
                        class="saw-input" 
                        value="<?php echo esc_attr($customer['contact_position'] ?? ''); ?>"
                        placeholder="Jednatel"
                    >
                </div>
            </div>
            
            <div class="saw-form-row">
                <div class="saw-form-group saw-col-6">
                    <label for="contact_email" class="saw-label">Email</label>
                    <input 
                        type="email" 
                        id="contact_email" 
                        name="contact_email" 
                        class="saw-input" 
                        value="<?php echo esc_attr($customer['contact_email'] ?? ''); ?>"
                        placeholder="jan.novak@firma.cz"
                    >
                    <small class="saw-help-text">Hlavní kontaktní email</small>
                </div>
                
                <div class="saw-form-group saw-col-6">
                    <label for="contact_phone" class="saw-label">Telefon</label>
                    <input 
                        type="tel" 
                        id="contact_phone" 
                        name="contact_phone" 
                        class="saw-input" 
                        value="<?php echo esc_attr($customer['contact_phone'] ?? ''); ?>"
                        placeholder="+420 123 456 789"
                    >
                </div>
            </div>
            
            <div class="saw-form-row">
                <div class="saw-form-group">
                    <label for="website" class="saw-label">Webové stránky</label>
                    <input 
                        type="url" 
                        id="website" 
                        name="website" 
                        class="saw-input" 
                        value="<?php echo esc_attr($customer['website'] ?? ''); ?>"
                        placeholder="https://www.firma.cz"
                    >
                </div>
            </div>
        </div>
    </details>
    
    <!-- ========================================= -->
    <!-- SEKCE 5: OBCHODNÍ ÚDAJE -->
    <!-- ========================================= -->
    <details open class="saw-form-section">
        <summary>
            <span class="dashicons dashicons-chart-line"></span>
            <strong>Obchodní údaje</strong>
        </summary>
        <div class="saw-form-section-content">
            <div class="saw-form-row">
                <div class="saw-form-group saw-col-6">
                    <label for="account_type_id" class="saw-label">Typ účtu</label>
                    <select id="account_type_id" name="account_type_id" class="saw-input">
                        <option value="">-- Vyberte typ účtu --</option>
                        <?php foreach ($account_types as $id => $display_name): ?>
                            <option value="<?php echo esc_attr($id); ?>" <?php selected($customer['account_type_id'] ?? '', $id); ?>>
                                <?php echo esc_html($display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="saw-form-group saw-col-6">
                    <label for="status" class="saw-label">Status</label>
                    <select id="status" name="status" class="saw-input">
                        <?php foreach ($status_options as $value => $data): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($customer['status'] ?? 'potential', $value); ?>>
                                <?php echo esc_html($data['icon'] . ' ' . $data['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="saw-form-row">
                <div class="saw-form-group saw-col-6">
                    <label for="acquisition_source" class="saw-label">Zdroj akvizice</label>
                    <select id="acquisition_source" name="acquisition_source" class="saw-input">
                        <option value="">-- Vyberte zdroj --</option>
                        <?php foreach ($acquisition_options as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($customer['acquisition_source'] ?? '', $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="saw-form-group saw-col-6">
                    <label for="last_payment_date" class="saw-label">Datum poslední platby</label>
                    <input 
                        type="date" 
                        id="last_payment_date" 
                        name="last_payment_date" 
                        class="saw-input" 
                        value="<?php echo esc_attr($customer['last_payment_date'] ?? ''); ?>"
                    >
                </div>
            </div>
            
            <div class="saw-form-row">
                <div class="saw-form-group">
                    <label class="saw-label">Typ předplatného</label>
                    <div style="display: flex; gap: 20px; margin-top: 8px;">
                        <?php foreach ($subscription_options as $value => $label): ?>
                            <label style="display: flex; align-items: center; gap: 6px;">
                                <input 
                                    type="radio" 
                                    name="subscription_type" 
                                    value="<?php echo esc_attr($value); ?>"
                                    <?php checked($customer['subscription_type'] ?? 'monthly', $value); ?>
                                >
                                <?php echo esc_html($label); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </details>
    
    <!-- ========================================= -->
    <!-- SEKCE 6: BRANDING -->
    <!-- ========================================= -->
    <details open class="saw-form-section">
        <summary>
            <span class="dashicons dashicons-art"></span>
            <strong>Branding</strong>
        </summary>
        <div class="saw-form-section-content">
            <div class="saw-branding-grid">
                <!-- Logo preview -->
                <div class="saw-logo-column">
                    <div class="saw-form-group">
                        <label class="saw-label">Aktuální logo</label>
                        <?php if (!empty($customer['logo_url_full'])): ?>
                            <div class="saw-logo-preview-current">
                                <p class="saw-logo-preview-label">Aktuální</p>
                                <img src="<?php echo esc_url($customer['logo_url_full']); ?>" alt="Logo">
                                <button type="button" class="saw-remove-logo-btn" id="remove-logo-btn">
                                    <span class="dashicons dashicons-trash"></span>
                                    Odebrat
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="saw-logo-preview-current">
                                <p class="saw-logo-preview-label">Žádné logo</p>
                                <span style="color: #9ca3af;">Logo nebylo nastaveno</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Upload -->
                <div class="saw-upload-column">
                    <div class="saw-form-group">
                        <label class="saw-label">Nahrát nové logo</label>
                        <div class="saw-file-upload-wrapper">
                            <input type="file" id="customer_logo" name="logo" accept="image/*" class="saw-file-input">
                            <label for="customer_logo" class="saw-file-label">
                                <span class="dashicons dashicons-upload"></span>
                                Vybrat soubor
                            </label>
                            <div class="saw-file-info">
                                <span class="dashicons dashicons-info"></span>
                                <span class="saw-file-name">JPG, PNG, GIF, WEBP, SVG - max 5 MB</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Color picker -->
                <div class="saw-color-column">
                    <div class="saw-form-group">
                        <label for="customer_primary_color_picker" class="saw-label">Primární barva</label>
                        <div class="saw-color-section">
                            <div class="saw-color-picker-wrapper">
                                <input 
                                    type="color" 
                                    id="customer_primary_color_picker" 
                                    class="saw-color-picker"
                                    value="<?php echo esc_attr($customer['primary_color'] ?? '#1e40af'); ?>"
                                >
                                <input 
                                    type="text" 
                                    id="customer_primary_color" 
                                    name="primary_color" 
                                    class="saw-color-value" 
                                    value="<?php echo esc_attr($customer['primary_color'] ?? '#1e40af'); ?>"
                                    pattern="^#[0-9A-Fa-f]{6}$"
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </details>
    
    <!-- ========================================= -->
    <!-- SEKCE 7: NASTAVENÍ -->
    <!-- ========================================= -->
    <details class="saw-form-section">
        <summary>
            <span class="dashicons dashicons-admin-settings"></span>
            <strong>Nastavení</strong>
        </summary>
        <div class="saw-form-section-content">
            <div class="saw-form-row">
                <div class="saw-form-group">
                    <label class="saw-label">Výchozí jazyk administrace</label>
                    <div style="display: flex; gap: 20px; margin-top: 8px;">
                        <?php foreach ($language_options as $value => $label): ?>
                            <label style="display: flex; align-items: center; gap: 6px;">
                                <input 
                                    type="radio" 
                                    name="admin_language_default" 
                                    value="<?php echo esc_attr($value); ?>"
                                    <?php checked($customer['admin_language_default'] ?? 'cs', $value); ?>
                                >
                                <?php echo esc_html($label); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="saw-form-row">
                <div class="saw-form-group">
                    <label for="notes" class="saw-label">Interní poznámky</label>
                    <textarea 
                        id="notes" 
                        name="notes" 
                        class="saw-textarea" 
                        rows="5"
                        placeholder="Interní poznámky viditelné pouze administrátorům..."
                    ><?php echo esc_textarea($customer['notes'] ?? ''); ?></textarea>
                    <small class="saw-help-text">Poznámky neviditelné pro zákazníka</small>
                </div>
            </div>
        </div>
    </details>
    
    <!-- ========================================= -->
    <!-- TLAČÍTKA -->
    <!-- ========================================= -->
    <div class="saw-form-actions">
        <button type="submit" class="saw-button saw-button-primary saw-button-large">
            <span class="dashicons dashicons-yes"></span>
            <?php echo $is_edit ? 'Uložit změny' : 'Vytvořit zákazníka'; ?>
        </button>
        <a href="<?php echo esc_url($back_url); ?>" class="saw-button saw-button-secondary saw-button-large">
            <span class="dashicons dashicons-no-alt"></span>
            Zrušit
        </a>
    </div>
</form>

<script>
(function($) {
    'use strict';
    
    // Toggle fakturační adresy
    $('#billing-different').on('change', function() {
        $('#billing-fields').slideToggle(200);
    });
    
    // Close alerts
    $('.saw-alert-close').on('click', function() {
        $(this).parent().fadeOut(200);
    });
    
})(jQuery);
</script>