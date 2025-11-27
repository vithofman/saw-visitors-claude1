<?php
/**
 * Invitation Success - PIN Display
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

$flow = $this->session->get('invitation_flow');
$lang = $flow['language'] ?? 'cs';
$pin = $pin ?? '';
$visit = $visit ?? [];

$translations = [
    'cs' => [
        'title' => '✅ Registrace dokončena!',
        'subtitle' => 'Děkujeme za vyplnění informací',
        'pin_title' => 'Váš PIN kód pro check-in',
        'pin_note' => 'Tento PIN kód použijete při příchodu na recepci',
        'reminder' => 'PIN kód jsme Vám také zaslali e-mailem',
        'next_steps' => 'Co bude dál?',
        'step1' => 'Den návštěvy přijdete na recepci',
        'step2' => 'Zadáte svůj PIN kód',
        'step3' => 'Absolvujete případné školení',
        'step4' => 'A můžete vstoupit!',
    ],
    'en' => [
        'title' => '✅ Registration Complete!',
        'subtitle' => 'Thank you for providing the information',
        'pin_title' => 'Your PIN code for check-in',
        'pin_note' => 'Use this PIN code when arriving at reception',
        'reminder' => 'We also sent the PIN code to your email',
        'next_steps' => 'What\'s next?',
        'step1' => 'On the visit day, come to reception',
        'step2' => 'Enter your PIN code',
        'step3' => 'Complete any required training',
        'step4' => 'And you can enter!',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];
?>
<style>
.saw-invitation-success-page {
    max-width: 800px;
    margin: 0 auto;
    padding: 2rem;
    text-align: center;
}

.saw-success-header {
    margin-bottom: 3rem;
}

.saw-success-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    color: white;
    margin: 0 auto 2rem;
}

.saw-success-header h1 {
    font-size: 2rem;
    color: #fff;
    margin-bottom: 0.5rem;
}

.saw-success-header p {
    color: rgba(255, 255, 255, 0.8);
}

.saw-pin-display {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 2rem;
    margin: 2rem 0;
}

.saw-pin-display h2 {
    color: #fff;
    margin-bottom: 1rem;
}

.saw-pin-code {
    font-size: 48px;
    font-weight: bold;
    color: #667eea;
    letter-spacing: 8px;
    font-family: monospace;
    margin: 1rem 0;
}

.saw-pin-note {
    color: rgba(255, 255, 255, 0.8);
}

.saw-reminder {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.3);
    border-radius: 12px;
    padding: 1rem;
    margin: 2rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    color: #93c5fd;
}

.saw-next-steps {
    margin-top: 3rem;
}

.saw-next-steps h3 {
    color: #fff;
    margin-bottom: 2rem;
}

.saw-steps-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    text-align: left;
}

.saw-step {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    gap: 1rem;
    align-items: start;
}

.saw-step-number {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    flex-shrink: 0;
}

.saw-step p {
    color: rgba(255, 255, 255, 0.9);
    margin: 0;
}
</style>

<div class="saw-invitation-success-page">
    <div class="saw-success-header">
        <div class="saw-success-icon">✓</div>
        <h1><?= esc_html($t['title']) ?></h1>
        <p><?= esc_html($t['subtitle']) ?></p>
    </div>
    
    <div class="saw-pin-display">
        <h2><?= esc_html($t['pin_title']) ?></h2>
        <div class="saw-pin-code"><?= esc_html($pin) ?></div>
        <p class="saw-pin-note"><?= esc_html($t['pin_note']) ?></p>
    </div>
    
    <div class="saw-reminder">
        <span class="saw-reminder-icon">📧</span>
        <p><?= esc_html($t['reminder']) ?></p>
    </div>
    
    <div class="saw-next-steps">
        <h3><?= esc_html($t['next_steps']) ?></h3>
        
        <div class="saw-steps-grid">
            <div class="saw-step">
                <span class="saw-step-number">1</span>
                <p><?= esc_html($t['step1']) ?></p>
            </div>
            
            <div class="saw-step">
                <span class="saw-step-number">2</span>
                <p><?= esc_html($t['step2']) ?></p>
            </div>
            
            <div class="saw-step">
                <span class="saw-step-number">3</span>
                <p><?= esc_html($t['step3']) ?></p>
            </div>
            
            <div class="saw-step">
                <span class="saw-step-number">4</span>
                <p><?= esc_html($t['step4']) ?></p>
            </div>
        </div>
    </div>
    
    <!-- Informační text o možnosti úpravy -->
    <div class="saw-edit-info" style="
        margin-top: 2rem;
        padding: 1rem 1.5rem;
        background: rgba(59, 130, 246, 0.1);
        border: 1px solid rgba(59, 130, 246, 0.3);
        border-radius: 12px;
        text-align: center;
        color: #93c5fd;
    ">
        <p style="margin: 0; font-size: 0.9375rem;">
            <?php echo $lang === 'en' 
                ? 'You can edit your information anytime using the menu on the left.' 
                : 'Své údaje můžete kdykoliv upravit pomocí menu vlevo.'; 
            ?>
        </p>
    </div>
</div>

