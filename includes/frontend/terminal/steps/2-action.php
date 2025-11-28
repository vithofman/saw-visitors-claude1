<?php
/**
 * Terminal Step - Action Choice (Unified Design)
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
];

$t = $translations[$lang] ?? $translations['cs'];
?>
<!-- Žádný <style> blok! CSS je v pages.css -->

<div class="saw-page-aurora saw-step-action">
    <div class="saw-page-content saw-page-content-centered">
        
        <!-- Header -->
        <div class="saw-page-header saw-page-header-centered">
            <div class="saw-header-icon">❓</div>
            <h1 class="saw-header-title"><?php echo esc_html($t['title']); ?></h1>
            <p class="saw-header-subtitle"><?php echo esc_html($t['subtitle']); ?></p>
        </div>
        
        <!-- Action Grid -->
        <form method="POST">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="set_action">
            
            <div class="saw-selection-grid saw-selection-grid-2">
                
                <!-- Check-in Button -->
                <button type="submit" 
                        name="action_type" 
                        value="checkin" 
                        class="saw-selection-card is-checkin">
                    <span class="saw-card-icon">✅</span>
                    <div class="saw-card-content">
                        <div class="saw-card-title"><?php echo esc_html($t['checkin']); ?></div>
                        <div class="saw-card-subtitle">(<?php echo esc_html($t['checkin_full']); ?>)</div>
                        <div class="saw-card-description"><?php echo esc_html($t['checkin_desc']); ?></div>
                    </div>
                </button>
                
                <!-- Check-out Button -->
                <button type="submit" 
                        name="action_type" 
                        value="checkout" 
                        class="saw-selection-card is-checkout">
                    <span class="saw-card-icon">🚪</span>
                    <div class="saw-card-content">
                        <div class="saw-card-title"><?php echo esc_html($t['checkout']); ?></div>
                        <div class="saw-card-subtitle">(<?php echo esc_html($t['checkout_full']); ?>)</div>
                        <div class="saw-card-description"><?php echo esc_html($t['checkout_desc']); ?></div>
                    </div>
                </button>
                
            </div>
        </form>
        
    </div>
</div>

<?php
error_log("[ACTION.PHP] Unified design loaded (v3.3.0)");
?>