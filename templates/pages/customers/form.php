<?php
/**
 * Customers Form Template - ENHANCED VERSION WITH GRID LAYOUT
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
        <a href="<?php echo esc_url(home_url('/admin/settings/customers/')); ?>" class="saw-back-button">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            Zpět na seznam
        </a>
    </div>
</div>

<?php if (isset($_SESSION['saw_customer_error'])): ?>
    <div class="saw-alert saw-alert-error">
        <?php echo esc_html($_SESSION['saw_customer_error']); ?>
        <button type="button" class="saw-alert-close">&times;</button>
    </div>
    <?php unset($_SESSION['saw_customer_error']); ?>
<?php endif; ?>

<form method="post" action="<?php echo esc_url($form_action); ?>" enctype="multipart/form-data" class="saw-form" id="saw-customer-form">
    <?php wp_nonce_field('saw_customer_form', 'saw_customer_nonce'); ?>
    
    <!-- ZÁKLADNÍ INFORMACE -->
    <div class="saw-card">
        <div class="saw-card-header">
            <h2 class="saw-card-title">📋 Základní informace</h2>
        </div>
        <div class="saw-card-body">
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
            
            <!-- ✨ NOVÉ: Adresa a poznámky vedle sebe -->
            <div class="saw-form-row">
                <div class="saw-form-group saw-col-6">
                    <label for="address" class="saw-label">Adresa</label>
                    <textarea 
                        id="address" 
                        name="address" 
                        class="saw-textarea" 
                        rows="5"
                        placeholder="např. Karlovo náměstí 123, Praha 2, 120 00"
                    ><?php echo esc_textarea($customer['address'] ?? ''); ?></textarea>
                    <small class="saw-help-text">Fyzická adresa sídla společnosti</small>
                </div>
                
                <div class="saw-form-group saw-col-6">
                    <label for="notes" class="saw-label">Interní poznámky</label>
                    <textarea 
                        id="notes" 
                        name="notes" 
                        class="saw-textarea" 
                        rows="5"
                        placeholder="Interní poznámky, kontaktní informace, zvláštnosti..."
                    ><?php echo esc_textarea($customer['notes'] ?? ''); ?></textarea>
                    <small class="saw-help-text">Poznámky viditelné pouze administrátorům (neviditelné pro zákazníka)</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- BRANDING -->
    <div class="saw-card">
        <div class="saw-card-header">
            <h2 class="saw-card-title">🎨 Branding a vzhled</h2>
        </div>
        <div class="saw-card-body">
            
            <div class="saw-branding-grid">
                
                <!-- COLUMN 1: LOGO PREVIEW -->
                <div class="saw-logo-column">
                    <label class="saw-label">Logo</label>
                    
                    <!-- Aktuální logo (pokud existuje) -->
                    <?php if ($is_edit && !empty($customer['logo_url_full'])): ?>
                        <div class="saw-logo-preview-current" id="current-logo-wrapper">
                            <span class="saw-logo-preview-label">Současné</span>
                            <img src="<?php echo esc_url($customer['logo_url_full']); ?>" alt="Logo" id="current-logo">
                            <button type="button" class="saw-remove-logo-btn" id="remove-current-logo" title="Smazat logo">
                                <span class="dashicons dashicons-trash"></span>
                                Smazat
                            </button>
                            <input type="hidden" name="remove_logo" id="remove_logo_input" value="0">
                        </div>
                    <?php endif; ?>
                    
                    <!-- Náhled nového loga -->
                    <div class="saw-logo-new-preview" id="new-logo-preview" style="display: none;">
                        <span class="preview-label">Nové logo</span>
                        <img src="" alt="Náhled" id="new-logo-img">
                        <button type="button" class="saw-remove-preview-btn" id="remove-new-preview">
                            ✕
                        </button>
                    </div>
                </div>
                
                <!-- COLUMN 2: UPLOAD CONTROLS -->
                <div class="saw-upload-column">
                    <label class="saw-label">Nahrát logo</label>
                    
                    <div class="saw-file-upload-wrapper">
                        <input 
                            type="file" 
                            id="logo" 
                            name="logo" 
                            class="saw-file-input" 
                            accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml"
                        >
                        <label for="logo" class="saw-file-label">
                            <span class="dashicons dashicons-upload"></span>
                            Vybrat soubor
                        </label>
                        <span class="saw-file-info" id="file-info">
                            <span class="dashicons dashicons-info"></span>
                            Žádný soubor
                        </span>
                    </div>
                    
                    <small class="saw-help-text">
                        JPG, PNG, GIF, WebP, SVG<br>
                        Max. 5 MB
                    </small>
                </div>
                
                <!-- COLUMN 3: COLOR PICKER -->
                <div class="saw-color-column">
                    <label for="primary_color" class="saw-label">Primární barva</label>
                    <div class="saw-color-section">
                        <div class="saw-color-picker-wrapper">
                            <input 
                                type="color" 
                                id="primary_color" 
                                name="primary_color" 
                                class="saw-color-picker" 
                                value="<?php echo esc_attr($customer['primary_color'] ?? '#1e40af'); ?>"
                            >
                            <input 
                                type="text" 
                                class="saw-color-value" 
                                id="color_value" 
                                value="<?php echo esc_attr($customer['primary_color'] ?? '#1e40af'); ?>" 
                                pattern="^#[0-9A-Fa-f]{6}$"
                                maxlength="7"
                            >
                        </div>
                        <small class="saw-help-text">Barva v rozhraní</small>
                    </div>
                </div>
                
            </div>
            
        </div>
    </div>
    
    <!-- ACTIONS -->
    <div class="saw-form-actions">
        <button type="submit" class="saw-button saw-button-primary saw-button-large">
            <?php if ($is_edit): ?>
                💾 Uložit změny
            <?php else: ?>
                ➕ Vytvořit zákazníka
            <?php endif; ?>
        </button>
        <a href="<?php echo esc_url(home_url('/admin/settings/customers/')); ?>" class="saw-button saw-button-secondary saw-button-large">
            ❌ Zrušit
        </a>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ===================================================================
    // LOGO UPLOAD HANDLING
    // ===================================================================
    
    const logoInput = document.getElementById('logo');
    const fileInfo = document.getElementById('file-info');
    const newPreview = document.getElementById('new-logo-preview');
    const newLogoImg = document.getElementById('new-logo-img');
    const currentLogoWrapper = document.getElementById('current-logo-wrapper');
    const currentLogo = document.getElementById('current-logo');
    
    // Změna souboru
    if (logoInput) {
        logoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            
            if (file) {
                // Validace typu
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
                if (!allowedTypes.includes(file.type)) {
                    alert('❌ Nepovolený typ souboru. Použijte JPG, PNG, GIF, WebP nebo SVG.');
                    logoInput.value = '';
                    return;
                }
                
                // Validace velikosti (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('❌ Soubor je příliš velký. Maximální velikost je 5 MB.');
                    logoInput.value = '';
                    return;
                }
                
                // Update file info
                fileInfo.innerHTML = '<span class="dashicons dashicons-yes"></span> ' + file.name;
                fileInfo.classList.add('has-file');
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    newLogoImg.src = e.target.result;
                    newPreview.style.display = 'block';
                    
                    // Dim current logo
                    if (currentLogo) {
                        currentLogo.style.opacity = '0.3';
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Zrušit náhled nového loga
    const removeNewPreviewBtn = document.getElementById('remove-new-preview');
    if (removeNewPreviewBtn) {
        removeNewPreviewBtn.addEventListener('click', function() {
            logoInput.value = '';
            fileInfo.innerHTML = '<span class="dashicons dashicons-info"></span> Žádný soubor nevybrán';
            fileInfo.classList.remove('has-file');
            newPreview.style.display = 'none';
            
            if (currentLogo) {
                currentLogo.style.opacity = '1';
            }
        });
    }
    
    // Smazat aktuální logo
    const removeCurrentLogoBtn = document.getElementById('remove-current-logo');
    const removeLogoInput = document.getElementById('remove_logo_input');
    
    if (removeCurrentLogoBtn) {
        removeCurrentLogoBtn.addEventListener('click', function() {
            if (confirm('⚠️ Opravdu chcete smazat aktuální logo?')) {
                removeLogoInput.value = '1';
                currentLogoWrapper.style.opacity = '0.3';
                currentLogoWrapper.style.pointerEvents = 'none';
                this.textContent = '✓ Logo bude smazáno';
                this.style.background = '#059669';
            }
        });
    }
    
    // ===================================================================
    // COLOR PICKER SYNC
    // ===================================================================
    
    const colorPicker = document.getElementById('primary_color');
    const colorValue = document.getElementById('color_value');
    
    if (colorPicker && colorValue) {
        // Color picker změna
        colorPicker.addEventListener('input', function() {
            const color = this.value.toUpperCase();
            colorValue.value = color;
        });
        
        // Text input změna
        colorValue.addEventListener('input', function() {
            const color = this.value.toUpperCase();
            const hexPattern = /^#[0-9A-F]{6}$/;
            
            if (hexPattern.test(color)) {
                colorPicker.value = color;
            }
        });
    }
    
    // ===================================================================
    // ALERT CLOSE
    // ===================================================================
    
    const alertClose = document.querySelector('.saw-alert-close');
    if (alertClose) {
        alertClose.addEventListener('click', function() {
            this.closest('.saw-alert').remove();
        });
    }
    
    // ===================================================================
    // FORM VALIDATION
    // ===================================================================
    
    const form = document.getElementById('saw-customer-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            
            if (name.length < 2) {
                e.preventDefault();
                alert('❌ Název zákazníka musí mít alespoň 2 znaky.');
                document.getElementById('name').focus();
                return false;
            }
            
            const ico = document.getElementById('ico').value.trim();
            if (ico && !/^[0-9]{6,12}$/.test(ico)) {
                e.preventDefault();
                alert('❌ IČO musí obsahovat 6-12 číslic.');
                document.getElementById('ico').focus();
                return false;
            }
            
            return true;
        });
    }
});
</script>