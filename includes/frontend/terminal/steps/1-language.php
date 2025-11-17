<?php
/**
 * Terminal Step 1 - Language Selection
 * 
 * First step: Choose interface language
 * Loads languages from database based on customer_id and branch_id
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// $languages is passed from controller - structure:
// ['cs' => ['name' => 'Čeština', 'flag' => '🇨🇿', 'is_default' => true], ...]
?>

<div class="saw-terminal-card">
    <div class="saw-terminal-card-header">
        <h2 class="saw-terminal-card-title">
            🌍 Vyberte jazyk / Choose Language / Оберіть мову
        </h2>
        <p class="saw-terminal-card-subtitle">
            Select your preferred language for this session
        </p>
    </div>
    
    <div class="saw-terminal-card-body">
        <form method="POST" class="saw-terminal-form">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="set_language">
            
            <?php if (empty($languages)): ?>
                <div class="saw-terminal-error">
                    <span class="saw-terminal-error-icon">⚠️</span>
                    <span class="saw-terminal-error-message">
                        Pro tuto pobočku nejsou nastaveny žádné jazyky. Kontaktujte správce.
                    </span>
                </div>
            <?php else: ?>
                
                <div class="saw-terminal-grid-<?php echo count($languages) <= 3 ? count($languages) : '3'; ?>">
                    
                    <?php foreach ($languages as $code => $lang): ?>
                    <button type="submit" 
                            name="language" 
                            value="<?php echo esc_attr($code); ?>" 
                            class="saw-terminal-btn saw-terminal-btn-icon<?php echo $lang['is_default'] ? ' saw-terminal-btn-primary' : ''; ?>">
                        <span class="icon">
                            <?php echo esc_html($lang['flag']); ?>
                        </span>
                        <span><?php echo esc_html($lang['name']); ?></span>
                        <?php if ($lang['is_default']): ?>
                            <small style="font-size: 0.875rem; opacity: 0.8; font-weight: 400;">
                                (výchozí)
                            </small>
                        <?php endif; ?>
                    </button>
                    <?php endforeach; ?>
                    
                </div>
                
            <?php endif; ?>
        </form>
    </div>
</div>