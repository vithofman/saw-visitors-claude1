<?php
/**
 * Terminal Step 1 - Language Selection
 * 
 * First step: Choose interface language
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
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
            
            <div class="saw-terminal-grid-3">
                
                <?php foreach ($languages as $code => $name): ?>
                <button type="submit" 
                        name="language" 
                        value="<?php echo esc_attr($code); ?>" 
                        class="saw-terminal-btn saw-terminal-btn-icon">
                    <span class="icon">
                        <?php
                        $flags = [
                            'cs' => '🇨🇿',
                            'en' => '🇬🇧',
                            'uk' => '🇺🇦',
                        ];
                        echo $flags[$code] ?? '🌐';
                        ?>
                    </span>
                    <span><?php echo esc_html($name); ?></span>
                </button>
                <?php endforeach; ?>
                
            </div>
        </form>
    </div>
</div>
