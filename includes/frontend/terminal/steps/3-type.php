<?php
/**
 * Terminal Step 3 - Check-in Type Selection
 * 
 * Choose between Planned visit (with PIN) or Walk-in (one-time registration)
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$flow = $this->session->get('terminal_flow');
$lang = $flow['language'] ?? 'cs';

$translations = [
    'cs' => [
        'title' => 'Typ návštěvy',
        'subtitle' => 'Máte registrovanou návštěvu?',
        'planned' => 'Plánovaná návštěva',
        'planned_desc' => 'Mám PIN kód z emailu',
        'walkin' => 'Jednorázová návštěva',
        'walkin_desc' => 'Nemám PIN, chci se zaregistrovat',
    ],
    'en' => [
        'title' => 'Visit Type',
        'subtitle' => 'Do you have a registered visit?',
        'planned' => 'Planned Visit',
        'planned_desc' => 'I have a PIN code from email',
        'walkin' => 'Walk-in Visit',
        'walkin_desc' => 'I don\'t have a PIN, I want to register',
    ],
    'uk' => [
        'title' => 'Тип візиту',
        'subtitle' => 'У вас є зареєстрований візит?',
        'planned' => 'Заплановий візит',
        'planned_desc' => 'У мене є PIN-код з електронної пошти',
        'walkin' => 'Разовий візит',
        'walkin_desc' => 'У мене немає PIN, я хочу зареєструватися',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];
?>

<div class="saw-terminal-card">
    <div class="saw-terminal-card-header">
        <h2 class="saw-terminal-card-title">
            <?php echo esc_html($t['title']); ?>
        </h2>
        <p class="saw-terminal-card-subtitle">
            <?php echo esc_html($t['subtitle']); ?>
        </p>
    </div>
    
    <div class="saw-terminal-card-body">
        <form method="POST" class="saw-terminal-form">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="set_checkin_type">
            
            <div class="saw-terminal-grid-2">
                
                <!-- Planned Visit Button -->
                <button type="submit" 
                        name="checkin_type" 
                        value="planned" 
                        class="saw-terminal-btn saw-terminal-btn-icon">
                    <span class="icon">📧</span>
                    <div>
                        <div style="font-size: 1.5rem; font-weight: 700;">
                            <?php echo esc_html($t['planned']); ?>
                        </div>
                        <div style="font-size: 1rem; font-weight: 400; opacity: 0.9;">
                            <?php echo esc_html($t['planned_desc']); ?>
                        </div>
                    </div>
                </button>
                
                <!-- Walk-in Visit Button -->
                <button type="submit" 
                        name="checkin_type" 
                        value="walkin" 
                        class="saw-terminal-btn saw-terminal-btn-icon saw-terminal-btn-secondary">
                    <span class="icon">🚶</span>
                    <div>
                        <div style="font-size: 1.5rem; font-weight: 700;">
                            <?php echo esc_html($t['walkin']); ?>
                        </div>
                        <div style="font-size: 1rem; font-weight: 400; opacity: 0.9;">
                            <?php echo esc_html($t['walkin_desc']); ?>
                        </div>
                    </div>
                </button>
                
            </div>
        </form>
    </div>
</div>
