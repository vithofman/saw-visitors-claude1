<?php
/**
 * Branches Form Template
 * 
 * REFACTORED v3.0.0:
 * ✅ Professional file upload component
 * ✅ Global CSS classes
 * ✅ No inline styles
 * ✅ All values escaped
 * 
 * @package SAW_Visitors
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_edit = !empty($item);
$item = $item ?? [];

$customer_id = SAW_Context::get_customer_id();

// Enqueue file upload assets
if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/class-saw-file-uploader.php')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/class-saw-file-uploader.php';
    $file_uploader = new SAW_File_Uploader();
    $file_uploader->enqueue_assets();
}
?>

<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo $is_edit ? 'Upravit pobočku' : 'Nová pobočka'; ?>
        </h1>
        <a href="<?php echo esc_url(home_url('/admin/branches/')); ?>" class="saw-back-button">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            Zpět na seznam
        </a>
    </div>
</div>

<div class="saw-form-container">
    <form method="post" enctype="multipart/form-data" class="saw-branch-form">
        <?php wp_nonce_field('saw_branches_form', 'saw_nonce'); ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
        <?php endif; ?>
        
        <input type="hidden" name="customer_id" value="<?php echo esc_attr($customer_id); ?>">
        
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-generic"></span>
                <strong>Základní informace</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-8">
                        <label for="name" class="saw-label">
                            Název pobočky <span class="saw-required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['name'] ?? ''); ?>"
                            required
                        >
                        <span class="saw-help-text">
                            Název pobočky (např. "Pobočka Praha")
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
                        >
                        <span class="saw-help-text">
                            Interní kód (např. "PR001")
                        </span>
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <?php
                        // ✅ Professional file upload component
                        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/file-upload-input.php')) {
                            $id = 'image_url';
                            $name = 'image_url';
                            $config = [];
                            $current_file_url = $item['image_url'] ?? '';
                            $label = 'Nahrát obrázek';
                            $current_label = 'Současný obrázek';
                            $help_text = 'Hlavní obrázek pobočky · Maximální velikost 2MB · Podporované formáty: JPG, PNG, GIF';
                            $accept = 'image/jpeg,image/png,image/gif';
                            $show_preview = true;
                            $custom_class = '';
                            
                            require SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/file-upload-input.php';
                        }
                        ?>
                    </div>
                </div>
                
            </div>
        </details>
        
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-location"></span>
                <strong>Adresa</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="street" class="saw-label">
                            Ulice a číslo
                        </label>
                        <input 
                            type="text" 
                            id="street" 
                            name="street" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['street'] ?? ''); ?>"
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
                            placeholder="123 45"
                        >
                    </div>
                    
                    <div class="saw-form-group saw-col-3">
                        <label for="country" class="saw-label">
                            Země
                        </label>
                        <select id="country" name="country" class="saw-select">
                            <?php
                            $countries = [
                                'CZ' => 'Česká republika',
                                'SK' => 'Slovensko',
                                'DE' => 'Německo',
                                'AT' => 'Rakousko',
                                'PL' => 'Polsko',
                            ];
                            $selected_country = $item['country'] ?? 'CZ';
                            foreach ($countries as $code => $name_country) {
                                printf(
                                    '<option value="%s"%s>%s</option>',
                                    esc_attr($code),
                                    selected($selected_country, $code, false),
                                    esc_html($name_country)
                                );
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
            </div>
        </details>
        
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-location-alt"></span>
                <strong>GPS Souřadnice</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="latitude" class="saw-label">
                            Zeměpisná šířka
                        </label>
                        <input 
                            type="number" 
                            id="latitude" 
                            name="latitude" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['latitude'] ?? ''); ?>"
                            step="0.00000001"
                            placeholder="50.0755381"
                        >
                        <span class="saw-help-text">
                            GPS souřadnice (např. 50.0755)
                        </span>
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="longitude" class="saw-label">
                            Zeměpisná délka
                        </label>
                        <input 
                            type="number" 
                            id="longitude" 
                            name="longitude" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['longitude'] ?? ''); ?>"
                            step="0.00000001"
                            placeholder="14.4378005"
                        >
                        <span class="saw-help-text">
                            GPS souřadnice (např. 14.4378)
                        </span>
                    </div>
                </div>
                
            </div>
        </details>
        
        <details class="saw-form-section">
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
        
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-clock"></span>
                <strong>Provozní doba</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="opening_hours" class="saw-label">
                            Provozní doba
                        </label>
                        <textarea 
                            id="opening_hours" 
                            name="opening_hours" 
                            class="saw-textarea"
                            rows="7"
                            placeholder="Po: 8:00-16:00&#10;Út: 8:00-16:00&#10;St: 8:00-16:00&#10;Čt: 8:00-16:00&#10;Pá: 8:00-16:00&#10;So: Zavřeno&#10;Ne: Zavřeno"
                        ><?php 
                            if (!empty($item['opening_hours'])) {
                                if (is_string($item['opening_hours'])) {
                                    $hours_array = json_decode($item['opening_hours'], true);
                                    if (is_array($hours_array)) {
                                        echo esc_textarea(implode("\n", $hours_array));
                                    } else {
                                        echo esc_textarea($item['opening_hours']);
                                    }
                                }
                            }
                        ?></textarea>
                        <span class="saw-help-text">
                            Každý den na nový řádek (např. "Po-Pá: 8:00-16:00")
                        </span>
                    </div>
                </div>
                
            </div>
        </details>
        
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-media-text"></span>
                <strong>Popis a poznámky</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="description" class="saw-label">
                            Veřejný popis
                        </label>
                        <textarea 
                            id="description" 
                            name="description" 
                            class="saw-textarea"
                            rows="5"
                        ><?php echo esc_textarea($item['description'] ?? ''); ?></textarea>
                        <span class="saw-help-text">
                            Veřejný popis pobočky (viditelný pro návštěvníky)
                        </span>
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="notes" class="saw-label">
                            Interní poznámky
                        </label>
                        <textarea 
                            id="notes" 
                            name="notes" 
                            class="saw-textarea"
                            rows="3"
                        ><?php echo esc_textarea($item['notes'] ?? ''); ?></textarea>
                        <span class="saw-help-text">
                            Interní poznámky (neviditelné pro návštěvníky)
                        </span>
                    </div>
                </div>
                
            </div>
        </details>
        
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-settings"></span>
                <strong>Nastavení</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-4">
                        <label class="saw-checkbox-label">
                            <input 
                                type="checkbox" 
                                name="is_active" 
                                value="1"
                                <?php checked(!isset($item['is_active']) || !empty($item['is_active'])); ?>
                            >
                            <span class="saw-checkbox-text">Aktivní pobočka</span>
                        </label>
                        <span class="saw-help-text">
                            Pouze aktivní pobočky jsou viditelné
                        </span>
                    </div>
                    
                    <div class="saw-form-group saw-col-4">
                        <label class="saw-checkbox-label">
                            <input 
                                type="checkbox" 
                                name="is_headquarters" 
                                id="is_headquarters"
                                value="1"
                                <?php checked(!empty($item['is_headquarters'])); ?>
                            >
                            <span class="saw-checkbox-text">Hlavní sídlo</span>
                        </label>
                        <span class="saw-help-text">
                            Je toto hlavní sídlo společnosti?
                        </span>
                    </div>
                    
                    <div class="saw-form-group saw-col-4">
                        <label for="sort_order" class="saw-label">
                            Pořadí řazení
                        </label>
                        <input 
                            type="number" 
                            id="sort_order" 
                            name="sort_order" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['sort_order'] ?? 0); ?>"
                            min="0"
                        >
                        <span class="saw-help-text">
                            Nižší číslo = vyšší v seznamu
                        </span>
                    </div>
                </div>
                
            </div>
        </details>
        
        <div class="saw-form-actions">
            <button type="submit" class="saw-button saw-button-primary">
                <span class="dashicons dashicons-yes"></span>
                <?php echo $is_edit ? 'Uložit změny' : 'Vytvořit pobočku'; ?>
            </button>
            <a href="<?php echo esc_url(home_url('/admin/branches/')); ?>" class="saw-button saw-button-secondary">
                <span class="dashicons dashicons-no-alt"></span>
                Zrušit
            </a>
        </div>
        
    </form>
</div>