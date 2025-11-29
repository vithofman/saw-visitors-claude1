<?php
/**
 * Terminal Step - Language Selection (Unified Design)
 * 
 * @package SAW_Visitors
 * @version 4.0.0
 */
if (!defined('ABSPATH')) {
    exit;
}

$languages = $languages ?? [];
$has_languages = !empty($languages);
$lang_count = min(count($languages), 4);
?>

<div class="saw-page-aurora saw-step-language">
    <div class="saw-page-content">
        
        <div class="saw-page-header">
            <div class="saw-header-icon">🌍</div>
            <h1 class="saw-header-title">
                Vyberte jazyk / Choose Language / Оберіть мову
            </h1>
            <p class="saw-header-subtitle">
                Select your preferred language for this session
            </p>
        </div>
        
        <?php if (!$has_languages): ?>
            <div class="saw-empty-state">
                <div class="saw-empty-state-icon">⚠️</div>
                <p class="saw-empty-state-text">
                    Pro tuto pobočku nejsou nastaveny žádné jazyky.<br>
                    Kontaktujte správce.
                </p>
            </div>
        <?php else: ?>
            <form method="POST">
                <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
                <input type="hidden" name="terminal_action" value="set_language">
                
                <div class="saw-card-grid saw-card-grid-<?php echo $lang_count; ?>">
                    <?php foreach ($languages as $code => $lang): ?>
                    <button type="submit" 
                            name="language" 
                            value="<?php echo esc_attr($code); ?>"
                            class="saw-card-selection">
                        <span class="saw-card-selection-icon">
                            <?php echo esc_html($lang['flag']); ?>
                        </span>
                        <span class="saw-card-selection-title">
                            <?php echo esc_html($lang['name']); ?>
                        </span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </form>
        <?php endif; ?>
        
    </div>
</div>