<?php
/**
 * Terminal Step - Success Confirmation (Unified Design)
 * 
 * @package SAW_Visitors
 * @version 3.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$flow = $this->session->get('terminal_flow');
$lang = $flow['language'] ?? 'cs';

// Zjištění akce (checkout vs checkin)
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : ($flow['action'] ?? 'checkin');

$translations = [
    'cs' => [
        'checkin_title' => 'Úspěšně přihlášeno!',
        'checkin_message' => 'Vaše návštěva byla zaregistrována. Přejeme příjemný den.',
        'checkout_title' => 'Úspěšně odhlášeno!',
        'checkout_message' => 'Děkujeme za návštěvu. Přejeme šťastnou cestu.',
        'back_btn' => 'Zpět na začátek',
        'auto_redirect' => 'Automatické přesměrování za 5 sekund...',
        'checkin_info' => 'Při odchodu se prosím opět odhlaste na tomto terminálu.',
        'important' => 'Důležité',
    ],
    'en' => [
        'checkin_title' => 'Successfully Checked In!',
        'checkin_message' => 'Your visit has been registered. Have a great day.',
        'checkout_title' => 'Successfully Checked Out!',
        'checkout_message' => 'Thank you for your visit. Have a safe journey.',
        'back_btn' => 'Back to Start',
        'auto_redirect' => 'Automatic redirect in 5 seconds...',
        'checkin_info' => 'Please check out at this terminal when leaving.',
        'important' => 'Important',
    ],
    'sk' => [
        'checkin_title' => 'Úspešne prihlásený!',
        'checkin_message' => 'Vaša návšteva bola zaregistrovaná. Prajeme príjemný deň.',
        'checkout_title' => 'Úspešne odhlásený!',
        'checkout_message' => 'Ďakujeme za návštevu. Prajeme šťastnú cestu.',
        'back_btn' => 'Späť na začiatok',
        'auto_redirect' => 'Automatické presmerovanie za 5 sekúnd...',
        'checkin_info' => 'Pri odchode sa prosím opäť odhláste na tomto terminále.',
        'important' => 'Dôležité',
    ],
    'uk' => [
        'checkin_title' => 'Успішно зареєстровано!',
        'checkin_message' => 'Ваш візит зареєстровано. Гарного дня.',
        'checkout_title' => 'Успішно виписано!',
        'checkout_message' => 'Дякуємо за візит. Щасливої дороги.',
        'back_btn' => 'Повернутися на початок',
        'auto_redirect' => 'Автоматичне перенаправлення через 5 секунд...',
        'checkin_info' => 'Будь ласка, виписуйтесь на цьому терміналі при виході.',
        'important' => 'Важливо',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];
$is_checkin = ($action === 'checkin');
$title = $is_checkin ? $t['checkin_title'] : $t['checkout_title'];
$message = $is_checkin ? $t['checkin_message'] : $t['checkout_message'];
$icon = $is_checkin ? '✅' : '👋';
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
    --color-success: #10b981;
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

.saw-success-aurora {
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

.saw-success-content {
    max-width: 600px;
    width: 100%;
    text-align: center;
    animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

.saw-success-icon-wrapper {
    width: 8rem;
    height: 8rem;
    margin: 0 auto 2rem;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--color-success) 0%, #059669 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
    box-shadow: 
        0 20px 60px rgba(16, 185, 129, 0.4),
        inset 0 2px 0 rgba(255, 255, 255, 0.2);
    position: relative;
    animation: scaleIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) 0.2s both;
}

@keyframes scaleIn {
    from { opacity: 0; transform: scale(0.8); }
    to { opacity: 1; transform: scale(1); }
}

.saw-success-icon-wrapper::before {
    content: "";
    position: absolute;
    inset: -4px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.4), transparent);
    z-index: -1;
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 0.5; transform: scale(1); }
    50% { opacity: 0.8; transform: scale(1.05); }
}

.saw-success-title {
    font-size: 2.5rem;
    font-weight: 700;
    background: linear-gradient(135deg, #f9fafb 0%, #cbd5e1 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: -0.02em;
    margin-bottom: 1rem;
    animation: fadeIn 0.6s ease 0.3s both;
}

.saw-success-message {
    font-size: 1.25rem;
    color: rgba(203, 213, 225, 0.9);
    font-weight: 500;
    line-height: 1.6;
    margin-bottom: 2rem;
    animation: fadeIn 0.6s ease 0.4s both;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.saw-success-info {
    background: rgba(59, 130, 246, 0.15);
    border: 1px solid rgba(59, 130, 246, 0.3);
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    animation: fadeIn 0.6s ease 0.5s both;
}

.saw-success-info-title {
    font-size: 1rem;
    font-weight: 600;
    color: #60a5fa;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.saw-success-info-text {
    font-size: 0.9375rem;
    color: rgba(203, 213, 225, 0.9);
    line-height: 1.6;
}

.saw-success-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 1rem 2.5rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 16px;
    font-weight: 700;
    font-size: 1.125rem;
    cursor: pointer;
    text-decoration: none;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    animation: fadeIn 0.6s ease 0.6s both;
}

.saw-success-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 40px rgba(102, 126, 234, 0.6);
}

.saw-success-redirect {
    margin-top: 2rem;
    font-size: 0.875rem;
    color: var(--text-muted);
    animation: fadeIn 0.6s ease 0.7s both;
}

@media (max-width: 768px) {
    .saw-success-icon-wrapper {
        width: 6rem;
        height: 6rem;
        font-size: 3rem;
    }
    
    .saw-success-title {
        font-size: 2rem;
    }
    
    .saw-success-message {
        font-size: 1.125rem;
    }
    
    .saw-success-btn {
        width: 100%;
        padding: 1rem;
    }
}
</style>

<div class="saw-success-aurora">
    <div class="saw-success-content">
        
        <div class="saw-success-icon-wrapper">
            <?php echo $icon; ?>
        </div>
        
        <h1 class="saw-success-title">
            <?php echo esc_html($title); ?>
        </h1>
        
        <p class="saw-success-message">
            <?php echo esc_html($message); ?>
        </p>
        
        <?php if ($is_checkin): ?>
        <div class="saw-success-info">
            <div class="saw-success-info-title">
                <span>ℹ️</span>
                <span><?php echo esc_html($t['important']); ?></span>
            </div>
            <p class="saw-success-info-text">
                <?php echo esc_html($t['checkin_info']); ?>
            </p>
        </div>
        <?php endif; ?>
        
        <a href="<?php echo home_url('/terminal/'); ?>" 
           class="saw-success-btn">
            <?php echo esc_html($t['back_btn']); ?> →
        </a>
        
        <p class="saw-success-redirect">
            <?php echo esc_html($t['auto_redirect']); ?>
        </p>
        
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Auto-redirect to start after 5 seconds
    setTimeout(function() {
        window.location.href = '<?php echo home_url('/terminal/'); ?>';
    }, 5000);
});
</script>