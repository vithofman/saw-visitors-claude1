<?php
/**
 * Terminal Step - Language Selection (Unified Design)
 * 
 * @package SAW_Visitors
 * @version 3.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get languages from controller
// Structure: ['cs' => ['name' => 'Čeština', 'flag' => '🇨🇿', 'is_default' => true], ...]
$languages = $languages ?? [];
$has_languages = !empty($languages);
?>
<!-- Žádný <style> blok! CSS je v pages.css -->

<div class="saw-page-aurora saw-step-language">
    <div class="saw-page-content saw-page-content-centered">
        
        <!-- Header -->
        <div class="saw-page-header saw-page-header-centered">
            <div class="saw-header-icon">🌍</div>
            <h1 class="saw-header-title">
                Vyberte jazyk / Choose Language / Оберіть мову
            </h1>
            <p class="saw-header-subtitle">
                Select your preferred language for this session
            </p>
        </div>
        
        <?php if (!$has_languages): ?>
            
            <!-- Error State -->
            <div class="saw-empty-state">
                <div class="saw-empty-state-icon">⚠️</div>
                <p class="saw-empty-state-text">
                    Pro tuto pobočku nejsou nastaveny žádné jazyky.<br>
                    Kontaktujte správce.
                </p>
            </div>
            
        <?php else: ?>
            
            <!-- Language Grid -->
            <form method="POST">
                <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
                <input type="hidden" name="terminal_action" value="set_language">
                
                <div class="saw-selection-grid saw-lang-<?php echo min(count($languages), 6); ?>">
                    <?php foreach ($languages as $code => $lang): ?>
                    <button type="submit" 
                            name="language" 
                            value="<?php echo esc_attr($code); ?>"
                            class="saw-selection-card<?php echo $lang['is_default'] ? ' is-default' : ''; ?>">
                        <span class="saw-card-icon">
                            <?php echo esc_html($lang['flag']); ?>
                        </span>
                        <span class="saw-card-title">
                            <?php echo esc_html($lang['name']); ?>
                        </span>
                        <?php if ($lang['is_default']): ?>
                        <span class="saw-card-badge">Výchozí</span>
                        <?php endif; ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </form>
            
        <?php endif; ?>
        
    </div>
</div>

<?php
error_log("[LANGUAGE.PHP] Unified design loaded (v3.3.0) - " . count($languages) . " languages");
?>