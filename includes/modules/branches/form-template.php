<?php
/**
 * Branches Form Template
 * 
 * Formulář pro vytvoření/editaci pobočky.
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_edit = !empty($item);
$item = $item ?? [];

$opening_hours_text = '';
if (!empty($item['opening_hours'])) {
    $hours_array = json_decode($item['opening_hours'], true);
    if (is_array($hours_array)) {
        $opening_hours_text = implode("\n", $hours_array);
    }
}
?>

<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo $is_edit ? 'Upravit pobočku' : 'Nová pobočka'; ?>
        </h1>
        <a href="<?php echo home_url('/admin/branches/'); ?>" class="saw-back-button">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            Zpět na seznam
        </a>
    </div>
</div>

<div class="saw-form-container">
    <form method="post" class="saw-branch-form" enctype="multipart/form-data">
        <?php wp_nonce_field('saw_branches_form', 'saw_nonce'); ?>
        
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
                        <label for="name" class="saw-label saw-required">
                            Název pobočky
                        </label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['name'] ?? ''); ?>" 
                            required
                            placeholder="Pobočka Praha"
                        >
                        <span class="saw-help-text">
                            Název pobočky (např. "Pobočka Praha", "Centrála Brno")
                        </span>
                    </div>
                    
                    <div class="saw-form-group saw-col-4">
                        <label for="code" class="saw-label">
                            Kód pobočky
                        </label>
                        <input 
                            type="text" 
                            id="code" 
                            name="code" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['code'] ?? ''); ?>"
                            placeholder="PR001"
                        >
                        <span class="saw-help-text">
                            Interní kód (např. "PR001")
                        </span>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ADRESA -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-location"></span>
                <strong>Adresa</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="street" class="saw-label">
                            Ulice a číslo
                        </label>
                        <input 
                            type="text" 
                            id="street" 
                            name="street" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['street'] ?? ''); ?>"
                            placeholder="Hlavní 123"
                        >
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="city" class="saw-label">
                            Město
                        </label>
                        <input 
                            type="text" 
                            id="city" 
                            name="city" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['city'] ?? ''); ?>"
                            placeholder="Praha"
                        >
                    </div>
                    
                    <div class="saw-form-group saw-col-3">
                        <label for="postal_code" class="saw-label">
                            PSČ
                        </label>
                        <input 
                            type="text" 
                            id="postal_code" 
                            name="postal_code" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['postal_code'] ?? ''); ?>"
                            placeholder="110 00"
                        >
                    </div>
                    
                    <div class="saw-form-group saw-col-3">
                        <label for="country" class="saw-label">
                            Země
                        </label>
                        <select id="country" name="country" class="saw-select">
                            <option value="CZ" <?php selected($item['country'] ?? 'CZ', 'CZ'); ?>>Česká republika</option>
                            <option value="SK" <?php selected($item['country'] ?? '', 'SK'); ?>>Slovensko</option>
                            <option value="DE" <?php selected($item['country'] ?? '', 'DE'); ?>>Německo</option>
                            <option value="AT" <?php selected($item['country'] ?? '', 'AT'); ?>>Rakousko</option>
                            <option value="PL" <?php selected($item['country'] ?? '', 'PL'); ?>>Polsko</option>
                        </select>
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="latitude" class="saw-label">
                            Zeměpisná šířka (Latitude)
                        </label>
                        <input 
                            type="number" 
                            id="latitude" 
                            name="latitude" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['latitude'] ?? ''); ?>"
                            step="0.00000001"
                            placeholder="50.0755"
                        >
                        <span class="saw-help-text">
                            GPS souřadnice (např. 50.0755)
                        </span>
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="longitude" class="saw-label">
                            Zeměpisná délka (Longitude)
                        </label>
                        <input 
                            type="number" 
                            id="longitude" 
                            name="longitude" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['longitude'] ?? ''); ?>"
                            step="0.00000001"
                            placeholder="14.4378"
                        >
                        <span class="saw-help-text">
                            GPS souřadnice (např. 14.4378)
                        </span>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- KONTAKTNÍ ÚDAJE -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-phone"></span>
                <strong>Kontaktní údaje</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="phone" class="saw-label">
                            Telefon
                        </label>
                        <input 
                            type="text" 
                            id="phone" 
                            name="phone" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['phone'] ?? ''); ?>"
                            placeholder="+420 123 456 789"
                        >
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="email" class="saw-label">
                            Email
                        </label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['email'] ?? ''); ?>"
                            placeholder="pobocka@firma.cz"
                        >
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- OBRÁZEK -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-format-image"></span>
                <strong>Obrázek pobočky</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label class="saw-label">
                            Fotografie pobočky
                        </label>
                        
                        <?php if (!empty($item['image_url'])): ?>
                            <div class="saw-current-image">
                                <img src="<?php echo esc_url($item['image_url']); ?>" alt="Současný obrázek" style="max-width: 300px; border-radius: 8px;">
                            </div>
                        <?php endif; ?>
                        
                        <input type="hidden" id="image_url" name="image_url" value="<?php echo esc_attr($item['image_url'] ?? ''); ?>">
                        <input type="hidden" id="image_thumbnail" name="image_thumbnail" value="<?php echo esc_attr($item['image_thumbnail'] ?? ''); ?>">
                        
                        <button type="button" class="saw-button saw-button-secondary saw-upload-image-btn">
                            <span class="dashicons dashicons-upload"></span>
                            <?php echo !empty($item['image_url']) ? 'Změnit obrázek' : 'Nahrát obrázek'; ?>
                        </button>
                        
                        <?php if (!empty($item['image_url'])): ?>
                            <button type="button" class="saw-button saw-button-secondary saw-remove-image-btn">
                                <span class="dashicons dashicons-no"></span>
                                Odebrat obrázek
                            </button>
                        <?php endif; ?>
                        
                        <span class="saw-help-text">
                            Obrázek pobočky (doporučeno 800x600px)
                        </span>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- POPIS A POZNÁMKY -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-edit"></span>
                <strong>Popis a poznámky</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="description" class="saw-label">
                            Popis pobočky
                        </label>
                        <textarea 
                            id="description" 
                            name="description" 
                            class="saw-textarea" 
                            rows="5"
                            placeholder="Veřejný popis pobočky..."
                        ><?php echo esc_textarea($item['description'] ?? ''); ?></textarea>
                        <span class="saw-help-text">
                            Veřejný popis pobočky (viditelný pro návštěvníky)
                        </span>
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="notes" class="saw-label">
                            Interní poznámky
                        </label>
                        <textarea 
                            id="notes" 
                            name="notes" 
                            class="saw-textarea" 
                            rows="3"
                            placeholder="Interní poznámky..."
                        ><?php echo esc_textarea($item['notes'] ?? ''); ?></textarea>
                        <span class="saw-help-text">
                            Interní poznámky (neviditelné pro návštěvníky)
                        </span>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- PROVOZNÍ DOBA -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-clock"></span>
                <strong>Provozní doba</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="opening_hours" class="saw-label">
                            Provozní doba
                        </label>
                        <textarea 
                            id="opening_hours" 
                            name="opening_hours" 
                            class="saw-textarea" 
                            rows="7"
                            placeholder="Po: 8:00-16:00&#10;Út: 8:00-16:00&#10;St: 8:00-16:00&#10;Čt: 8:00-16:00&#10;Pá: 8:00-16:00&#10;So: Zavřeno&#10;Ne: Zavřeno"
                        ><?php echo esc_textarea($opening_hours_text); ?></textarea>
                        <span class="saw-help-text">
                            Každý den na nový řádek (např. "Po-Pá: 8:00-16:00")
                        </span>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- NASTAVENÍ -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-settings"></span>
                <strong>Nastavení</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label class="saw-checkbox-label">
                            <input 
                                type="checkbox" 
                                id="is_active" 
                                name="is_active" 
                                value="1"
                                <?php checked(!empty($item['is_active']) ? $item['is_active'] : 1, 1); ?>
                            >
                            <span class="saw-checkbox-text">
                                <strong>Aktivní pobočka</strong>
                                <small>Pouze aktivní pobočky jsou viditelné v systému</small>
                            </span>
                        </label>
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label class="saw-checkbox-label">
                            <input 
                                type="checkbox" 
                                id="is_headquarters" 
                                name="is_headquarters" 
                                value="1"
                                <?php checked(!empty($item['is_headquarters']), 1); ?>
                            >
                            <span class="saw-checkbox-text">
                                <strong>Hlavní sídlo</strong>
                                <small>Označit jako hlavní sídlo společnosti</small>
                            </span>
                        </label>
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="sort_order" class="saw-label">
                            Pořadí řazení
                        </label>
                        <input 
                            type="number" 
                            id="sort_order" 
                            name="sort_order" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['sort_order'] ?? '0'); ?>"
                            min="0"
                            placeholder="0"
                        >
                        <span class="saw-help-text">
                            Nižší číslo = vyšší v seznamu
                        </span>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ACTION BUTTONS -->
        <div class="saw-form-actions">
            <button type="submit" class="saw-button saw-button-primary">
                <span class="dashicons dashicons-yes"></span>
                <?php echo $is_edit ? 'Uložit změny' : 'Vytvořit pobočku'; ?>
            </button>
            <a href="<?php echo home_url('/admin/branches/'); ?>" class="saw-button saw-button-secondary">
                <span class="dashicons dashicons-no-alt"></span>
                Zrušit
            </a>
        </div>
        
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Image upload
    $('.saw-upload-image-btn').on('click', function(e) {
        e.preventDefault();
        
        if (typeof wp !== 'undefined' && wp.media) {
            const frame = wp.media({
                title: 'Vyberte obrázek pobočky',
                button: {
                    text: 'Použít tento obrázek'
                },
                multiple: false
            });
            
            frame.on('select', function() {
                const attachment = frame.state().get('selection').first().toJSON();
                $('#image_url').val(attachment.url);
                $('#image_thumbnail').val(attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url);
                
                $('.saw-current-image').remove();
                $('.saw-upload-image-btn').before('<div class="saw-current-image"><img src="' + attachment.url + '" alt="Nový obrázek" style="max-width: 300px; border-radius: 8px;"></div>');
                $('.saw-upload-image-btn').text('Změnit obrázek').prepend('<span class="dashicons dashicons-upload"></span> ');
                
                if ($('.saw-remove-image-btn').length === 0) {
                    $('.saw-upload-image-btn').after('<button type="button" class="saw-button saw-button-secondary saw-remove-image-btn"><span class="dashicons dashicons-no"></span> Odebrat obrázek</button>');
                }
            });
            
            frame.open();
        }
    });
    
    // Remove image
    $(document).on('click', '.saw-remove-image-btn', function(e) {
        e.preventDefault();
        $('#image_url').val('');
        $('#image_thumbnail').val('');
        $('.saw-current-image').remove();
        $('.saw-remove-image-btn').remove();
        $('.saw-upload-image-btn').text('Nahrát obrázek').prepend('<span class="dashicons dashicons-upload"></span> ');
    });
});
</script>
