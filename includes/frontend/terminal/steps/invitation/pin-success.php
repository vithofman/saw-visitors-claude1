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
    "SELECT pin_code, planned_date_from, branch_id, invitation_email 
     FROM {$wpdb->prefix}saw_visits WHERE id = %d",
    $visit_id
), ARRAY_A);

if (!$visit || empty($visit['pin_code'])) {
    wp_die('Chyba: PIN kód nenalezen');
}

$pin = $visit['pin_code'];
$date = !empty($visit['planned_date_from']) ? date('d.m.Y', strtotime($visit['planned_date_from'])) : 'N/A';

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
<div class="saw-terminal-wrapper">
    <div class="saw-pin-success-page">
        
        <!-- Success Icon -->
        <div class="saw-success-icon">
            ✅
        </div>
        
        <!-- Title -->
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

<style>
.saw-pin-success-page {
    max-width: 600px;
    margin: 4rem auto;
    padding: 3rem;
    text-align: center;
}

.saw-success-icon {
    font-size: 6rem;
    margin-bottom: 2rem;
    animation: successPulse 2s ease-in-out infinite;
}

@keyframes successPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.saw-success-title {
    font-size: 3rem;
    font-weight: 800;
    color: #10b981;
    margin: 0 0 1rem 0;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}

.saw-success-subtitle {
    font-size: 1.25rem;
    color: rgba(203, 213, 225, 0.8);
    margin: 0 0 3rem 0;
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
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
}

.saw-btn-close:hover {
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
}

/* Responsive */
@media (max-width: 768px) {
    .saw-pin-success-page {
        padding: 2rem 1rem;
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

<script>
// Zabránit náhodnému odchodu
window.addEventListener('beforeunload', function(e) {
    e.preventDefault();
    e.returnValue = 'Ujistěte se, že jste si poznamenali PIN kód!';
});

// Po 10 sekundách už nenabízet warning
setTimeout(function() {
    window.removeEventListener('beforeunload', arguments.callee);
}, 10000);
</script>

