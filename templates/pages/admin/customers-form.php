<?php
/**
 * Template: Formulář zákazníka (Create/Edit)
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Načti layout
if ( class_exists( 'SAW_App_Layout' ) ) {
    $layout = new SAW_App_Layout();
    $layout->set_title( $is_edit ? 'Upravit zákazníka' : 'Přidat zákazníka' );
    $layout->set_active_menu( 'settings-customers' );
    
    ob_start();
}
?>

<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo $is_edit ? 'Upravit zákazníka' : 'Přidat nového zákazníka'; ?>
        </h1>
        <p class="saw-page-subtitle">
            <a href="<?php echo esc_url( home_url( '/admin/settings/customers/' ) ); ?>" class="saw-breadcrumb">
                &larr; Zpět na seznam
            </a>
        </p>
    </div>
</div>

<?php if ( isset( $error ) ) : ?>
    <div class="saw-alert saw-alert-error">
        <?php echo esc_html( $error ); ?>
        <button type="button" class="saw-alert-close">&times;</button>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="saw-form" id="saw-customer-form">
    <?php wp_nonce_field( 'saw_customer_form', 'saw_customer_nonce' ); ?>
    
    <div class="saw-card">
        <div class="saw-card-header">
            <h2 class="saw-card-title">Základní informace</h2>
        </div>
        <div class="saw-card-body">
            <div class="saw-form-row">
                <!-- Název -->
                <div class="saw-form-group saw-col-8">
                    <label for="name" class="saw-label saw-required">Název zákazníka</label>
                    <input 
                        type="text" 
                        id="name" 
                        name="name" 
                        class="saw-input" 
                        value="<?php echo esc_attr( $customer['name'] ?? '' ); ?>" 
                        required
                        placeholder="např. ACME s.r.o."
                    >
                    <small class="saw-help-text">Zadejte název firmy nebo organizace</small>
                </div>
                
                <!-- IČO -->
                <div class="saw-form-group saw-col-4">
                    <label for="ico" class="saw-label">IČO</label>
                    <input 
                        type="text" 
                        id="ico" 
                        name="ico" 
                        class="saw-input" 
                        value="<?php echo esc_attr( $customer['ico'] ?? '' ); ?>"
                        placeholder="12345678"
                        pattern="[0-9]{6,12}"
                    >
                    <small class="saw-help-text">6-12 číslic</small>
                </div>
            </div>
            
            <!-- Adresa -->
            <div class="saw-form-group">
                <label for="address" class="saw-label">Adresa</label>
                <textarea 
                    id="address" 
                    name="address" 
                    class="saw-textarea" 
                    rows="3"
                    placeholder="např. Karlovo náměstí 123, Praha 2, 120 00"
                ><?php echo esc_textarea( $customer['address'] ?? '' ); ?></textarea>
                <small class="saw-help-text">Zadejte úplnou adresu firmy</small>
            </div>
            
            <!-- Poznámky -->
            <div class="saw-form-group">
                <label for="notes" class="saw-label">Poznámky</label>
                <textarea 
                    id="notes" 
                    name="notes" 
                    class="saw-textarea" 
                    rows="4"
                    placeholder="Interní poznámky, kontaktní osoby, atd."
                ><?php echo esc_textarea( $customer['notes'] ?? '' ); ?></textarea>
                <small class="saw-help-text">Interní poznámky viditelné jen SuperAdminovi</small>
            </div>
        </div>
    </div>
    
    <div class="saw-card">
        <div class="saw-card-header">
            <h2 class="saw-card-title">Branding</h2>
        </div>
        <div class="saw-card-body">
            <div class="saw-form-row">
                <!-- Logo -->
                <div class="saw-form-group saw-col-6">
                    <label for="logo" class="saw-label">Logo zákazníka</label>
                    
                    <?php if ( $is_edit && ! empty( $customer['logo_url_full'] ) ) : ?>
                        <div class="saw-logo-preview-current">
                            <img src="<?php echo esc_url( $customer['logo_url_full'] ); ?>" alt="Současné logo" id="current-logo">
                            <p class="saw-text-muted">Současné logo</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="saw-file-input-wrapper">
                        <input 
                            type="file" 
                            id="logo" 
                            name="logo" 
                            accept="image/*"
                            class="saw-file-input"
                        >
                        <label for="logo" class="saw-file-label">
                            <span class="dashicons dashicons-upload"></span>
                            Vybrat soubor
                        </label>
                        <span class="saw-file-name">Žádný soubor nevybrán</span>
                    </div>
                    
                    <small class="saw-help-text">
                        Podporované formáty: JPG, PNG, GIF, WebP, SVG<br>
                        Maximální velikost: 5 MB
                    </small>
                    
                    <!-- Preview nového loga -->
                    <div id="logo-preview" class="saw-logo-preview" style="display: none;">
                        <img src="" alt="Náhled loga" id="logo-preview-img">
                        <button type="button" class="saw-btn saw-btn-sm saw-btn-secondary" id="remove-logo-preview">
                            <span class="dashicons dashicons-no"></span> Zrušit
                        </button>
                    </div>
                </div>
                
                <!-- Primární barva -->
                <div class="saw-form-group saw-col-6">
                    <label for="primary_color" class="saw-label">Primární barva</label>
                    <div class="saw-color-picker-wrapper">
                        <input 
                            type="color" 
                            id="primary_color" 
                            name="primary_color" 
                            class="saw-color-input" 
                            value="<?php echo esc_attr( $customer['primary_color'] ?? '#1e40af' ); ?>"
                        >
                        <input 
                            type="text" 
                            id="primary_color_text" 
                            class="saw-input saw-color-text" 
                            value="<?php echo esc_attr( $customer['primary_color'] ?? '#1e40af' ); ?>"
                            pattern="^#[0-9A-Fa-f]{6}$"
                            placeholder="#1e40af"
                        >
                    </div>
                    <small class="saw-help-text">Hlavní barva používaná v aplikaci pro tohoto zákazníka</small>
                    
                    <!-- Color preview -->
                    <div class="saw-color-preview-large" id="color-preview" style="background-color: <?php echo esc_attr( $customer['primary_color'] ?? '#1e40af' ); ?>">
                        <span>Náhled barvy</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="saw-form-actions">
        <button type="submit" class="saw-btn saw-btn-primary saw-btn-lg">
            <span class="dashicons dashicons-yes"></span>
            <?php echo $is_edit ? 'Uložit změny' : 'Vytvořit zákazníka'; ?>
        </button>
        <a href="<?php echo esc_url( home_url( '/admin/settings/customers/' ) ); ?>" class="saw-btn saw-btn-secondary saw-btn-lg">
            Zrušit
        </a>
    </div>
</form>

<script>
jQuery(document).ready(function($) {
    // File input preview
    $('#logo').on('change', function(e) {
        var file = e.target.files[0];
        
        if (file) {
            // Update file name
            $('.saw-file-name').text(file.name);
            
            // Show preview
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#logo-preview-img').attr('src', e.target.result);
                $('#logo-preview').show();
                $('#current-logo').css('opacity', '0.3');
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Remove preview
    $('#remove-logo-preview').on('click', function() {
        $('#logo').val('');
        $('.saw-file-name').text('Žádný soubor nevybrán');
        $('#logo-preview').hide();
        $('#current-logo').css('opacity', '1');
    });
    
    // Color picker sync
    $('#primary_color').on('input', function() {
        var color = $(this).val();
        $('#primary_color_text').val(color);
        $('#color-preview').css('background-color', color);
    });
    
    $('#primary_color_text').on('input', function() {
        var color = $(this).val();
        if (/^#[0-9A-Fa-f]{6}$/.test(color)) {
            $('#primary_color').val(color);
            $('#color-preview').css('background-color', color);
        }
    });
    
    // Form validation
    $('#saw-customer-form').on('submit', function(e) {
        var name = $('#name').val().trim();
        
        if (name.length < 2) {
            e.preventDefault();
            alert('Název zákazníka musí mít alespoň 2 znaky.');
            $('#name').focus();
            return false;
        }
        
        // Validate IČO if filled
        var ico = $('#ico').val().trim();
        if (ico && !/^[0-9]{6,12}$/.test(ico.replace(/\s/g, ''))) {
            e.preventDefault();
            alert('IČO musí obsahovat 6-12 číslic.');
            $('#ico').focus();
            return false;
        }
        
        // Validate color
        var color = $('#primary_color_text').val();
        if (!/^#[0-9A-Fa-f]{6}$/.test(color)) {
            e.preventDefault();
            alert('Primární barva musí být v HEX formátu (#RRGGBB).');
            $('#primary_color_text').focus();
            return false;
        }
        
        return true;
    });
    
    // Close alert
    $(document).on('click', '.saw-alert-close', function() {
        $(this).closest('.saw-alert').fadeOut(300, function() {
            $(this).remove();
        });
    });
});
</script>

<?php
if ( class_exists( 'SAW_App_Layout' ) ) {
    $content = ob_get_clean();
    $layout->render( $content );
}
?>
