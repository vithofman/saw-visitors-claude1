<?php
/**
 * Settings Module View
 *
 * @package SAW_Visitors
 * @version 2.0.0 - Wide layout with tabs
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="saw-settings-page">
    
    <div class="saw-page-header">
        <h1 class="saw-page-title">
            <span class="saw-page-icon"><?php echo esc_html($icon); ?></span>
            Nastavení
        </h1>
    </div>
    
    <?php if ($flash): ?>
        <div class="saw-flash-message saw-flash-<?php echo esc_attr($flash['type']); ?>">
            <?php echo esc_html($flash['message']); ?>
        </div>
    <?php endif; ?>
    
    <div class="saw-settings-tabs">
        <button type="button" class="saw-tab-btn active" data-tab="general">
            ℹ️ Základní
        </button>
        <?php if ($role === 'admin'): ?>
            <button type="button" class="saw-tab-btn" data-tab="company">
                🏢 Nastavení firmy
            </button>
        <?php endif; ?>
    </div>
    
    <div class="saw-settings-content">
        
        <div class="saw-tab-content" data-tab-content="general">
            <div class="saw-form-section">
                <h2 class="saw-section-title">🎨 Vzhled</h2>
                
                <div class="saw-form-field">
                    <label class="saw-checkbox-label saw-dark-mode-toggle-label">
                        <input 
                            type="checkbox" 
                            id="saw-dark-mode-toggle" 
                            class="saw-dark-mode-toggle"
                            <?php 
                            $user_id = get_current_user_id();
                            $dark_mode = get_user_meta($user_id, 'saw_dark_mode', true);
                            checked($dark_mode, '1');
                            ?>
                        >
                        <span>
                            <strong>Tmavý režim</strong>
                            <span class="saw-hint">Terminal/Azura glossy styl</span>
                        </span>
                    </label>
                </div>
            </div>
        </div>
        
        <?php if ($role === 'admin' && $customer): ?>
        <div class="saw-tab-content" data-tab-content="company" style="display: none;">
        
        <form method="post" action="" enctype="multipart/form-data" class="saw-settings-form">
            <?php wp_nonce_field('saw_settings_save'); ?>
            
            <div class="saw-form-grid">
                
                <div class="saw-form-column">
                    <div class="saw-form-section">
                        <h2 class="saw-section-title">📋 Základní informace</h2>
                        
                        <div class="saw-form-field">
                            <label class="saw-label required">Název firmy</label>
                            <input 
                                type="text" 
                                name="name" 
                                value="<?php echo esc_attr($customer['name']); ?>" 
                                class="saw-input"
                                required
                            >
                        </div>
                        
                        <div class="saw-form-row">
                            <div class="saw-form-field">
                                <label class="saw-label">IČO</label>
                                <input 
                                    type="text" 
                                    name="ico" 
                                    value="<?php echo esc_attr($customer['ico'] ?? ''); ?>" 
                                    class="saw-input"
                                    placeholder="12345678"
                                    maxlength="8"
                                >
                                <span class="saw-hint">8 číslic</span>
                            </div>
                            
                            <div class="saw-form-field">
                                <label class="saw-label">DIČ</label>
                                <input 
                                    type="text" 
                                    name="dic" 
                                    value="<?php echo esc_attr($customer['dic'] ?? ''); ?>" 
                                    class="saw-input"
                                    placeholder="CZ12345678"
                                    maxlength="12"
                                >
                                <span class="saw-hint">Formát: CZ12345678</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="saw-form-section">
                        <h2 class="saw-section-title">ℹ️ Metadata</h2>
                        
                        <div class="saw-metadata">
                            <div class="saw-meta-item">
                                <span class="saw-meta-label">ID zákazníka:</span>
                                <span class="saw-meta-value"><?php echo esc_html($customer['id']); ?></span>
                            </div>
                            <div class="saw-meta-item">
                                <span class="saw-meta-label">Vytvořeno:</span>
                                <span class="saw-meta-value">
                                    <?php echo esc_html(date('d.m.Y H:i', strtotime($customer['created_at']))); ?>
                                </span>
                            </div>
                            <?php if ($customer['updated_at']): ?>
                                <div class="saw-meta-item">
                                    <span class="saw-meta-label">Poslední úprava:</span>
                                    <span class="saw-meta-value">
                                        <?php echo esc_html(date('d.m.Y H:i', strtotime($customer['updated_at']))); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="saw-form-column">
                    <div class="saw-form-section">
                        <h2 class="saw-section-title">🎨 Vizuální identita</h2>
                        
                        <div class="saw-form-field">
                            <label class="saw-label">Logo firmy</label>
                            <?php
                            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/class-saw-file-uploader.php';
                            
                            $id = 'logo';
                            $name = 'logo';
                            $config = array();
                            $current_file_url = $customer['logo_url'] ?? '';
                            $label = 'Nahrát logo';
                            $current_label = 'Současné logo';
                            $help_text = '';
                            $accept = 'image/jpeg,image/png,image/gif,image/webp';
                            $show_preview = true;
                            $custom_class = '';
                            
                            require SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/file-upload-input.php';
                            ?>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <div class="saw-form-actions">
                <button type="submit" class="saw-btn-primary">
                    💾 Uložit změny
                </button>
            </div>
        
        </form>
        
        </div>
        <?php endif; ?>
        
    </div>
    
</div>

<script>
jQuery(document).ready(function($) {
    $('.saw-tab-btn').on('click', function() {
        if ($(this).prop('disabled')) return;
        
        $('.saw-tab-btn').removeClass('active');
        $(this).addClass('active');
        
        const tab = $(this).data('tab');
        $('.saw-tab-content').hide();
        $('[data-tab-content="' + tab + '"]').show();
    });
});
</script>
