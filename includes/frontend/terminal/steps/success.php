<?php
/**
 * Terminal Step - Success Confirmation
 * 
 * Display success message after check-in or check-out
 * Auto-redirects to start after 5 seconds
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 */
if (!defined('ABSPATH')) {
    exit;
}

$flow = $this->session->get('terminal_flow');
$lang = $flow['language'] ?? 'cs';

// ✅ OPRAVENO: Správné zjištění akce (checkout vs checkin)
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
    ],
    'en' => [
        'checkin_title' => 'Successfully Checked In!',
        'checkin_message' => 'Your visit has been registered. Have a great day.',
        'checkout_title' => 'Successfully Checked Out!',
        'checkout_message' => 'Thank you for your visit. Have a safe journey.',
        'back_btn' => 'Back to Start',
        'auto_redirect' => 'Automatic redirect in 5 seconds...',
        'checkin_info' => 'Please check out at this terminal when leaving.',
    ],
    'uk' => [
        'checkin_title' => 'Успішно зареєстровано!',
        'checkin_message' => 'Ваш візит зареєстровано. Гарного дня.',
        'checkout_title' => 'Успішно виписано!',
        'checkout_message' => 'Дякуємо за візит. Щасливої дороги.',
        'back_btn' => 'Повернутися на початок',
        'auto_redirect' => 'Автоматичне перенаправлення через 5 секунд...',
        'checkin_info' => 'Будь ласка, виписуйтесь на цьому терміналі при виході.',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];
$is_checkin = ($action === 'checkin');
$title = $is_checkin ? $t['checkin_title'] : $t['checkout_title'];
$message = $is_checkin ? $t['checkin_message'] : $t['checkout_message'];
$icon = $is_checkin ? '✅' : '👋';
?>

<div class="saw-terminal-card">
    <div class="saw-terminal-success">
        
        <div class="saw-terminal-success-icon">
            <?php echo $icon; ?>
        </div>
        
        <h2 class="saw-terminal-success-title">
            <?php echo esc_html($title); ?>
        </h2>
        
        <p class="saw-terminal-success-message">
            <?php echo esc_html($message); ?>
        </p>
        
        <!-- Additional Info (only for check-in) -->
        <?php if ($is_checkin): ?>
        <div style="background: #f0f9ff; border: 2px solid #bae6fd; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem;">
            <p style="margin: 0; font-size: 1rem; color: #0369a1;">
                <strongℹ️ Důležité:</strong><br>
                <?php echo esc_html($t['checkin_info']); ?>
            </p>
        </div>
        <?php endif; ?>
        
        <a href="<?php echo home_url('/terminal/'); ?>" 
           class="saw-terminal-btn saw-terminal-btn-success">
            <?php echo esc_html($t['back_btn']); ?>
        </a>
        
        <p style="margin-top: 2rem; color: #a0aec0; font-size: 0.875rem;">
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