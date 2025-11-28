<?php
/**
 * Terminal Step - Success Confirmation (Unified Design)
 * 
 * @package SAW_Visitors
 * @version 3.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$flow = $this->session->get('terminal_flow');
$lang = $flow['language'] ?? 'cs';

// Zjištění akce (checkout vs checkin)
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
        'important' => 'Důležité',
    ],
    'en' => [
        'checkin_title' => 'Successfully Checked In!',
        'checkin_message' => 'Your visit has been registered. Have a great day.',
        'checkout_title' => 'Successfully Checked Out!',
        'checkout_message' => 'Thank you for your visit. Have a safe journey.',
        'back_btn' => 'Back to Start',
        'auto_redirect' => 'Automatic redirect in 5 seconds...',
        'checkin_info' => 'Please check out at this terminal when leaving.',
        'important' => 'Important',
    ],
    'sk' => [
        'checkin_title' => 'Úspešne prihlásený!',
        'checkin_message' => 'Vaša návšteva bola zaregistrovaná. Prajeme príjemný deň.',
        'checkout_title' => 'Úspešne odhlásený!',
        'checkout_message' => 'Ďakujeme za návštevu. Prajeme šťastnú cestu.',
        'back_btn' => 'Späť na začiatok',
        'auto_redirect' => 'Automatické presmerovanie za 5 sekúnd...',
        'checkin_info' => 'Pri odchode sa prosím opäť odhláste na tomto terminále.',
        'important' => 'Dôležité',
    ],
    'uk' => [
        'checkin_title' => 'Успішно зареєстровано!',
        'checkin_message' => 'Ваш візит зареєстровано. Гарного дня.',
        'checkout_title' => 'Успішно виписано!',
        'checkout_message' => 'Дякуємо за візит. Щасливої дороги.',
        'back_btn' => 'Повернутися на початок',
        'auto_redirect' => 'Автоматичне перенаправлення через 5 секунд...',
        'checkin_info' => 'Будь ласка, виписуйтесь на цьому терміналі при виході.',
        'important' => 'Важливо',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];
$is_checkin = ($action === 'checkin');
$title = $is_checkin ? $t['checkin_title'] : $t['checkout_title'];
$message = $is_checkin ? $t['checkin_message'] : $t['checkout_message'];
$icon = $is_checkin ? '✅' : '👋';
?>
<!-- Žádný <style> blok! CSS je v pages.css -->

<div class="saw-page-aurora saw-step-success">
    <div class="saw-page-content saw-page-content-centered">
        
        <div class="saw-success-icon">
            <?php echo $icon; ?>
        </div>
        
        <h1 class="saw-header-title">
            <?php echo esc_html($title); ?>
        </h1>
        
        <p class="saw-header-subtitle">
            <?php echo esc_html($message); ?>
        </p>
        
        <?php if ($is_checkin): ?>
        <div class="saw-content-card saw-success-info-card">
            <div class="saw-card-title">
                <span>ℹ️</span>
                <span><?php echo esc_html($t['important']); ?></span>
            </div>
            <p class="saw-card-description">
                <?php echo esc_html($t['checkin_info']); ?>
            </p>
        </div>
        <?php endif; ?>
        
        <a href="<?php echo home_url('/terminal/'); ?>" 
           class="saw-btn saw-btn-primary">
            <?php echo esc_html($t['back_btn']); ?> →
        </a>
        
        <p class="saw-success-redirect">
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