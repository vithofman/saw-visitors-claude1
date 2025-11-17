<?php
/**
 * Terminal Step - Checkout via PIN
 * 
 * Enter PIN to load all visitors for that visit, then select who is leaving
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
        'subtitle' => 'PIN kód z vaší návštěvy',
        'select_title' => 'Vyberte odcházející osoby',
        'select_subtitle' => 'Zaškrtněte všechny, kdo odcházejí',
        'select_all' => 'Vybrat všechny',
        'submit' => 'Odhlásit vybrané',
    ],
    'en' => [
        'title' => 'Enter PIN Code',
        'subtitle' => 'PIN code from your visit',
        'select_title' => 'Select departing persons',
        'select_subtitle' => 'Check all who are leaving',
        'select_all' => 'Select All',
        'submit' => 'Check out selected',
    ],
    'uk' => [
        'title' => 'Введіть PIN-код',
        'subtitle' => 'PIN-код з вашого візиту',
        'select_title' => 'Виберіть осіб, які від\'їжджають',
        'select_subtitle' => 'Позначте всіх, хто від\'їжджає',
        'select_all' => 'Вибрати всіх',
        'submit' => 'Виписати вибраних',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];

// TODO: Load visitors based on PIN from database
// For now, mock data
$visitors = [];
$pin_verified = false;

if (isset($_POST['pin']) && !empty($_POST['pin'])) {
    // Mock verification
    $pin_verified = true;
    $visitors = [
        ['id' => 1, 'first_name' => 'Jan', 'last_name' => 'Novák', 'position' => 'Obchodní ředitel'],
        ['id' => 2, 'first_name' => 'Marie', 'last_name' => 'Svobodová', 'position' => 'Manažerka projektu'],
        ['id' => 3, 'first_name' => 'Petr', 'last_name' => 'Dvořák', 'position' => 'IT specialista'],
    ];
}
?>

<div class="saw-terminal-card">
    
    <?php if (!$pin_verified): ?>
    
    <!-- PIN Entry -->
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
            <input type="hidden" name="pin" id="pin-input" value="">
            
            <button type="submit" class="saw-terminal-btn saw-terminal-btn-success">
                Načíst návštěvu →
            </button>
        </form>
        
    </div>
    
    <?php else: ?>
    
    <!-- Visitor Selection -->
    <div class="saw-terminal-card-header">
        <h2 class="saw-terminal-card-title">
            <?php echo esc_html($t['select_title']); ?>
        </h2>
        <p class="saw-terminal-card-subtitle">
            <?php echo esc_html($t['select_subtitle']); ?>
        </p>
    </div>
    
    <div class="saw-terminal-card-body">
        
        <form method="POST" class="saw-terminal-form">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="checkout_pin">
            
            <!-- Select All Button -->
            <button type="button" 
                    class="saw-terminal-btn saw-terminal-btn-secondary" 
                    id="select-all-btn"
                    style="margin-bottom: 2rem;">
                ✅ <?php echo esc_html($t['select_all']); ?>
            </button>
            
            <!-- Visitor List -->
            <div class="saw-terminal-visitor-list">
                <?php foreach ($visitors as $visitor): ?>
                <label class="saw-terminal-visitor-item" data-visitor-id="<?php echo $visitor['id']; ?>">
                    <input type="checkbox" 
                           name="visitor_ids[]" 
                           value="<?php echo $visitor['id']; ?>" 
                           class="saw-terminal-visitor-checkbox">
                    
                    <div class="saw-terminal-visitor-avatar">
                        <?php echo strtoupper(substr($visitor['first_name'], 0, 1)); ?>
                    </div>
                    
                    <div class="saw-terminal-visitor-info">
                        <h3 class="saw-terminal-visitor-name">
                            <?php echo esc_html($visitor['first_name'] . ' ' . $visitor['last_name']); ?>
                        </h3>
                        <?php if (!empty($visitor['position'])): ?>
                        <p class="saw-terminal-visitor-position">
                            <?php echo esc_html($visitor['position']); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
            
            <!-- Submit Button -->
            <button type="submit" class="saw-terminal-btn saw-terminal-btn-danger">
                🚪 <?php echo esc_html($t['submit']); ?>
            </button>
            
        </form>
        
    </div>
    
    <?php endif; ?>
    
</div>

<script>
jQuery(document).ready(function($) {
    // Select all button
    $('#select-all-btn').on('click', function() {
        const allChecked = $('.saw-terminal-visitor-checkbox:checked').length === $('.saw-terminal-visitor-checkbox').length;
        
        $('.saw-terminal-visitor-checkbox').prop('checked', !allChecked);
        $('.saw-terminal-visitor-item').toggleClass('selected', !allChecked);
        
        $(this).text(allChecked ? '✅ Vybrat všechny' : '❌ Zrušit výběr');
    });
});
</script>
