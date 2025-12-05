<?php
/**
 * Terminal Step - Action Choice (Unified Design)
 * 
 * OPRAVENO: Inline styly podle vzoru checkout-method.php
 * 
 * @package SAW_Visitors
 * @version 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$flow = $this->session->get('terminal_flow');
$lang = $flow['language'] ?? 'cs';

$translations = [
    'cs' => [
        'title' => 'Co chcete udělat?',
        'subtitle' => 'Vyberte prosím akci',
        'checkin' => 'Příchod',
        'checkin_full' => 'Check-in',
        'checkin_desc' => 'Registruji se na návštěvu',
        'checkout' => 'Odchod',
        'checkout_full' => 'Check-out',
        'checkout_desc' => 'Končím návštěvu a odcházím',
    ],
    'en' => [
        'title' => 'What would you like to do?',
        'subtitle' => 'Please select an action',
        'checkin' => 'Arrival',
        'checkin_full' => 'Check-in',
        'checkin_desc' => 'I am arriving for a visit',
        'checkout' => 'Departure',
        'checkout_full' => 'Check-out',
        'checkout_desc' => 'I am leaving',
    ],
    'sk' => [
        'title' => 'Čo chcete urobiť?',
        'subtitle' => 'Prosím vyberte akciu',
        'checkin' => 'Príchod',
        'checkin_full' => 'Check-in',
        'checkin_desc' => 'Registrujem sa na návštevu',
        'checkout' => 'Odchod',
        'checkout_full' => 'Check-out',
        'checkout_desc' => 'Končím návštevu a odchádzam',
    ],
    'uk' => [
        'title' => 'Що ви хочете зробити?',
        'subtitle' => 'Будь ласка, виберіть дію',
        'checkin' => 'Прихід',
        'checkin_full' => 'Реєстрація',
        'checkin_desc' => 'Я реєструюся на візит',
        'checkout' => 'Вихід',
        'checkout_full' => 'Виписка',
        'checkout_desc' => 'Я закінчую візит і йду',
    ],
    'de' => [
        'title' => 'Was möchten Sie tun?',
        'subtitle' => 'Bitte wählen Sie eine Aktion',
        'checkin' => 'Ankunft',
        'checkin_full' => 'Check-in',
        'checkin_desc' => 'Ich melde mich für einen Besuch an',
        'checkout' => 'Abreise',
        'checkout_full' => 'Check-out',
        'checkout_desc' => 'Ich beende meinen Besuch',
    ],
    'pl' => [
        'title' => 'Co chcesz zrobić?',
        'subtitle' => 'Proszę wybrać akcję',
        'checkin' => 'Przyjście',
        'checkin_full' => 'Check-in',
        'checkin_desc' => 'Rejestruję się na wizytę',
        'checkout' => 'Wyjście',
        'checkout_full' => 'Check-out',
        'checkout_desc' => 'Kończę wizytę i wychodzę',
    ],
    'vi' => [
        'title' => 'Bạn muốn làm gì?',
        'subtitle' => 'Vui lòng chọn hành động',
        'checkin' => 'Đến',
        'checkin_full' => 'Check-in',
        'checkin_desc' => 'Tôi đang đăng ký cho chuyến thăm',
        'checkout' => 'Đi',
        'checkout_full' => 'Check-out',
        'checkout_desc' => 'Tôi kết thúc chuyến thăm',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];
?>
<style>
/* === ACTION CHOICE - UNIFIED STYLE === */
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

.saw-action-aurora {
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

.saw-action-content {
    max-width: 900px;
    width: 100%;
    animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Header */
.saw-action-header {
    text-align: center;
    margin-bottom: 3rem;
}

.saw-action-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    animation: scaleIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) 0.2s both;
}

@keyframes scaleIn {
    from { opacity: 0; transform: scale(0.8); }
    to { opacity: 1; transform: scale(1); }
}

.saw-action-title {
    font-size: 2.25rem;
    font-weight: 700;
    background: linear-gradient(135deg, #f9fafb 0%, #cbd5e1 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: -0.02em;
    margin-bottom: 0.75rem;
}

.saw-action-subtitle {
    font-size: 1.125rem;
    color: rgba(203, 213, 225, 0.8);
    font-weight: 500;
}

/* Action Grid */
.saw-action-grid {
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

/* Action Button */
.saw-action-btn {
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

.saw-action-btn:hover {
    transform: translateY(-6px);
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.4);
}

.saw-action-btn:active {
    transform: translateY(-3px);
}

/* Check-in (Green) */
.saw-action-btn.is-checkin {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.15));
    border-color: rgba(16, 185, 129, 0.4);
}

.saw-action-btn.is-checkin:hover {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.25), rgba(5, 150, 105, 0.25));
    border-color: rgba(16, 185, 129, 0.6);
}

/* Check-out (Orange) */
.saw-action-btn.is-checkout {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(217, 119, 6, 0.15));
    border-color: rgba(245, 158, 11, 0.4);
}

.saw-action-btn.is-checkout:hover {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.25), rgba(217, 119, 6, 0.25));
    border-color: rgba(245, 158, 11, 0.6);
}

.saw-action-icon-large {
    font-size: 6rem;
    line-height: 1;
    filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.4));
    margin-bottom: 0.5rem;
}

.saw-action-text {
    text-align: center;
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
    width: 100%;
}

.saw-action-name {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.2;
}

.saw-action-name-sub {
    font-size: 1rem;
    font-weight: 500;
    color: rgba(203, 213, 225, 0.6);
    margin-top: -0.25rem;
}

.saw-action-desc {
    font-size: 0.9375rem;
    color: rgba(203, 213, 225, 0.85);
    font-weight: 500;
    line-height: 1.5;
    padding: 0 0.5rem;
}

/* Responsive */
@media (max-width: 768px) {
    .saw-action-aurora {
        padding: 1.5rem;
    }
    
    .saw-action-icon {
        font-size: 3rem;
    }
    
    .saw-action-title {
        font-size: 1.75rem;
    }
    
    .saw-action-subtitle {
        font-size: 1rem;
    }
    
    .saw-action-header {
        margin-bottom: 2rem;
    }
    
    .saw-action-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .saw-action-btn {
        padding: 2rem 1.5rem;
        min-height: 280px;
    }
    
    .saw-action-icon-large {
        font-size: 4.5rem;
    }
    
    .saw-action-name {
        font-size: 1.75rem;
    }
    
    .saw-action-desc {
        font-size: 0.875rem;
    }
}
</style>

<div class="saw-action-aurora">
    <div class="saw-action-content">
        
        <!-- Header -->
        <div class="saw-action-header">
            <div class="saw-action-icon">❓</div>
            <h1 class="saw-action-title"><?php echo esc_html($t['title']); ?></h1>
            <p class="saw-action-subtitle"><?php echo esc_html($t['subtitle']); ?></p>
        </div>
        
        <!-- Action Grid -->
        <form method="POST" class="saw-action-grid">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="set_action">
            
            <!-- Check-in Button -->
            <button type="submit" 
                    name="action_type" 
                    value="checkin" 
                    class="saw-action-btn is-checkin">
                <span class="saw-action-icon-large">✅</span>
                <div class="saw-action-text">
                    <div class="saw-action-name"><?php echo esc_html($t['checkin']); ?></div>
                    <div class="saw-action-name-sub">(<?php echo esc_html($t['checkin_full']); ?>)</div>
                    <div class="saw-action-desc"><?php echo esc_html($t['checkin_desc']); ?></div>
                </div>
            </button>
            
            <!-- Check-out Button -->
            <button type="submit" 
                    name="action_type" 
                    value="checkout" 
                    class="saw-action-btn is-checkout">
                <span class="saw-action-icon-large">🚪</span>
                <div class="saw-action-text">
                    <div class="saw-action-name"><?php echo esc_html($t['checkout']); ?></div>
                    <div class="saw-action-name-sub">(<?php echo esc_html($t['checkout_full']); ?>)</div>
                    <div class="saw-action-desc"><?php echo esc_html($t['checkout_desc']); ?></div>
                </div>
            </button>
            
        </form>
        
    </div>
</div>

<?php
error_log("[ACTION.PHP] Unified design loaded (v4.0.0)");
?>