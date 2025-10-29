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
        <a href="/admin/settings/customers/" class="saw-btn saw-btn-secondary">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                <path d="M12.5 15L7.5 10L12.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Zpět
        </a>
        <h1 class="saw-page-title"><?php echo $is_edit ? 'Upravit zákazníka' : 'Nový zákazník'; ?></h1>
    </div>
</div>

<div class="saw-customer-form-container">
    <form method="POST" enctype="multipart/form-data" class="saw-customer-form">
        <?php wp_nonce_field('saw_customer_form', 'saw_customer_nonce'); ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($customer['id']); ?>">
        <?php endif; ?>

        <div class="saw-form-section">
            <div class="saw-form-section-header">
                <h2>Základní informace</h2>
            </div>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="name" class="saw-form-label required">Název zákazníka</label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            class="saw-form-input" 
                            value="<?php echo esc_attr($customer['name'] ?? ''); ?>" 
                            required
                            placeholder="Název firmy nebo jméno zákazníka"
                        >
                    </div>
                </div>

                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="ico" class="saw-form-label">IČO</label>
                        <input 
                            type="text" 
                            id="ico" 
                            name="ico" 
                            class="saw-form-input" 
                            value="<?php echo esc_attr($customer['ico'] ?? ''); ?>"
                            placeholder="12345678"
                            pattern="\d{6,12}"
                        >
                        <span class="saw-form-help">6-12 číslic</span>
                    </div>

                    <div class="saw-form-group">
                        <label for="dic" class="saw-form-label">DIČ</label>
                        <input 
                            type="text" 
                            id="dic" 
                            name="dic" 
                            class="saw-form-input" 
                            value="<?php echo esc_attr($customer['dic'] ?? ''); ?>"
                            placeholder="CZ12345678"
                        >
                    </div>
                </div>

                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="account_type_id" class="saw-form-label">Typ účtu</label>
                        <select id="account_type_id" name="account_type_id" class="saw-form-select">
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

                    <div class="saw-form-group">
                        <label for="status" class="saw-form-label">Status</label>
                        <select id="status" name="status" class="saw-form-select">
                            <option value="potential" <?php selected($customer['status'] ?? 'potential', 'potential'); ?>>Potenciální</option>
                            <option value="active" <?php selected($customer['status'] ?? '', 'active'); ?>>Aktivní</option>
                            <option value="inactive" <?php selected($customer['status'] ?? '', 'inactive'); ?>>Neaktivní</option>
                        </select>
                    </div>
                </div>

                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="primary_color" class="saw-form-label">Primární barva</label>
                        <input 
                            type="color" 
                            id="primary_color" 
                            name="primary_color" 
                            class="saw-form-color" 
                            value="<?php echo esc_attr($customer['primary_color'] ?? '#1e40af'); ?>"
                        >
                    </div>

                    <div class="saw-form-group">
                        <label for="logo" class="saw-form-label">Logo</label>
                        <input 
                            type="file" 
                            id="logo" 
                            name="logo" 
                            class="saw-form-input" 
                            accept="image/*"
                        >
                        <?php if (!empty($customer['logo_url_full'])): ?>
                            <div class="saw-logo-preview">
                                <img src="<?php echo esc_url($customer['logo_url_full']); ?>" alt="Logo" style="max-width: 200px; margin-top: 10px;">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="saw-form-section">
            <div class="saw-form-section-header">
                <h2>Provozní adresa</h2>
            </div>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="address_street" class="saw-form-label">Ulice</label>
                        <input 
                            type="text" 
                            id="address_street" 
                            name="address_street" 
                            class="saw-form-input" 
                            value="<?php echo esc_attr($customer['address_street'] ?? ''); ?>"
                            placeholder="Hlavní"
                        >
                    </div>

                    <div class="saw-form-group" style="max-width: 150px;">
                        <label for="address_number" class="saw-form-label">Číslo popisné</label>
                        <input 
                            type="text" 
                            id="address_number" 
                            name="address_number" 
                            class="saw-form-input" 
                            value="<?php echo esc_attr($customer['address_number'] ?? ''); ?>"
                            placeholder="123"
                        >
                    </div>
                </div>

                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="address_city" class="saw-form-label">Město</label>
                        <input 
                            type="text" 
                            id="address_city" 
                            name="address_city" 
                            class="saw-form-input" 
                            value="<?php echo esc_attr($customer['address_city'] ?? ''); ?>"
                            placeholder="Praha"
                        >
                    </div>

                    <div class="saw-form-group" style="max-width: 150px;">
                        <label for="address_zip" class="saw-form-label">PSČ</label>
                        <input 
                            type="text" 
                            id="address_zip" 
                            name="address_zip" 
                            class="saw-form-input" 
                            value="<?php echo esc_attr($customer['address_zip'] ?? ''); ?>"
                            placeholder="110 00"
                        >
                    </div>

                    <div class="saw-form-group">
                        <label for="address_country" class="saw-form-label">Země</label>
                        <input 
                            type="text" 
                            id="address_country" 
                            name="address_country" 
                            class="saw-form-input" 
                            value="<?php echo esc_attr($customer['address_country'] ?? 'Česká republika'); ?>"
                        >
                    </div>
                </div>
            </div>
        </div>

        <div class="saw-form-section saw-form-section-collapsible">
            <div class="saw-form-section-header" style="cursor: pointer;">
                <h2>Fakturační adresa</h2>
                <button type="button" class="saw-section-toggle">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
            <div class="saw-form-section-content" style="display: none;">
                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="billing_address_street" class="saw-form-label">Ulice</label>
                        <input 
                            type="text" 
                            id="billing_address_street" 
                            name="billing_address_street" 
                            class="saw-form-input" 
                            value="<?php echo esc_attr($customer['billing_address_street'] ?? ''); ?>"
                        >
                    </div>

                    <div class="saw-form-group" style="max-width: 150px;">
                        <label for="billing_address_number" class="saw-form-label">Číslo popisné</label>
                        <input 
                            type="text" 
                            id="billing_address_number" 
                            name="billing_address_number" 
                            class="saw-form-input" 
                            value="<?php echo esc_attr($customer['billing_address_number'] ?? ''); ?>"
                        >
                    </div>
                </div>

                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="billing_address_city" class="saw-form-label">Město</label>
                        <input 
                            type="text" 
                            id="billing_address_city" 
                            name="billing_address_city" 
                            class="saw-form-input" 
                            value="<?php echo esc_attr($customer['billing_address_city'] ?? ''); ?>"
                        >
                    </div>

                    <div class="saw-form-group" style="max-width: 150px;">
                        <label for="billing_address_zip" class="saw-form-label">PSČ</label>
                        <input 
                            type="text" 
                            id="billing_address_zip" 
                            name="billing_address_zip" 
                            class="saw-form-input" 
                            value="<?php echo esc_attr($customer['billing_address_zip'] ?? ''); ?>"
                        >
                    </div>

                    <div class="saw-form-group">
                        <label for="billing_address_country" class="saw-form-label">Země</label>
                        <input 
                            type="text" 
                            id="billing_address_country" 
                            name="billing_address_country" 
                            class="saw-form-input" 
                            value="<?php echo esc_attr($customer['billing_address_country'] ?? ''); ?>"
                        >
                    </div>
                </div>
            </div>
        </div>

        <div class="saw-form-section saw-form-section-collapsible">
            <div class="saw-form-section-header" style="cursor: pointer;">
                <h2>Kontaktní údaje</h2>
                <button type="button" class="saw-section-toggle">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
            <div class="saw-form-section-content" style="display: none;">
                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="contact_person" class="saw-form-label">Kontaktní osoba</label>
                        <input 
                            type="text" 
                            id="contact_person" 
                            name="contact_person" 
                            class="saw-form-input" 
                            value="<?php echo esc_attr($customer['contact_person'] ?? ''); ?>"
                            placeholder="Jméno a příjmení"
                        >
                    </div>

                    <div class="saw-form-group">
                        <label for="contact_position" class="saw-form-label">Pozice</label>
                        <input 
                            type="text" 
                            id="contact_position" 
                            name="contact_position" 
                            class="saw-form-input" 
                            value="<?php echo esc_attr($customer['contact_position'] ?? ''); ?>"
                            placeholder="Jednatel, Ředitel..."
                        >
                    </div>
                </div>

                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="contact_email" class="saw-form-label">Email</label>
                        <input 
                            type="email" 
                            id="contact_email" 
                            name="contact_email" 
                            class="saw-form-input" 
                            value="<?php echo esc_attr($customer['contact_email'] ?? ''); ?>"
                            placeholder="email@example.com"
                        >
                    </div>

                    <div class="saw-form-group">
                        <label for="contact_phone" class="saw-form-label">Telefon</label>
                        <input 
                            type="tel" 
                            id="contact_phone" 
                            name="contact_phone" 
                            class="saw-form-input" 
                            value="<?php echo esc_attr($customer['contact_phone'] ?? ''); ?>"
                            placeholder="+420 123 456 789"
                        >
                    </div>
                </div>

                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="website" class="saw-form-label">Web</label>
                        <input 
                            type="url" 
                            id="website" 
                            name="website" 
                            class="saw-form-input" 
                            value="<?php echo esc_attr($customer['website'] ?? ''); ?>"
                            placeholder="https://example.com"
                        >
                    </div>
                </div>
            </div>
        </div>

        <div class="saw-form-section saw-form-section-collapsible">
            <div class="saw-form-section-header" style="cursor: pointer;">
                <h2>Dodatečné informace</h2>
                <button type="button" class="saw-section-toggle">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
            <div class="saw-form-section-content" style="display: none;">
                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="acquisition_source" class="saw-form-label">Zdroj akvizice</label>
                        <input 
                            type="text" 
                            id="acquisition_source" 
                            name="acquisition_source" 
                            class="saw-form-input" 
                            value="<?php echo esc_attr($customer['acquisition_source'] ?? ''); ?>"
                            placeholder="Doporučení, Web, Akce..."
                        >
                    </div>

                    <div class="saw-form-group">
                        <label for="subscription_type" class="saw-form-label">Typ předplatného</label>
                        <select id="subscription_type" name="subscription_type" class="saw-form-select">
                            <option value="monthly" <?php selected($customer['subscription_type'] ?? 'monthly', 'monthly'); ?>>Měsíční</option>
                            <option value="yearly" <?php selected($customer['subscription_type'] ?? '', 'yearly'); ?>>Roční</option>
                        </select>
                    </div>
                </div>

                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="admin_language_default" class="saw-form-label">Výchozí jazyk administrace</label>
                        <select id="admin_language_default" name="admin_language_default" class="saw-form-select">
                            <option value="cs" <?php selected($customer['admin_language_default'] ?? 'cs', 'cs'); ?>>Čeština</option>
                            <option value="en" <?php selected($customer['admin_language_default'] ?? '', 'en'); ?>>English</option>
                            <option value="sk" <?php selected($customer['admin_language_default'] ?? '', 'sk'); ?>>Slovenčina</option>
                        </select>
                    </div>
                </div>

                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="notes" class="saw-form-label">Poznámky</label>
                        <textarea 
                            id="notes" 
                            name="notes" 
                            class="saw-form-textarea" 
                            rows="4"
                            placeholder="Interní poznámky k zákazníkovi..."
                        ><?php echo esc_textarea($customer['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="saw-form-actions">
            <a href="/admin/settings/customers/" class="saw-btn saw-btn-secondary">
                Zrušit
            </a>
            <button type="submit" class="saw-btn saw-btn-primary">
                <?php echo $is_edit ? 'Uložit změny' : 'Vytvořit zákazníka'; ?>
            </button>
        </div>
    </form>
</div>

<script>
(function() {
    document.querySelectorAll('.saw-form-section-collapsible .saw-form-section-header').forEach(function(header) {
        header.addEventListener('click', function(e) {
            if (e.target.closest('.saw-section-toggle')) {
                return;
            }
            const section = this.closest('.saw-form-section-collapsible');
            const content = section.querySelector('.saw-form-section-content');
            const toggle = section.querySelector('.saw-section-toggle');
            const isVisible = content.style.display !== 'none';
            
            content.style.display = isVisible ? 'none' : 'block';
            toggle.classList.toggle('active');
        });
    });
    
    document.querySelectorAll('.saw-section-toggle').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const section = this.closest('.saw-form-section-collapsible');
            const content = section.querySelector('.saw-form-section-content');
            const isVisible = content.style.display !== 'none';
            
            content.style.display = isVisible ? 'none' : 'block';
            this.classList.toggle('active');
        });
    });
})();
</script>