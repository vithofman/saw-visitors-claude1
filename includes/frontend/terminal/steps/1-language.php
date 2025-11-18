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
<style>
/* === UNIFIED STYLE === */
:root {
    --theme-color: #667eea;
    --theme-color-hover: #764ba2;
    --bg-dark: #1a202c;
    --bg-dark-medium: #2d3748;
    --bg-glass: rgba(15, 23, 42, 0.6);
    --bg-glass-light: rgba(255, 255, 255, 0.08);
    --border-glass: rgba(148, 163, 184, 0.12);
    --text-primary: #FFFFFF;
    --text-secondary: #e5e7eb;
    --text-muted: #9ca3af;
}

*,
*::before,
*::after {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

.saw-terminal-footer {
    display: none !important;
}

.saw-language-aurora {
    position: fixed;
    inset: 0;
    width: 100vw;
    height: 100vh;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: var(--text-secondary);
    background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

.saw-language-content {
    max-width: 900px;
    width: 100%;
    animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Header */
.saw-language-header {
    text-align: center;
    margin-bottom: 3rem;
}

.saw-language-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    animation: scaleIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) 0.2s both;
}

@keyframes scaleIn {
    from { opacity: 0; transform: scale(0.8); }
    to { opacity: 1; transform: scale(1); }
}

.saw-language-title {
    font-size: 2rem;
    font-weight: 700;
    background: linear-gradient(135deg, #f9fafb 0%, #cbd5e1 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: -0.02em;
    margin-bottom: 0.75rem;
    line-height: 1.3;
}

.saw-language-subtitle {
    font-size: 1rem;
    color: rgba(203, 213, 225, 0.8);
    font-weight: 500;
}

/* Language Grid */
.saw-language-grid {
    display: grid;
    gap: 1.25rem;
    animation: fadeIn 0.6s ease 0.3s both;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Dynamic grid based on count */
.saw-language-grid.saw-lang-1 {
    grid-template-columns: 1fr;
    max-width: 400px;
    margin: 0 auto;
}

.saw-language-grid.saw-lang-2 {
    grid-template-columns: repeat(2, 1fr);
}

.saw-language-grid.saw-lang-3 {
    grid-template-columns: repeat(3, 1fr);
}

.saw-language-grid.saw-lang-4 {
    grid-template-columns: repeat(2, 1fr);
}

.saw-language-grid.saw-lang-5,
.saw-language-grid.saw-lang-6 {
    grid-template-columns: repeat(3, 1fr);
}

/* Language Button */
.saw-language-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding: 2rem 1.5rem;
    background: var(--bg-glass);
    backdrop-filter: blur(20px) saturate(180%);
    border: 2px solid var(--border-glass);
    border-radius: 20px;
    color: var(--text-primary);
    font-size: 1.125rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
    text-decoration: none;
    min-height: 160px;
}

.saw-language-btn:hover {
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(102, 126, 234, 0.6);
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.3);
}

.saw-language-btn:active {
    transform: translateY(-2px);
}

/* Default language highlight */
.saw-language-btn.is-default {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.2), rgba(118, 75, 162, 0.2));
    border-color: rgba(102, 126, 234, 0.5);
}

.saw-language-btn.is-default:hover {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.3), rgba(118, 75, 162, 0.3));
    border-color: rgba(102, 126, 234, 0.7);
}

.saw-language-flag {
    font-size: 4rem;
    line-height: 1;
    filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.3));
}

.saw-language-name {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
}

.saw-language-badge {
    font-size: 0.8125rem;
    color: rgba(203, 213, 225, 0.8);
    background: rgba(102, 126, 234, 0.2);
    border: 1px solid rgba(102, 126, 234, 0.3);
    padding: 0.25rem 0.625rem;
    border-radius: 6px;
    font-weight: 600;
}

/* Error State */
.saw-language-error {
    background: rgba(239, 68, 68, 0.15);
    border: 2px solid rgba(239, 68, 68, 0.3);
    border-radius: 16px;
    padding: 2rem;
    text-align: center;
    animation: fadeIn 0.6s ease 0.3s both;
}

.saw-language-error-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    display: block;
}

.saw-language-error-message {
    font-size: 1.125rem;
    color: #fca5a5;
    font-weight: 600;
}

/* Responsive */
@media (max-width: 768px) {
    .saw-language-aurora {
        padding: 1rem;
    }
    
    .saw-language-icon {
        font-size: 3rem;
    }
    
    .saw-language-title {
        font-size: 1.5rem;
    }
    
    .saw-language-subtitle {
        font-size: 0.9375rem;
    }
    
    .saw-language-header {
        margin-bottom: 2rem;
    }
    
    .saw-language-grid.saw-lang-3,
    .saw-language-grid.saw-lang-4,
    .saw-language-grid.saw-lang-5,
    .saw-language-grid.saw-lang-6 {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .saw-language-btn {
        padding: 1.5rem 1rem;
        min-height: 140px;
    }
    
    .saw-language-flag {
        font-size: 3rem;
    }
    
    .saw-language-name {
        font-size: 1.125rem;
    }
}

@media (max-width: 480px) {
    .saw-language-grid.saw-lang-2,
    .saw-language-grid.saw-lang-3,
    .saw-language-grid.saw-lang-4,
    .saw-language-grid.saw-lang-5,
    .saw-language-grid.saw-lang-6 {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="saw-language-aurora">
    <div class="saw-language-content">
        
        <!-- Header -->
        <div class="saw-language-header">
            <div class="saw-language-icon">🌍</div>
            <h1 class="saw-language-title">
                Vyberte jazyk / Choose Language / Оберіть мову
            </h1>
            <p class="saw-language-subtitle">
                Select your preferred language for this session
            </p>
        </div>
        
        <?php if (!$has_languages): ?>
            
            <!-- Error State -->
            <div class="saw-language-error">
                <span class="saw-language-error-icon">⚠️</span>
                <p class="saw-language-error-message">
                    Pro tuto pobočku nejsou nastaveny žádné jazyky.<br>
                    Kontaktujte správce.
                </p>
            </div>
            
        <?php else: ?>
            
            <!-- Language Grid -->
            <form method="POST">
                <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
                <input type="hidden" name="terminal_action" value="set_language">
                
                <div class="saw-language-grid saw-lang-<?php echo min(count($languages), 6); ?>">
                    <?php foreach ($languages as $code => $lang): ?>
                    <button type="submit" 
                            name="language" 
                            value="<?php echo esc_attr($code); ?>"
                            class="saw-language-btn<?php echo $lang['is_default'] ? ' is-default' : ''; ?>">
                        <span class="saw-language-flag">
                            <?php echo esc_html($lang['flag']); ?>
                        </span>
                        <span class="saw-language-name">
                            <?php echo esc_html($lang['name']); ?>
                        </span>
                        <?php if ($lang['is_default']): ?>
                        <span class="saw-language-badge">Výchozí</span>
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