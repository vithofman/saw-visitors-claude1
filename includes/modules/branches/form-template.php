<?php
/**
 * Branches Form Template
 *
 * FINAL v14.0.0 - WITH FILE UPLOAD COMPONENT (COMPLETE VERSION)
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches
 * @version     14.0.0
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
    <div class="saw-form-group saw-col-12">
        <label class="saw-checkbox-label">
            <input type="checkbox" name="is_headquarters" value="1"
                   <?php checked(!empty($item['is_headquarters'])); ?>>
            <span>Sídlo firmy</span>
        </label>
    </div>
</div>

<div class="saw-form-row">
    <div class="saw-form-group saw-col-12">
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
                
            </div>
        </details>
        
        <!-- Logo -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-format-image"></span>
                <strong>Logo / Obrázek</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <?php
                        // ✅ FILE UPLOAD COMPONENT - STEJNĚ JAKO CUSTOMERS
                        $id = 'image_url';
                        $name = 'image_url';
                        $current_file_url = $item['image_url'] ?? '';
                        $label = 'Nahrát obrázek';
                        $current_label = 'Současný obrázek';
                        $help_text = 'Nahrajte obrázek ve formátu JPG, PNG nebo WebP (max 2MB)';
                        $accept = 'image/jpeg,image/png,image/webp';
                        $show_preview = true;
                        $config = array();
                        
                        require SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/file-upload-input.php';
                        ?>
                    </div>
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