<?php
/**
 * Terminal Step - Checkout via PIN
 * 
 * Enter PIN to load visitors
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$flow = $this->session->get('terminal_flow');
$lang = $flow['language'] ?? 'cs';

$translations = [
    'cs' => [
        'title' => 'Odhlášení - PIN kód',
        'subtitle' => 'Zadejte PIN kód z vaší návštěvy',
        'submit' => 'Načíst návštěvu',
    ],
    'en' => [
        'title' => 'Check-out - PIN Code',
        'subtitle' => 'Enter PIN code from your visit',
        'submit' => 'Load Visit',
    ],
    'uk' => [
        'title' => 'Виписка - PIN-код',
        'subtitle' => 'Введіть PIN-код з вашого візиту',
        'submit' => 'Завантажити візит',
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
        
        <div class="saw-terminal-pin-display">
            <div class="saw-terminal-pin-dots">
                <?php for ($i = 0; $i < 6; $i++): ?>
                <div class="saw-terminal-pin-dot"></div>
                <?php endfor; ?>
            </div>
        </div>
        
        <div class="saw-terminal-numpad">
            <?php for ($i = 1; $i <= 9; $i++): ?>
            <button type="button" 
                    class="saw-terminal-numpad-btn" 
                    data-value="<?php echo $i; ?>">
                <?php echo $i; ?>
            </button>
            <?php endfor; ?>
            
            <button type="button" class="saw-terminal-numpad-btn clear">CLR</button>
            <button type="button" class="saw-terminal-numpad-btn" data-value="0">0</button>
            <button type="button" class="saw-terminal-numpad-btn backspace">⌫</button>
        </div>
        
        <form method="POST" id="pin-form" class="saw-terminal-form">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="checkout_pin_verify">
            <input type="hidden" name="pin" id="pin-input" value="">
            
            <button type="submit" class="saw-terminal-btn saw-terminal-btn-success">
                <?php echo esc_html($t['submit']); ?> →
            </button>
        </form>
        
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    let pin = '';
    
    // Update PIN display
    function updateDisplay() {
        $('.saw-terminal-pin-dot').each(function(i) {
            $(this).toggleClass('filled', i < pin.length);
        });
        
        $('#pin-input').val(pin);
        
        // Auto-submit when 6 digits entered
        if (pin.length === 6) {
            setTimeout(function() {
                $('#pin-form').submit();
            }, 300);
        }
    }
    
    // Number button click
    $('.saw-terminal-numpad-btn[data-value]').on('click', function() {
        if (pin.length < 6) {
            pin += $(this).data('value');
            updateDisplay();
        }
    });
    
    // Clear button
    $('.saw-terminal-numpad-btn.clear').on('click', function() {
        pin = '';
        updateDisplay();
    });
    
    // Backspace button
    $('.saw-terminal-numpad-btn.backspace').on('click', function() {
        pin = pin.slice(0, -1);
        updateDisplay();
    });
    
    // Keyboard support
    $(document).on('keydown', function(e) {
        if (e.key >= '0' && e.key <= '9') {
            if (pin.length < 6) {
                pin += e.key;
                updateDisplay();
            }
        } else if (e.key === 'Backspace') {
            pin = pin.slice(0, -1);
            updateDisplay();
        } else if (e.key === 'Escape') {
            pin = '';
            updateDisplay();
        }
    });
});
</script>
