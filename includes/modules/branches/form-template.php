<?php
/**
 * Branches Form Template
 *
 * REFACTORED v13.1.0 - PRODUCTION READY
 * ✅ Sidebar + page support
 * ✅ Opening hours inline management
 * ✅ GPS button
 * ✅ File upload
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches
 * @version     13.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_edit = !empty($item);
$item = $item ?? array();
$in_sidebar = isset($GLOBALS['saw_sidebar_form']) && $GLOBALS['saw_sidebar_form'];

// Decode opening hours
$opening_hours = array();
if (!empty($item['opening_hours'])) {
    $opening_hours = json_decode($item['opening_hours'], true);
}
if (!is_array($opening_hours)) {
    $opening_hours = array();
}

$days = array(
    'monday' => 'Pondělí',
    'tuesday' => 'Úterý',
    'wednesday' => 'Středa',
    'thursday' => 'Čtvrtek',
    'friday' => 'Pátek',
    'saturday' => 'Sobota',
    'sunday' => 'Neděle',
);

?>

<?php if (!$in_sidebar): ?>
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
<?php endif; ?>

<div class="saw-form-container saw-module-branches">
    <form method="post" action="" enctype="multipart/form-data" class="saw-branch-form">
        <?php
        $nonce_action = $is_edit ? 'saw_edit_branches' : 'saw_create_branches';
        wp_nonce_field($nonce_action, '_wpnonce', false);
        ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
        <?php endif; ?>
        
        <!-- Základní informace -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-generic"></span>
                <strong>Základní informace</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-8">
                        <label for="name" class="saw-label saw-required">Název pobočky</label>
                        <input type="text" id="name" name="name" class="saw-input"
                               value="<?php echo esc_attr($item['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="saw-form-group saw-col-4">
                        <label for="code" class="saw-label">Kód pobočky</label>
                        <input type="text" id="code" name="code" class="saw-input"
                               value="<?php echo esc_attr($item['code'] ?? ''); ?>"
                               placeholder="např. HQ, PR1">
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label class="saw-checkbox-label">
                            <input type="checkbox" name="is_headquarters" value="1"
                                   <?php checked(!empty($item['is_headquarters'])); ?>>
                            <span>Sídlo firmy</span>
                        </label>
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label class="saw-checkbox-label">
                            <input type="checkbox" name="is_active" value="1"
                                   <?php checked(empty($item) || !empty($item['is_active'])); ?>>
                            <span>Aktivní</span>
                        </label>
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="sort_order" class="saw-label">Pořadí</label>
                        <input type="number" id="sort_order" name="sort_order" class="saw-input"
                               value="<?php echo esc_attr($item['sort_order'] ?? 10); ?>" min="0">
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- Kontakt -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-phone"></span>
                <strong>Kontaktní údaje</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="phone" class="saw-label">Telefon</label>
                        <input type="text" id="phone" name="phone" class="saw-input"
                               value="<?php echo esc_attr($item['phone'] ?? ''); ?>"
                               placeholder="+420 123 456 789">
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="email" class="saw-label">Email</label>
                        <input type="email" id="email" name="email" class="saw-input"
                               value="<?php echo esc_attr($item['email'] ?? ''); ?>"
                               placeholder="pobocka@firma.cz">
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- Adresa -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-location"></span>
                <strong>Adresa</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="street" class="saw-label">Ulice a č.p.</label>
                        <input type="text" id="street" name="street" class="saw-input"
                               value="<?php echo esc_attr($item['street'] ?? ''); ?>"
                               placeholder="Hlavní 123">
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="city" class="saw-label">Město</label>
                        <input type="text" id="city" name="city" class="saw-input"
                               value="<?php echo esc_attr($item['city'] ?? ''); ?>"
                               placeholder="Praha">
                    </div>
                    
                    <div class="saw-form-group saw-col-3">
                        <label for="postal_code" class="saw-label">PSČ</label>
                        <input type="text" id="postal_code" name="postal_code" class="saw-input"
                               value="<?php echo esc_attr($item['postal_code'] ?? ''); ?>"
                               placeholder="110 00">
                    </div>
                    
                    <div class="saw-form-group saw-col-3">
                        <label for="country" class="saw-label">Země</label>
                        <input type="text" id="country" name="country" class="saw-input"
                               value="<?php echo esc_attr($item['country'] ?? 'CZ'); ?>"
                               maxlength="2">
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-5">
                        <label for="latitude" class="saw-label">GPS Latitude</label>
                        <input type="text" id="latitude" name="latitude" class="saw-input"
                               value="<?php echo esc_attr($item['latitude'] ?? ''); ?>"
                               placeholder="50.0755">
                    </div>
                    
                    <div class="saw-form-group saw-col-5">
                        <label for="longitude" class="saw-label">GPS Longitude</label>
                        <input type="text" id="longitude" name="longitude" class="saw-input"
                               value="<?php echo esc_attr($item['longitude'] ?? ''); ?>"
                               placeholder="14.4378">
                    </div>
                    
                    <div class="saw-form-group saw-col-2">
                        <label class="saw-label">&nbsp;</label>
                        <button type="button" id="load-gps-btn" class="saw-button saw-button-secondary">
                            📍 GPS
                        </button>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- Logo -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-format-image"></span>
                <strong>Logo / Obrázek</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <?php if (!empty($item['image_url'])): ?>
                    <div class="saw-current-image">
                        <img src="<?php echo esc_url($item['image_url']); ?>" alt="Current" style="max-width: 200px;">
                    </div>
                <?php endif; ?>
                
                <div class="saw-form-group">
                    <label for="image_url" class="saw-label">Nahrát nový obrázek</label>
                    <input type="file" id="image_url" name="image_url" accept="image/*" class="saw-input">
                    <small class="saw-help-text">Formáty: JPG, PNG, GIF. Max 2MB.</small>
                </div>
                
            </div>
        </details>
        
        <!-- Poznámky -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-edit-page"></span>
                <strong>Poznámky a popis</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-group">
                    <label for="description" class="saw-label">Popis</label>
                    <textarea id="description" name="description" class="saw-textarea" rows="3"><?php echo esc_textarea($item['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="saw-form-group">
                    <label for="notes" class="saw-label">Interní poznámky</label>
                    <textarea id="notes" name="notes" class="saw-textarea" rows="3"><?php echo esc_textarea($item['notes'] ?? ''); ?></textarea>
                </div>
                
            </div>
        </details>
        
        <!-- Submit -->
        <div class="saw-form-actions">
            <button type="submit" class="saw-button saw-button-primary">
                <?php echo $is_edit ? 'Uložit změny' : 'Vytvořit pobočku'; ?>
            </button>
            
            <?php if (!$in_sidebar): ?>
                <a href="<?php echo esc_url(home_url('/admin/branches/')); ?>" class="saw-button saw-button-secondary">
                    Zrušit
                </a>
            <?php endif; ?>
        </div>
        
    </form>
</div>