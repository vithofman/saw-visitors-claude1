<?php
/**
 * Terminal Step 2 - Action Choice
 * 
 * Choose between Check-in (arrival) or Check-out (departure)
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current language for translations
$flow = $this->session->get('terminal_flow');
$lang = $flow['language'] ?? 'cs';

$translations = [
    'cs' => [
        'title' => 'Co chcete udělat?',
        'subtitle' => 'Vyberte prosím akci',
        'checkin' => 'Příchod (Check-in)',
        'checkin_desc' => 'Registruji se na návštěvu',
        'checkout' => 'Odchod (Check-out)',
        'checkout_desc' => 'Končím návštěvu a odcházím',
    ],
    'en' => [
        'title' => 'What would you like to do?',
        'subtitle' => 'Please select an action',
        'checkin' => 'Arrival (Check-in)',
        'checkin_desc' => 'I am arriving for a visit',
        'checkout' => 'Departure (Check-out)',
        'checkout_desc' => 'I am leaving',
    ],
    'uk' => [
        'title' => 'Що ви хочете зробити?',
        'subtitle' => 'Будь ласка, виберіть дію',
        'checkin' => 'Прихід (Реєстрація)',
        'checkin_desc' => 'Я реєструюся на візит',
        'checkout' => 'Вихід (Виписка)',
        'checkout_desc' => 'Я закінчую візит і йду',
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
            <input type="hidden" name="terminal_action" value="set_action">
            
            <div class="saw-terminal-grid-2">
                
                <!-- Check-in Button -->
                <button type="submit" 
                        name="action_type" 
                        value="checkin" 
                        class="saw-terminal-btn saw-terminal-btn-icon saw-terminal-btn-success">
                    <span class="icon">✅</span>
                    <div>
                        <div style="font-size: 1.5rem; font-weight: 700;">
                            <?php echo esc_html($t['checkin']); ?>
                        </div>
                        <div style="font-size: 1rem; font-weight: 400; opacity: 0.9;">
                            <?php echo esc_html($t['checkin_desc']); ?>
                        </div>
                    </div>
                </button>
                
                <!-- Check-out Button -->
                <button type="submit" 
                        name="action_type" 
                        value="checkout" 
                        class="saw-terminal-btn saw-terminal-btn-icon saw-terminal-btn-danger">
                    <span class="icon">🚪</span>
                    <div>
                        <div style="font-size: 1.5rem; font-weight: 700;">
                            <?php echo esc_html($t['checkout']); ?>
                        </div>
                        <div style="font-size: 1rem; font-weight: 400; opacity: 0.9;">
                            <?php echo esc_html($t['checkout_desc']); ?>
                        </div>
                    </div>
                </button>
                
            </div>
        </form>
    </div>
</div>
