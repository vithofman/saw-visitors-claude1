<?php
/**
 * Invitation Success - PIN Display
 * 
 * ✅ PIN už existuje, jen ho zobrazíme!
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

// Get variables from template context
$flow = $flow ?? [];
$visit_id = $flow['visit_id'] ?? null;
$lang = $flow['language'] ?? 'cs';

if (!$visit_id) {
    wp_die('Chyba: Návštěva nenalezena');
}

// Načti visit s PINem
global $wpdb;
$visit = $wpdb->get_row($wpdb->prepare(
    "SELECT pin_code, planned_date_from, planned_date_to, branch_id, invitation_email 
     FROM {$wpdb->prefix}saw_visits WHERE id = %d",
    $visit_id
), ARRAY_A);

if (!$visit || empty($visit['pin_code'])) {
    wp_die('Chyba: PIN kód nenalezen');
}

$pin = $visit['pin_code'];

// Opravit zobrazení data - použít planned_date_from nebo planned_date_to
$date = 'N/A';
if (!empty($visit['planned_date_from'])) {
    $date = date_i18n('d.m.Y', strtotime($visit['planned_date_from']));
} elseif (!empty($visit['planned_date_to'])) {
    $date = date_i18n('d.m.Y', strtotime($visit['planned_date_to']));
}

// Update status
$wpdb->update(
    $wpdb->prefix . 'saw_visits',
    [
        'status' => 'confirmed',
        'invitation_confirmed_at' => current_time('mysql'),
    ],
    ['id' => $visit_id],
    ['%s', '%s'],
    ['%d']
);

// Odešli reminder email s PINem
if (!empty($visit['invitation_email'])) {
    $subject = 'Připomenutí: Váš PIN kód pro návštěvu';
    $message = "
Dobrý den,

Děkujeme za vyplnění informací o návštěvě.

🔢 VÁŠ PIN KÓD PRO CHECK-IN: {$pin}

Tento kód použijte na terminálu při příchodu na recepci 
dne {$date}.

DŮLEŽITÉ: Poznamenejte si tento PIN kód!

Těšíme se na vás!

";
    
    wp_mail($visit['invitation_email'], $subject, $message);
}

$translations = [
    'cs' => [
        'title' => 'HOTOVO!',
        'subtitle' => 'Registrace byla úspěšně dokončena',
        'pin_title' => 'Váš PIN kód pro check-in:',
        'pin_info' => 'Tento kód použijte při příchodu na recepci',
        'pin_warning' => 'DŮLEŽITÉ: Poznamenejte si tento PIN kód!',
        'email_sent' => 'PIN kód jsme vám také zaslali na email',
        'date_info' => 'Termín návštěvy',
        'close' => 'Zavřít',
    ],
    'en' => [
        'title' => 'DONE!',
        'subtitle' => 'Registration completed successfully',
        'pin_title' => 'Your PIN code for check-in:',
        'pin_info' => 'Use this code at the reception',
        'pin_warning' => 'IMPORTANT: Write down this PIN code!',
        'email_sent' => 'We also sent the PIN code to your email',
        'date_info' => 'Visit date',
        'close' => 'Close',
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
    --color-success: #10b981;
}

.saw-terminal-footer {
    display: none !important;
}

.saw-pin-success-aurora {
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

.saw-pin-success-content {
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

.saw-success-subtitle {
    font-size: 1.25rem;
    color: rgba(203, 213, 225, 0.9);
    font-weight: 500;
    line-height: 1.6;
    margin-bottom: 3rem;
    animation: fadeIn 0.6s ease 0.4s both;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* PIN Display - MEGA DŮRAZNÉ */
.saw-pin-display-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 24px;
    padding: 3rem;
    margin: 3rem 0;
    box-shadow: 
        0 20px 60px rgba(102, 126, 234, 0.5),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
    border: 3px solid rgba(255, 255, 255, 0.2);
    animation: pinGlow 3s ease-in-out infinite;
}

@keyframes pinGlow {
    0%, 100% { 
        box-shadow: 
            0 20px 60px rgba(102, 126, 234, 0.5),
            inset 0 1px 0 rgba(255, 255, 255, 0.2);
    }
    50% { 
        box-shadow: 
            0 20px 80px rgba(102, 126, 234, 0.8),
            inset 0 1px 0 rgba(255, 255, 255, 0.3);
    }
}

.saw-pin-label {
    font-size: 1.125rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.9);
    margin-bottom: 1rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}

.saw-pin-code-huge {
    font-size: 5rem;
    font-weight: 900;
    color: white;
    font-family: 'Courier New', monospace;
    letter-spacing: 0.5rem;
    text-shadow: 
        0 4px 20px rgba(0, 0, 0, 0.3),
        0 0 40px rgba(255, 255, 255, 0.5);
    margin: 1.5rem 0;
    padding: 1rem;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 16px;
}

.saw-pin-info {
    font-size: 1rem;
    color: rgba(255, 255, 255, 0.9);
    margin-top: 1.5rem;
}

/* Warning Box */
.saw-warning-box {
    background: rgba(251, 191, 36, 0.15);
    border: 2px solid rgba(251, 191, 36, 0.5);
    border-radius: 16px;
    padding: 1.5rem;
    margin: 2rem 0;
    display: flex;
    align-items: center;
    gap: 1rem;
    animation: fadeIn 0.6s ease 0.5s both;
}

.saw-warning-icon {
    font-size: 2.5rem;
    flex-shrink: 0;
}

.saw-warning-text {
    text-align: left;
    color: #fbbf24;
    line-height: 1.6;
}

.saw-warning-text strong {
    font-size: 1.125rem;
}

/* Email Info */
.saw-email-info {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.3);
    border-radius: 12px;
    padding: 1rem;
    color: #93c5fd;
    font-size: 0.9375rem;
    margin: 2rem 0;
    animation: fadeIn 0.6s ease 0.6s both;
}

.saw-email-info strong {
    color: white;
}

/* Close Button */
.saw-btn-close {
    margin-top: 3rem;
    padding: 1.25rem 3rem;
    font-size: 1.125rem;
    font-weight: 600;
    background: var(--bg-glass-light);
    color: var(--text-secondary);
    border: 2px solid var(--border-glass);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    animation: fadeIn 0.6s ease 0.7s both;
}

.saw-btn-close:hover {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(148, 163, 184, 0.2);
    transform: translateY(-2px);
}

/* Responsive */
@media (max-width: 768px) {
    .saw-pin-success-content {
        padding: 1rem;
    }
    
    .saw-success-title {
        font-size: 2rem;
    }
    
    .saw-pin-code-huge {
        font-size: 3.5rem;
        letter-spacing: 0.3rem;
    }
    
    .saw-warning-box {
        flex-direction: column;
        text-align: center;
    }
    
    .saw-warning-text {
        text-align: center;
    }
}
</style>

<div class="saw-pin-success-aurora">
    <div class="saw-pin-success-content">
        
        <div class="saw-success-icon-wrapper">
            ✅
        </div>
        
        <h1 class="saw-success-title"><?= esc_html($t['title']) ?></h1>
        <p class="saw-success-subtitle"><?= esc_html($t['subtitle']) ?></p>
        
        <!-- PIN Display - DŮRAZNÉ! -->
        <div class="saw-pin-display-card">
            
            <div class="saw-pin-label">
                <?= esc_html($t['pin_title']) ?>
            </div>
            
            <div class="saw-pin-code-huge">
                <?= esc_html($pin) ?>
            </div>
            
            <div class="saw-pin-info">
                📅 <?= esc_html($t['date_info']) ?>: <strong><?= esc_html($date) ?></strong>
            </div>
            
        </div>
        
        <!-- Warning Box -->
        <div class="saw-warning-box">
            <div class="saw-warning-icon">⚠️</div>
            <div class="saw-warning-text">
                <strong><?= esc_html($t['pin_warning']) ?></strong>
                <br>
                <?= esc_html($t['pin_info']) ?>
            </div>
        </div>
        
        <!-- Email Info -->
        <?php if (!empty($visit['invitation_email'])): ?>
        <div class="saw-email-info">
            📧 <?= esc_html($t['email_sent']) ?>: 
            <strong><?= esc_html($visit['invitation_email']) ?></strong>
        </div>
        <?php endif; ?>
        
        <!-- Close Button -->
        <button onclick="window.location.href='/'" class="saw-btn-close">
            <?= esc_html($t['close']) ?>
        </button>
        
    </div>
</div>


<script>
// Zabránit náhodnému odchodu pouze prvních 30 sekund
let beforeUnloadHandler = function(e) {
    e.preventDefault();
    e.returnValue = 'Ujistěte se, že jste si poznamenali PIN kód!';
    return e.returnValue;
};

window.addEventListener('beforeunload', beforeUnloadHandler);

// Po 30 sekundách už nenabízet warning
setTimeout(function() {
    window.removeEventListener('beforeunload', beforeUnloadHandler);
    beforeUnloadHandler = null;
}, 30000);

// Také odstranit při kliknutí na zavřít
document.querySelector('.saw-btn-close')?.addEventListener('click', function() {
    if (beforeUnloadHandler) {
        window.removeEventListener('beforeunload', beforeUnloadHandler);
        beforeUnloadHandler = null;
    }
});
</script>

