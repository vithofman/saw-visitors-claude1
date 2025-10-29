<?php
/**
 * Formulář pro vytvoření/úpravu zákazníka
 * 
 * @package SAW_Visitors
 * @var bool $is_edit
 * @var array $customer
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$account_types = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}saw_account_types ORDER BY display_name ASC", ARRAY_A);
?>

<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title"><?php echo $is_edit ? 'Upravit zákazníka' : 'Nový zákazník'; ?></h1>
        <a href="/admin/settings/customers/" class="saw-button saw-button-secondary">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                <path d="M12.5 15L7.5 10L12.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Zpět
        </a>
    </div>
</div>

<div class="saw-customer-form-container">
    <form method="POST" enctype="multipart/form-data" class="saw-customer-form">
        <?php wp_nonce_field('saw_customer_form', 'saw_customer_nonce'); ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($customer['id']); ?>">
        <?php endif; ?>

        <!-- Základní informace -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-generic"></span>
                <strong>Základní informace</strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="name" class="saw-label saw-required">Název zákazníka</label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            class="saw-input" 
                            value="<?php echo esc_attr($customer['name'] ?? ''); ?>" 
                            required
                            placeholder="Název firmy nebo jméno zákazníka"
                        >
                    </div>
                </div>

                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="ico" class="saw-label">IČO</label>
                        <input 
                            type="text" 
                            id="ico" 
                            name="ico" 
                            class="saw-input" 
                            value="<?php echo esc_attr($customer['ico'] ?? ''); ?>"
                            placeholder="12345678"
                            pattern="\d{6,12}"
                        >
                        <span class="saw-help-text">6-12 číslic</span>
                    </div>

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
                    </div>
                </div>

                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="account_type_id" class="saw-label">Typ účtu</label>
                        <select id="account_type_id" name="account_type_id" class="saw-input">
                            <option value="">-- Vyberte typ --</option>
                            <?php foreach ($account_types as $type): ?>
                                <option 
                                    value="<?php echo esc_attr($type['id']); ?>"
                                    <?php selected($customer['account_type_id'] ?? '', $type['id']); ?>
                                >
                                    <?php echo esc_html($type['display_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="saw-form-group saw-col-6">
                        <label for="status" class="saw-label">Status</label>
                        <select id="status" name="status" class="saw-input">
                            <option value="potential" <?php selected($customer['status'] ?? 'potential', 'potential'); ?>>Potenciální</option>
                            <option value="active" <?php selected($customer['status'] ?? '', 'active'); ?>>Aktivní</option>
                            <option value="inactive" <?php selected($customer['status'] ?? '', 'inactive'); ?>>Neaktivní</option>
                        </select>
                    </div>
                </div>
            </div>
        </details>

        <!-- Branding -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-admin-appearance"></span>
                <strong>Branding</strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-branding-grid">
                    <!-- Logo preview -->
                    <div class="saw-logo-column">
                        <div class="saw-logo-preview-current">
                            <span class="saw-logo-preview-label">Aktuální logo</span>
                            <?php if (!empty($customer['logo_url_full'])): ?>
                                <img src="<?php echo esc_url($customer['logo_url_full']); ?>" alt="Logo">
                                <button type="button" class="saw-remove-logo-btn" onclick="removeLogoPreview(this)">
                                    <span class="dashicons dashicons-no-alt"></span>
                                    Odstranit
                                </button>
                            <?php else: ?>
                                <span style="color: #999; font-size: 13px;">Žádné logo</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Upload -->
                    <div class="saw-upload-column">
                        <div class="saw-file-upload-wrapper">
                            <input 
                                type="file" 
                                id="logo" 
                                name="logo" 
                                class="saw-file-input" 
                                accept="image/*"
                                onchange="previewLogo(this)"
                            >
                            <label for="logo" class="saw-file-label">
                                <span class="dashicons dashicons-upload"></span>
                                Nahrát nové logo
                            </label>
                            <div class="saw-file-info">
                                <span class="dashicons dashicons-info"></span>
                                Max 5 MB, formáty: JPG, PNG, SVG, WEBP
                            </div>
                        </div>
                    </div>

                    <!-- Barva -->
                    <div class="saw-color-column">
                        <div class="saw-color-section">
                            <label class="saw-label">Primární barva</label>
                            <div class="saw-color-picker-wrapper">
                                <input 
                                    type="color" 
                                    id="primary_color" 
                                    name="primary_color" 
                                    class="saw-color-picker" 
                                    value="<?php echo esc_attr($customer['primary_color'] ?? '#1e40af'); ?>"
                                    onchange="updateColorValue(this)"
                                >
                                <input 
                                    type="text" 
                                    class="saw-color-value" 
                                    value="<?php echo esc_attr($customer['primary_color'] ?? '#1e40af'); ?>"
                                    readonly
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </details>

        <!-- Provozní adresa -->
        <details class="saw-form-section">
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
                            placeholder="Hlavní"
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
                            placeholder="123"
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
                            placeholder="110 00"
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
                        >
                    </div>
                </div>
            </div>
        </details>

        <!-- Fakturační adresa -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-money-alt"></span>
                <strong>Fakturační adresa</strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-8">
                        <label for="billing_address_street" class="saw-label">Ulice</label>
                        <input 
                            type="text" 
                            id="billing_address_street" 
                            name="billing_address_street" 
                            class="saw-input" 
                            value="<?php echo esc_attr($customer['billing_address_street'] ?? ''); ?>"
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
                        >
                    </div>
                </div>
            </div>
        </details>

        <!-- Kontaktní údaje -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-email"></span>
                <strong>Kontaktní údaje</strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="contact_person" class="saw-label">Kontaktní osoba</label>
                        <input 
                            type="text" 
                            id="contact_person" 
                            name="contact_person" 
                            class="saw-input" 
                            value="<?php echo esc_attr($customer['contact_person'] ?? ''); ?>"
                            placeholder="Jméno a příjmení"
                        >
                    </div>

                    <div class="saw-form-group saw-col-6">
                        <label for="contact_position" class="saw-label">Pozice</label>
                        <input 
                            type="text" 
                            id="contact_position" 
                            name="contact_position" 
                            class="saw-input" 
                            value="<?php echo esc_attr($customer['contact_position'] ?? ''); ?>"
                            placeholder="Jednatel, Ředitel..."
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
                            placeholder="email@example.com"
                        >
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
                        <label for="website" class="saw-label">Web</label>
                        <input 
                            type="url" 
                            id="website" 
                            name="website" 
                            class="saw-input" 
                            value="<?php echo esc_attr($customer['website'] ?? ''); ?>"
                            placeholder="https://example.com"
                        >
                    </div>
                </div>
            </div>
        </details>

        <!-- Dodatečné informace -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-welcome-write-blog"></span>
                <strong>Dodatečné informace</strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="acquisition_source" class="saw-label">Zdroj akvizice</label>
                        <input 
                            type="text" 
                            id="acquisition_source" 
                            name="acquisition_source" 
                            class="saw-input" 
                            value="<?php echo esc_attr($customer['acquisition_source'] ?? ''); ?>"
                            placeholder="Doporučení, Web, Akce..."
                        >
                    </div>

                    <div class="saw-form-group saw-col-6">
                        <label for="subscription_type" class="saw-label">Typ předplatného</label>
                        <select id="subscription_type" name="subscription_type" class="saw-input">
                            <option value="monthly" <?php selected($customer['subscription_type'] ?? 'monthly', 'monthly'); ?>>Měsíční</option>
                            <option value="yearly" <?php selected($customer['subscription_type'] ?? '', 'yearly'); ?>>Roční</option>
                        </select>
                    </div>
                </div>

                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="admin_language_default" class="saw-label">Výchozí jazyk administrace</label>
                        <select id="admin_language_default" name="admin_language_default" class="saw-input">
                            <option value="cs" <?php selected($customer['admin_language_default'] ?? 'cs', 'cs'); ?>>Čeština</option>
                            <option value="en" <?php selected($customer['admin_language_default'] ?? '', 'en'); ?>>English</option>
                            <option value="sk" <?php selected($customer['admin_language_default'] ?? '', 'sk'); ?>>Slovenčina</option>
                        </select>
                    </div>
                </div>

                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="notes" class="saw-label">Poznámky</label>
                        <textarea 
                            id="notes" 
                            name="notes" 
                            class="saw-textarea" 
                            rows="4"
                            placeholder="Interní poznámky k zákazníkovi..."
                        ><?php echo esc_textarea($customer['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </details>

        <!-- Tlačítka -->
        <div class="saw-form-actions">
            <a href="/admin/settings/customers/" class="saw-button saw-button-secondary saw-button-large">
                Zrušit
            </a>
            <button type="submit" class="saw-button saw-button-primary saw-button-large">
                <?php echo $is_edit ? 'Uložit změny' : 'Vytvořit zákazníka'; ?>
            </button>
        </div>
    </form>
</div>

<script>
function updateColorValue(input) {
    const valueInput = input.parentElement.querySelector('.saw-color-value');
    if (valueInput) {
        valueInput.value = input.value;
    }
}

function previewLogo(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.querySelector('.saw-logo-preview-current');
            if (preview) {
                preview.innerHTML = '<span class="saw-logo-preview-label">Náhled</span><img src="' + e.target.result + '" alt="Preview">';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function removeLogoPreview(btn) {
    const preview = btn.closest('.saw-logo-preview-current');
    if (preview) {
        preview.innerHTML = '<span class="saw-logo-preview-label">Aktuální logo</span><span style="color: #999; font-size: 13px;">Žádné logo</span>';
    }
    document.getElementById('logo').value = '';
}
</script>