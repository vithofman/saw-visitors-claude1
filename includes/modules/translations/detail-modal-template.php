<?php
/**
 * Translations Detail Sidebar Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Translations
 * @version     3.0.0 - Added inline styles like visits module
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// TRANSLATIONS SETUP
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}
$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'translations') 
    : [];

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// VALIDATION
// ============================================
if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">' . esc_html($tr('error_not_found', 'Překlad nebyl nalezen')) . '</div>';
    return;
}

// Context labels with colors
$context_config = array(
    'terminal' => array(
        'label' => '🖥️ Terminal',
        'badge' => 'saw-badge-primary',
    ),
    'invitation' => array(
        'label' => '📧 Pozvánka',
        'badge' => 'saw-badge-info',
    ),
    'admin' => array(
        'label' => '⚙️ Admin',
        'badge' => 'saw-badge-warning',
    ),
    'common' => array(
        'label' => '🌐 Společné',
        'badge' => 'saw-badge-secondary',
    ),
    'email' => array(
        'label' => '📧 Email',
        'badge' => 'saw-badge-success',
    ),
);

$lang_labels = array(
    'cs' => '🇨🇿 Čeština',
    'en' => '🇬🇧 English',
    'de' => '🇩🇪 Deutsch',
    'sk' => '🇸🇰 Slovenčina',
);

$current_context = $context_config[$item['context']] ?? array('label' => $item['context'], 'badge' => 'saw-badge-secondary');
?>

<style>
/* ============================================
   GLOBAL OVERFLOW FIX
   ============================================ */
.saw-industrial-section,
.saw-section-body,
.saw-info-grid,
.saw-detail-cards-stack {
    max-width: 100%;
    overflow: hidden;
}

/* ============================================
   DETAIL CARDS STACK
   ============================================ */
.saw-detail-cards-stack {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin: 16px 0;
    width: 100%;
    max-width: 100%;
    overflow: hidden;
    font-family: 'Roboto', sans-serif !important;
    color: #1a1a1a !important;
}

/* Translation Key Card */
.saw-translation-key-card {
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    border-radius: 14px;
    padding: 2px;
    margin-bottom: 0;
    width: 100%;
    box-sizing: border-box;
    max-width: 100%;
    overflow: hidden;
}

.saw-translation-key-card-inner {
    background: #ffffff;
    border-radius: 12px;
    padding: 16px;
    display: flex;
    gap: 14px;
    align-items: flex-start;
}

.saw-translation-key-icon {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6366f1;
    flex-shrink: 0;
}

.saw-translation-key-icon svg {
    width: 24px;
    height: 24px;
}

.saw-translation-key-content {
    flex: 1;
    min-width: 0;
    overflow: hidden;
}

.saw-translation-key-label {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}

.saw-translation-key-value {
    font-size: 14px;
    color: #1e293b;
    line-height: 1.5;
    word-break: break-word;
    overflow-wrap: break-word;
}

.saw-translation-key-value code {
    background: #f1f5f9;
    padding: 4px 8px;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
    color: #6366f1;
    font-weight: 600;
}

/* Translation Text Card */
.saw-translation-text-card {
    background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
    border-radius: 14px;
    padding: 2px;
    margin-bottom: 0;
    width: 100%;
    box-sizing: border-box;
    max-width: 100%;
    overflow: hidden;
}

.saw-translation-text-card-inner {
    background: #ffffff;
    border-radius: 12px;
    padding: 16px;
    display: flex;
    gap: 14px;
    align-items: flex-start;
}

.saw-translation-text-icon {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0ea5e9;
    flex-shrink: 0;
}

.saw-translation-text-icon svg {
    width: 24px;
    height: 24px;
}

.saw-translation-text-content {
    flex: 1;
    min-width: 0;
    overflow: hidden;
}

.saw-translation-text-label {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}

.saw-translation-text-value {
    font-size: 15px;
    color: #1e293b;
    line-height: 1.6;
    word-break: break-word;
    overflow-wrap: break-word;
    white-space: pre-wrap;
}

/* Description Card */
.saw-description-card {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    border-radius: 14px;
    padding: 2px;
    margin-bottom: 0;
    width: 100%;
    box-sizing: border-box;
    max-width: 100%;
    overflow: hidden;
}

.saw-description-card-inner {
    background: #ffffff;
    border-radius: 12px;
    padding: 16px;
    display: flex;
    gap: 14px;
    align-items: flex-start;
}

.saw-description-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #8b5cf6;
    flex-shrink: 0;
}

.saw-description-icon svg {
    width: 20px;
    height: 20px;
}

.saw-description-content {
    flex: 1;
    min-width: 0;
    overflow: hidden;
}

.saw-description-label {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}

.saw-description-text {
    font-size: 14px;
    color: #1e293b;
    line-height: 1.6;
    word-break: break-word;
    overflow-wrap: break-word;
    white-space: pre-wrap;
}

/* Placeholders Card */
.saw-placeholders-card {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    border-radius: 14px;
    padding: 2px;
    margin-bottom: 0;
    width: 100%;
    box-sizing: border-box;
    max-width: 100%;
    overflow: hidden;
}

.saw-placeholders-card-inner {
    background: #ffffff;
    border-radius: 12px;
    padding: 16px;
    display: flex;
    gap: 14px;
    align-items: flex-start;
}

.saw-placeholders-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #f59e0b;
    flex-shrink: 0;
}

.saw-placeholders-icon svg {
    width: 20px;
    height: 20px;
}

.saw-placeholders-content {
    flex: 1;
    min-width: 0;
    overflow: hidden;
}

.saw-placeholders-label {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}

.saw-placeholders-value {
    font-size: 14px;
    color: #1e293b;
    line-height: 1.5;
    word-break: break-word;
    overflow-wrap: break-word;
}

.saw-placeholders-value code {
    background: #fef3c7;
    padding: 4px 8px;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
    color: #d97706;
    font-weight: 600;
}

/* Mobile adjustments */
@media (max-width: 480px) {
    .saw-translation-key-card-inner,
    .saw-translation-text-card-inner,
    .saw-description-card-inner,
    .saw-placeholders-card-inner {
        flex-direction: column;
    }
    
    .saw-translation-key-icon,
    .saw-translation-text-icon,
    .saw-description-icon,
    .saw-placeholders-icon {
        width: 36px;
        height: 36px;
    }
}
</style>

<div class="saw-detail-wrapper">
    <div class="saw-detail-cards-stack">
        
        <!-- TRANSLATION KEY CARD -->
        <div class="saw-translation-key-card">
            <div class="saw-translation-key-card-inner">
                <div class="saw-translation-key-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>
                    </svg>
                </div>
                <div class="saw-translation-key-content">
                    <div class="saw-translation-key-label"><?php echo esc_html($tr('field_translation_key', 'Klíč překladu')); ?></div>
                    <div class="saw-translation-key-value">
                        <code><?php echo esc_html($item['translation_key']); ?></code>
                    </div>
                </div>
            </div>
        </div>

        <!-- TRANSLATION TEXT CARD -->
        <div class="saw-translation-text-card">
            <div class="saw-translation-text-card-inner">
                <div class="saw-translation-text-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                        <line x1="10" y1="9" x2="8" y2="9"/>
                    </svg>
                </div>
                <div class="saw-translation-text-content">
                    <div class="saw-translation-text-label"><?php echo esc_html($tr('section_translation', 'Text překladu')); ?></div>
                    <div class="saw-translation-text-value">
                        <?php echo nl2br(esc_html($item['translation_text'])); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- BASIC INFORMATION -->
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title saw-section-title-accent">ℹ️ <?php echo esc_html($tr('section_basic', 'Základní informace')); ?></h4>
            </div>
            <div class="saw-section-body">
                <div class="saw-info-grid">
                    <div class="saw-info-item">
                        <label><?php echo esc_html($tr('field_language_code', 'Jazyk')); ?></label>
                        <span><?php echo esc_html($lang_labels[$item['language_code']] ?? $item['language_code']); ?></span>
                    </div>
                    
                    <div class="saw-info-item">
                        <label><?php echo esc_html($tr('field_context', 'Kontext')); ?></label>
                        <span>
                            <span class="saw-badge <?php echo esc_attr($current_context['badge']); ?>">
                                <?php echo esc_html($current_context['label']); ?>
                            </span>
                        </span>
                    </div>
                    
                    <?php if (!empty($item['section'])): ?>
                    <div class="saw-info-item">
                        <label><?php echo esc_html($tr('field_section', 'Sekce')); ?></label>
                        <span>
                            <span class="saw-badge saw-badge-secondary">
                                <?php echo esc_html($item['section']); ?>
                            </span>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ADDITIONAL INFORMATION -->
        <?php if (!empty($item['description']) || !empty($item['placeholders'])): ?>
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title saw-section-title-accent">📝 <?php echo esc_html($tr('section_additional', 'Další informace')); ?></h4>
            </div>
            <div class="saw-section-body">
                <?php if (!empty($item['description'])): ?>
                <div class="saw-description-card">
                    <div class="saw-description-card-inner">
                        <div class="saw-description-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M12 16v-4M12 8h.01"/>
                            </svg>
                        </div>
                        <div class="saw-description-content">
                            <div class="saw-description-label"><?php echo esc_html($tr('field_description', 'Popis')); ?></div>
                            <div class="saw-description-text"><?php echo nl2br(esc_html($item['description'])); ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($item['placeholders'])): ?>
                <div class="saw-placeholders-card">
                    <div class="saw-placeholders-card-inner">
                        <div class="saw-placeholders-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>
                            </svg>
                        </div>
                        <div class="saw-placeholders-content">
                            <div class="saw-placeholders-label"><?php echo esc_html($tr('field_placeholders', 'Placeholdery')); ?></div>
                            <div class="saw-placeholders-value">
                                <code><?php echo esc_html($item['placeholders']); ?></code>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- METADATA -->
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title saw-section-title-accent">🕒 <?php echo esc_html($tr('section_metadata', 'Metadata')); ?></h4>
            </div>
            <div class="saw-section-body">
                <div class="saw-info-grid">
                    <div class="saw-info-item">
                        <label><?php echo esc_html($tr('field_created_at', 'Vytvořeno')); ?></label>
                        <span><?php echo !empty($item['created_at']) ? date_i18n('d.m.Y H:i', strtotime($item['created_at'])) : '—'; ?></span>
                    </div>
                    
                    <?php if (!empty($item['updated_at'])): ?>
                    <div class="saw-info-item">
                        <label><?php echo esc_html($tr('field_updated_at', 'Upraveno')); ?></label>
                        <span><?php echo date_i18n('d.m.Y H:i', strtotime($item['updated_at'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
