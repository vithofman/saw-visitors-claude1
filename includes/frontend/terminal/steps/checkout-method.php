<?php
/**
 * Terminal Step - Checkout Method Selection (Unified Design)
 * 
 * @package SAW_Visitors
 * @version 3.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$flow = $this->session->get('terminal_flow');
$lang = $flow['language'] ?? 'cs';

$translations = [
    'cs' => [
        'title' => 'Jak se chcete odhlásit?',
        'subtitle' => 'Vyberte způsob odhlášení',
        'pin' => 'Mám PIN kód',
        'pin_desc' => 'Zadám PIN a vyberu všechny odcházející',
        'search' => 'Vyhledat mě',
        'search_desc' => 'Najdu se podle jména',
    ],
    'en' => [
        'title' => 'How would you like to check out?',
        'subtitle' => 'Select checkout method',
        'pin' => 'I have a PIN code',
        'pin_desc' => 'I will enter PIN and select all departing visitors',
        'search' => 'Find me',
        'search_desc' => 'I will search by my name',
    ],
    'sk' => [
        'title' => 'Ako sa chcete odhlásiť?',
        'subtitle' => 'Vyberte spôsob odhlásenia',
        'pin' => 'Mám PIN kód',
        'pin_desc' => 'Zadám PIN a vyberiem všetkých odchádzajúcich',
        'search' => 'Vyhľadať ma',
        'search_desc' => 'Nájdem sa podľa mena',
    ],
    'uk' => [
        'title' => 'Як ви хочете виписатися?',
        'subtitle' => 'Виберіть спосіб виписки',
        'pin' => 'У мене є PIN-код',
        'pin_desc' => 'Я введу PIN і виберу всіх, хто від\'їжджає',
        'search' => 'Знайдіть мене',
        'search_desc' => 'Я буду шукати за своїм ім\'ям',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];
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

.saw-checkout-method-aurora {
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

.saw-checkout-method-content {
    max-width: 900px;
    width: 100%;
    animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Header */
.saw-checkout-method-header {
    text-align: center;
    margin-bottom: 3rem;
}

.saw-checkout-method-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    animation: scaleIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) 0.2s both;
}

@keyframes scaleIn {
    from { opacity: 0; transform: scale(0.8); }
    to { opacity: 1; transform: scale(1); }
}

.saw-checkout-method-title {
    font-size: 2.25rem;
    font-weight: 700;
    background: linear-gradient(135deg, #f9fafb 0%, #cbd5e1 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: -0.02em;
    margin-bottom: 0.75rem;
}

.saw-checkout-method-subtitle {
    font-size: 1.125rem;
    color: rgba(203, 213, 225, 0.8);
    font-weight: 500;
}

/* Method Grid */
.saw-checkout-method-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 3rem;
    animation: fadeIn 0.6s ease 0.3s both;
    max-width: 800px;
    margin: 0 auto;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Method Button */
.saw-checkout-method-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1.5rem;
    padding: 3rem 2.5rem;
    background: var(--bg-glass);
    backdrop-filter: blur(20px) saturate(180%);
    border: 2px solid var(--border-glass);
    border-radius: 20px;
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    text-decoration: none;
    min-height: 360px;
    width: 100%;
}

.saw-checkout-method-btn:hover {
    transform: translateY(-6px);
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.4);
}

.saw-checkout-method-btn:active {
    transform: translateY(-3px);
}

/* PIN Method (Purple) */
.saw-checkout-method-btn.is-pin {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.15), rgba(118, 75, 162, 0.15));
    border-color: rgba(102, 126, 234, 0.4);
}

.saw-checkout-method-btn.is-pin:hover {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.25), rgba(118, 75, 162, 0.25));
    border-color: rgba(102, 126, 234, 0.6);
}

/* Search Method (Orange) */
.saw-checkout-method-btn.is-search {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(217, 119, 6, 0.15));
    border-color: rgba(245, 158, 11, 0.4);
}

.saw-checkout-method-btn.is-search:hover {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.25), rgba(217, 119, 6, 0.25));
    border-color: rgba(245, 158, 11, 0.6);
}

.saw-checkout-method-icon-large {
    font-size: 6rem;
    line-height: 1;
    filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.4));
    margin-bottom: 0.5rem;
}

.saw-checkout-method-text {
    text-align: center;
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
    width: 100%;
}

.saw-checkout-method-name {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.2;
}

.saw-checkout-method-desc {
    font-size: 0.9375rem;
    color: rgba(203, 213, 225, 0.85);
    font-weight: 500;
    line-height: 1.5;
    padding: 0 0.5rem;
}

/* Responsive */
@media (max-width: 768px) {
    .saw-checkout-method-aurora {
        padding: 1.5rem;
    }
    
    .saw-checkout-method-icon {
        font-size: 3rem;
    }
    
    .saw-checkout-method-title {
        font-size: 1.75rem;
    }
    
    .saw-checkout-method-subtitle {
        font-size: 1rem;
    }
    
    .saw-checkout-method-header {
        margin-bottom: 2rem;
    }
    
    .saw-checkout-method-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .saw-checkout-method-btn {
        padding: 2rem 1.5rem;
        min-height: 280px;
    }
    
    .saw-checkout-method-icon-large {
        font-size: 4.5rem;
    }
    
    .saw-checkout-method-name {
        font-size: 1.75rem;
    }
    
    .saw-checkout-method-desc {
        font-size: 0.875rem;
    }
}
</style>

<div class="saw-checkout-method-aurora">
    <div class="saw-checkout-method-content">
        
        <!-- Header -->
        <div class="saw-checkout-method-header">
            <div class="saw-checkout-method-icon">🚪</div>
            <h1 class="saw-checkout-method-title"><?php echo esc_html($t['title']); ?></h1>
            <p class="saw-checkout-method-subtitle"><?php echo esc_html($t['subtitle']); ?></p>
        </div>
        
        <!-- Method Grid -->
        <div class="saw-checkout-method-grid">
            
            <!-- PIN Method -->
            <a href="<?php echo home_url('/terminal/checkout-pin/'); ?>" 
               class="saw-checkout-method-btn is-pin">
                <span class="saw-checkout-method-icon-large">🔐</span>
                <div class="saw-checkout-method-text">
                    <div class="saw-checkout-method-name"><?php echo esc_html($t['pin']); ?></div>
                    <div class="saw-checkout-method-desc"><?php echo esc_html($t['pin_desc']); ?></div>
                </div>
            </a>
            
            <!-- Search Method -->
            <a href="<?php echo home_url('/terminal/checkout-search/'); ?>" 
               class="saw-checkout-method-btn is-search">
                <span class="saw-checkout-method-icon-large">🔍</span>
                <div class="saw-checkout-method-text">
                    <div class="saw-checkout-method-name"><?php echo esc_html($t['search']); ?></div>
                    <div class="saw-checkout-method-desc"><?php echo esc_html($t['search_desc']); ?></div>
                </div>
            </a>
            
        </div>
        
    </div>
</div>

<?php
error_log("[CHECKOUT-METHOD.PHP] Unified design loaded (v3.3.0)");
?>