<?php
/**
 * Branches Form Template
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches
 * @version     18.0.0 - Translation system added
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// TRANSLATIONS
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}
$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'branches') 
    : [];

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// SETUP
// ============================================
$is_edit = !empty($item);
$item = $item ?? array();
$in_sidebar = isset($GLOBALS['saw_sidebar_form']) && $GLOBALS['saw_sidebar_form'];
?>

<?php if (!$in_sidebar): ?>
<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo $is_edit 
                ? esc_html($tr('form_title_edit', 'Upravit pobočku')) 
                : esc_html($tr('form_title_create', 'Nová pobočka')); ?>
        </h1>
        <a href="<?php echo esc_url(home_url('/admin/branches/')); ?>" class="saw-back-button">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            <?php echo esc_html($tr('btn_back', 'Zpět na seznam')); ?>
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
        
        <!-- ============================================ -->
        <!-- ZÁKLADNÍ INFORMACE -->
        <!-- ============================================ -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-generic"></span>
                <strong><?php echo esc_html($tr('section_basic', 'Základní informace')); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-8">
                        <label for="name" class="saw-label saw-required">
                            <?php echo esc_html($tr('field_name', 'Název pobočky')); ?>
                        </label>
                        <input type="text" id="name" name="name" class="saw-input"
                               value="<?php echo esc_attr($item['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="saw-form-group saw-col-4">
                        <label for="code" class="saw-label">
                            <?php echo esc_html($tr('field_code', 'Kód pobočky')); ?>
                        </label>
                        <input type="text" id="code" name="code" class="saw-input"
                               value="<?php echo esc_attr($item['code'] ?? ''); ?>"
                               placeholder="<?php echo esc_attr($tr('field_code_placeholder', 'např. HQ, PR1')); ?>">
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label class="saw-checkbox-label">
                            <input type="checkbox" name="is_headquarters" value="1"
                                   <?php checked(!empty($item['is_headquarters'])); ?>>
                            <span><?php echo esc_html($tr('field_is_headquarters', 'Sídlo firmy')); ?></span>
                        </label>
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label class="saw-checkbox-label">
                            <input type="checkbox" name="is_active" value="1"
                                   <?php checked(empty($item) || !empty($item['is_active'])); ?>>
                            <span><?php echo esc_html($tr('field_is_active', 'Aktivní')); ?></span>
                        </label>
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-4">
                        <label for="sort_order" class="saw-label">
                            <?php echo esc_html($tr('field_sort_order', 'Pořadí')); ?>
                        </label>
                        <input type="number" id="sort_order" name="sort_order" class="saw-input"
                               value="<?php echo esc_attr($item['sort_order'] ?? 10); ?>" min="0">
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ============================================ -->
        <!-- KONTAKT -->
        <!-- ============================================ -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-phone"></span>
                <strong><?php echo esc_html($tr('section_contact', 'Kontaktní údaje')); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="phone" class="saw-label">
                            <?php echo esc_html($tr('field_phone', 'Telefon')); ?>
                        </label>
                        <input type="text" id="phone" name="phone" class="saw-input"
                               value="<?php echo esc_attr($item['phone'] ?? ''); ?>"
                               placeholder="+420 123 456 789">
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="email" class="saw-label">
                            <?php echo esc_html($tr('field_email', 'Email')); ?>
                        </label>
                        <input type="email" id="email" name="email" class="saw-input"
                               value="<?php echo esc_attr($item['email'] ?? ''); ?>"
                               placeholder="pobocka@firma.cz">
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ============================================ -->
        <!-- ADRESA -->
        <!-- ============================================ -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-location"></span>
                <strong><?php echo esc_html($tr('section_address', 'Adresa')); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="street" class="saw-label">
                            <?php echo esc_html($tr('field_street', 'Ulice a č.p.')); ?>
                        </label>
                        <input type="text" id="street" name="street" class="saw-input"
                               value="<?php echo esc_attr($item['street'] ?? ''); ?>"
                               placeholder="<?php echo esc_attr($tr('field_street_placeholder', 'Hlavní 123')); ?>">
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="city" class="saw-label">
                            <?php echo esc_html($tr('field_city', 'Město')); ?>
                        </label>
                        <input type="text" id="city" name="city" class="saw-input"
                               value="<?php echo esc_attr($item['city'] ?? ''); ?>"
                               placeholder="Praha">
                    </div>
                    
                    <div class="saw-form-group saw-col-3">
                        <label for="postal_code" class="saw-label">
                            <?php echo esc_html($tr('field_postal_code', 'PSČ')); ?>
                        </label>
                        <input type="text" id="postal_code" name="postal_code" class="saw-input"
                               value="<?php echo esc_attr($item['postal_code'] ?? ''); ?>"
                               placeholder="110 00">
                    </div>
                    
                    <div class="saw-form-group saw-col-3">
                        <label for="country" class="saw-label">
                            <?php echo esc_html($tr('field_country', 'Země')); ?>
                        </label>
                        <input type="text" id="country" name="country" class="saw-input"
                               value="<?php echo esc_attr($item['country'] ?? 'CZ'); ?>"
                               maxlength="2"
                               placeholder="CZ">
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ============================================ -->
        <!-- LOGO / OBRÁZEK -->
        <!-- ============================================ -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-format-image"></span>
                <strong><?php echo esc_html($tr('section_image', 'Logo / Obrázek')); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <?php
                $has_image = !empty($item['image_url']);
                $current_image_url = $item['image_url'] ?? '';
                ?>
                
                <div class="saw-file-upload-component" data-context="branches">
                    <div class="saw-file-upload-area">
                        
                        <!-- Preview Section -->
                        <div class="saw-file-preview-section">
                            <div class="saw-file-preview-box<?php echo $has_image ? ' has-file' : ''; ?>">
                                <?php if ($has_image): ?>
                                    <img src="<?php echo esc_url($current_image_url); ?>" 
                                         alt="<?php echo esc_attr($tr('field_image_current', 'Současný obrázek')); ?>" 
                                         class="saw-preview-image">
                                    <button type="button" class="saw-file-remove-overlay" title="<?php echo esc_attr($tr('btn_remove', 'Odstranit')); ?>">
                                        <span class="saw-remove-icon">🗑️</span>
                                    </button>
                                <?php else: ?>
                                    <div class="saw-file-empty-state">
                                        <div class="saw-file-icon-wrapper">
                                            <span class="saw-file-icon-emoji">🖼️</span>
                                        </div>
                                        <p class="saw-file-empty-text"><?php echo esc_html($tr('field_image_empty', 'Žádný obrázek')); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($has_image): ?>
                                <p class="saw-current-file-label"><?php echo esc_html($tr('field_image_current', 'Současný obrázek')); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Upload Controls -->
                        <div class="saw-file-upload-controls">
                            <input type="file" 
                                   name="image_url" 
                                   id="image_url" 
                                   class="saw-file-input"
                                   accept="image/jpeg,image/png,image/webp"
                                   data-max-size="2097152">
                            
                            <label for="image_url" class="saw-file-upload-trigger">
                                📤 <?php echo esc_html($tr('field_image_upload', 'Nahrát obrázek')); ?>
                            </label>
                            
                            <div class="saw-file-selected-info hidden">
                                <span class="saw-success-icon">✅</span>
                                <span class="saw-file-name"></span>
                                <span class="saw-file-size"></span>
                                <button type="button" class="saw-file-clear-btn">
                                    ✕
                                </button>
                            </div>
                            
                            <p class="saw-help-text">
                                <?php echo esc_html($tr('field_image_help', 'Nahrajte obrázek ve formátu JPG, PNG nebo WebP (max 2MB)')); ?>
                            </p>
                            
                            <!-- Hidden field for removing existing image -->
                            <input type="hidden" name="remove_image" class="saw-file-remove-flag" value="0">
                        </div>
                        
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ============================================ -->
        <!-- POZNÁMKY -->
        <!-- ============================================ -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-edit-page"></span>
                <strong><?php echo esc_html($tr('section_notes', 'Poznámky a popis')); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-group">
                    <label for="description" class="saw-label">
                        <?php echo esc_html($tr('field_description', 'Popis')); ?>
                    </label>
                    <textarea id="description" name="description" class="saw-textarea" rows="3"
                              placeholder="<?php echo esc_attr($tr('field_description_placeholder', 'Veřejný popis pobočky...')); ?>"><?php 
                        echo esc_textarea($item['description'] ?? ''); 
                    ?></textarea>
                </div>
                
                <div class="saw-form-group">
                    <label for="notes" class="saw-label">
                        <?php echo esc_html($tr('field_notes', 'Interní poznámky')); ?>
                    </label>
                    <textarea id="notes" name="notes" class="saw-textarea" rows="3"
                              placeholder="<?php echo esc_attr($tr('field_notes_placeholder', 'Poznámky viditelné pouze pro administrátory...')); ?>"><?php 
                        echo esc_textarea($item['notes'] ?? ''); 
                    ?></textarea>
                </div>
                
            </div>
        </details>
        
        <!-- ============================================ -->
        <!-- SUBMIT -->
        <!-- ============================================ -->
        <div class="saw-form-actions">
            <button type="submit" class="saw-button saw-button-primary">
                <?php echo $is_edit 
                    ? esc_html($tr('btn_save', 'Uložit změny')) 
                    : esc_html($tr('btn_create', 'Vytvořit pobočku')); ?>
            </button>
            
            <?php if (!$in_sidebar): ?>
                <a href="<?php echo esc_url(home_url('/admin/branches/')); ?>" class="saw-button saw-button-secondary">
                    <?php echo esc_html($tr('btn_cancel', 'Zrušit')); ?>
                </a>
            <?php endif; ?>
        </div>
        
    </form>
</div>

<!-- ============================================ -->
<!-- FILE UPLOAD COMPONENT STYLES -->
<!-- ============================================ -->
<style>
.saw-file-upload-area {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 24px;
    align-items: start;
}

.saw-file-preview-section {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.saw-file-preview-box {
    position: relative;
    width: 200px;
    height: 200px;
    border: 2px solid #dcdcde;
    border-radius: 8px;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    transition: all 0.2s ease;
}

.saw-file-preview-box:hover {
    border-color: #a7aaad;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.saw-file-preview-box.has-file {
    cursor: pointer;
}

.saw-preview-image {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    padding: 12px;
}

.saw-file-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 24px;
    text-align: center;
}

.saw-file-icon-wrapper {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: #f0f0f1;
    display: flex;
    align-items: center;
    justify-content: center;
}

.saw-file-icon-wrapper .dashicons {
    font-size: 32px;
    width: 32px;
    height: 32px;
    color: #a7aaad;
}

.saw-file-icon-emoji {
    font-size: 32px;
    line-height: 1;
}

.saw-remove-icon {
    font-size: 48px;
    line-height: 1;
}

.saw-file-empty-text {
    margin: 0;
    font-size: 13px;
    color: #757575;
    font-weight: 500;
}

.saw-file-remove-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(220, 38, 38, 0.95);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.15s ease;
    border: none;
    cursor: pointer;
    border-radius: 6px;
}

.saw-file-preview-box.has-file:hover .saw-file-remove-overlay {
    opacity: 1;
}

.saw-file-remove-overlay .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
    color: #ffffff;
}

.saw-success-icon {
    font-size: 20px;
}

.saw-current-file-label {
    margin: 0;
    font-size: 12px;
    color: #757575;
    text-align: center;
}

.saw-file-upload-controls {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.saw-file-input {
    position: absolute;
    width: 0.1px;
    height: 0.1px;
    opacity: 0;
    overflow: hidden;
    z-index: -1;
}

.saw-file-upload-trigger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 20px;
    background: #ffffff;
    color: #50575e;
    border: 2px solid #c3c4c7;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
    align-self: flex-start;
}

.saw-file-upload-trigger:hover {
    background: #f6f7f7;
    border-color: #0073aa;
    color: #0073aa;
}

.saw-file-input:focus + .saw-file-upload-trigger {
    outline: 2px solid #0073aa;
    outline-offset: 2px;
}

.saw-file-selected-info {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: #d1fae5;
    border: 1px solid #a7f3d0;
    border-radius: 6px;
    transition: all 0.15s ease;
}

.saw-file-selected-info.hidden {
    display: none;
}

.saw-file-selected-info .saw-success-icon {
    color: #059669;
    font-size: 20px;
}

.saw-file-name {
    font-weight: 500;
    color: #065f46;
    flex: 1;
}

.saw-file-size {
    font-size: 12px;
    color: #6b7280;
}

.saw-file-clear-btn {
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
    transition: background 0.15s ease;
    font-size: 16px;
    color: #dc2626;
    font-weight: bold;
}

.saw-file-clear-btn:hover {
    background: rgba(220, 38, 38, 0.1);
}

@media (max-width: 600px) {
    .saw-file-upload-area {
        grid-template-columns: 1fr;
    }
    
    .saw-file-preview-box {
        width: 100%;
        height: 180px;
    }
}
</style>

<!-- ============================================ -->
<!-- FILE UPLOAD COMPONENT JAVASCRIPT -->
<!-- ============================================ -->
<script>
jQuery(document).ready(function($) {
    
    var $component = $('.saw-file-upload-component');
    var $input = $component.find('.saw-file-input');
    var $preview = $component.find('.saw-file-preview-box');
    var $selectedInfo = $component.find('.saw-file-selected-info');
    var $clearBtn = $component.find('.saw-file-clear-btn');
    var $helpText = $component.find('.saw-help-text');
    var $removeFlag = $component.find('.saw-file-remove-flag');
    var $removeOverlay = $component.find('.saw-file-remove-overlay');
    
    var maxSize = parseInt($input.data('max-size')) || 2097152;
    var allowedTypes = ($input.attr('accept') || '').split(',').map(function(t) { return t.trim(); });
    var originalHelpText = $helpText.text();
    
    // File input change
    $input.on('change', function() {
        var file = this.files[0];
        if (!file) return;
        
        // Validate size
        if (file.size > maxSize) {
            var maxMB = (maxSize / 1024 / 1024).toFixed(1);
            showError('Soubor je příliš velký. Maximální velikost je ' + maxMB + 'MB.');
            $input.val('');
            return;
        }
        
        // Validate type
        var isValidType = allowedTypes.some(function(type) {
            return file.type.match(type.replace('*', '.*'));
        });
        if (allowedTypes.length > 0 && !isValidType) {
            showError('Neplatný typ souboru!');
            $input.val('');
            return;
        }
        
        // Show selected file info
        $selectedInfo.removeClass('hidden');
        $selectedInfo.find('.saw-file-name').text(file.name);
        $selectedInfo.find('.saw-file-size').text(formatFileSize(file.size));
        $helpText.text(originalHelpText).removeClass('saw-error-text');
        
        // Show preview
        if (file.type.startsWith('image/')) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $preview.html('<img src="' + e.target.result + '" class="saw-preview-image" alt="Náhled">');
                $preview.addClass('has-file');
            };
            reader.readAsDataURL(file);
        }
        
        // Clear remove flag
        $removeFlag.val('0');
    });
    
    // Clear button
    $clearBtn.on('click', function() {
        $input.val('');
        $selectedInfo.addClass('hidden');
        resetPreview();
    });
    
    // Remove overlay click (for existing images)
    $removeOverlay.on('click', function(e) {
        e.preventDefault();
        $removeFlag.val('1');
        resetPreview();
        $input.val('');
    });
    
    function resetPreview() {
        $preview.removeClass('has-file');
        $preview.html(
            '<div class="saw-file-empty-state">' +
                '<div class="saw-file-icon-wrapper">' +
                    '<span class="saw-file-icon-emoji">🖼️</span>' +
                '</div>' +
                '<p class="saw-file-empty-text">Žádný obrázek</p>' +
            '</div>'
        );
    }
    
    function showError(message) {
        $helpText.text(message).addClass('saw-error-text');
    }
    
    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }
    
});
</script>