<?php
/**
 * File Upload Input Template
 * 
 * @package SAW_Visitors
 */

if (!defined('ABSPATH')) {
    exit;
}

$id = $id ?? 'file';
$name = $name ?? 'file';
$config = $config ?? [];
$current_file_url = $current_file_url ?? '';
$label = $label ?? 'Nahrát soubor';
$current_label = $current_label ?? 'Současný soubor';
$help_text = $help_text ?? '';
$accept = $accept ?? 'image/jpeg,image/png,image/gif';
$show_preview = $show_preview ?? true;
$custom_class = $custom_class ?? '';

$uploader = new SAW_File_Uploader($config);
$upload_config = $uploader->get_config();
$max_size_mb = round($upload_config['max_file_size'] / 1048576, 1);

$has_file = !empty($current_file_url);
?>

<div class="saw-file-upload-component <?php echo esc_attr($custom_class); ?>">
    <div class="saw-file-upload-area">
        <div class="saw-file-preview-section">
            <label class="saw-label"><?php echo esc_html($current_label); ?></label>
            <div class="saw-file-preview-box <?php echo $has_file ? 'has-file' : ''; ?>" 
                 id="<?php echo esc_attr($id); ?>-preview">
                <?php if ($has_file): ?>
                    <img src="<?php echo esc_url($current_file_url); ?>" alt="<?php echo esc_attr($label); ?>" class="saw-preview-image">
                    <button type="button" class="saw-file-remove-overlay" title="Odstranit">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                <?php else: ?>
                    <div class="saw-file-empty-state">
                        <div class="saw-file-icon-wrapper">
                            <span class="dashicons dashicons-format-image"></span>
                        </div>
                        <p class="saw-file-empty-text">Zatím žádné logo</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="saw-file-upload-controls">
            <label class="saw-label"><?php echo esc_html($label); ?></label>
            
            <input type="file" 
                   id="<?php echo esc_attr($id); ?>" 
                   name="<?php echo esc_attr($name); ?>" 
                   class="saw-file-input"
                   accept="<?php echo esc_attr($accept); ?>"
                   data-max-size="<?php echo esc_attr($upload_config['max_file_size']); ?>">
            
            <label for="<?php echo esc_attr($id); ?>" class="saw-file-upload-trigger">
                <span class="dashicons dashicons-upload"></span>
                <span class="saw-upload-text">Vybrat soubor</span>
            </label>
            
            <div class="saw-file-selected-info hidden">
                <div class="saw-file-selected-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="saw-file-selected-details">
                    <div class="saw-file-selected-name"></div>
                    <div class="saw-file-selected-meta"></div>
                </div>
                <button type="button" class="saw-file-clear-btn" title="Zrušit výběr">
                    <span class="dashicons dashicons-dismiss"></span>
                </button>
            </div>
            
            <?php if ($help_text): ?>
                <p class="saw-help-text"><?php echo esc_html($help_text); ?></p>
            <?php else: ?>
                <p class="saw-help-text">Maximální velikost <?php echo esc_html($max_size_mb); ?>MB · Podporované formáty: JPG, PNG, GIF</p>
            <?php endif; ?>
        </div>
    </div>
    
    <input type="hidden" 
           name="<?php echo esc_attr($name); ?>_remove" 
           value="0" 
           class="saw-file-remove-flag">
</div>