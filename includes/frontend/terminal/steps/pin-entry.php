<?php
/**
 * Terminal Step - PIN Entry
 * 
 * Enter 6-digit PIN code for planned visit
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
        'title' => 'Zadejte PIN kód',
        'subtitle' => 'PIN kód jste obdrželi v emailu',
        'clear' => 'Smazat vše',
        'backspace' => '⌫',
        'submit' => 'Potvrdit',
    ],
    'en' => [
        'title' => 'Enter PIN Code',
        'subtitle' => 'You received the PIN code via email',
        'clear' => 'Clear All',
        'backspace' => '⌫',
        'submit' => 'Submit',
    ],
    'uk' => [
        'title' => 'Введіть PIN-код',
        'subtitle' => 'Ви отримали PIN-код електронною поштою',
        'clear' => 'Очистити все',
        'backspace' => '⌫',
        'submit' => 'Підтвердити',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];
?>

<div class="saw-terminal-card">
    <div class="saw-terminal-card-header">
        <h2 class="saw-terminal-card-title">
            🔐 <?php echo esc_html($t['title']); ?>
        </h2>
        <p class="saw-terminal-card-subtitle">
            <?php echo esc_html($t['subtitle']); ?>
        </p>
    </div>
    
    <div class="saw-terminal-card-body">
        
        <!-- PIN Display -->
        <div class="saw-terminal-pin-display">
            <div class="saw-terminal-pin-dots">
                <?php for ($i = 0; $i < 6; $i++): ?>
                <div class="saw-terminal-pin-dot"></div>
                <?php endfor; ?>
            </div>
        </div>
        
        <!-- Numeric Keypad -->
        <div class="saw-terminal-numpad">
            <?php for ($i = 1; $i <= 9; $i++): ?>
            <button type="button" 
                    class="saw-terminal-numpad-btn" 
                    data-value="<?php echo $i; ?>">
                <?php echo $i; ?>
            </button>
            <?php endfor; ?>
            
            <button type="button" class="saw-terminal-numpad-btn clear">
                <?php echo esc_html($t['clear']); ?>
            </button>
            
            <button type="button" 
                    class="saw-terminal-numpad-btn" 
                    data-value="0">
                0
            </button>
            
            <button type="button" class="saw-terminal-numpad-btn backspace">
                <?php echo $t['backspace']; ?>
            </button>
        </div>
        
        <!-- Hidden Form -->
        <form method="POST" id="pin-form" class="saw-terminal-form">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="verify_pin">
            <input type="hidden" name="pin" id="pin-input" value="">
            
            <button type="submit" class="saw-terminal-btn saw-terminal-btn-success">
                <?php echo esc_html($t['submit']); ?>
            </button>
        </form>
        
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Auto-submit will be handled by terminal.js
    // when PIN length reaches 6 digits
});
</script>
