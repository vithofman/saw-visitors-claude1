<?php
/**
 * Terminal Training Step - Additional Information
 * 
 * Company policies, contact info, and final instructions
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$flow = $this->session->get('terminal_flow');
$lang = $flow['language'] ?? 'cs';
$visitor_id = $flow['visitor_id'] ?? null;
$branch_id = $flow['branch_id'] ?? SAW_Context::get_branch_id();

// TODO: Load from database/settings
$company_policies = [
    'cs' => [
        '📵 Mobilní telefony na tichý režim',
        '🚭 Zákaz kouření v celém objektu (kromě vyhrazených míst)',
        '🎧 Neposlouchejte hudbu při pohybu',
        '📸 Fotografování pouze se souhlasem',
        '🗑️ Udržujte čistotu - používejte odpadkové koše',
        '👔 Dodržujte dress code (není-li uvedeno jinak)',
    ],
    'en' => [
        '📵 Mobile phones on silent mode',
        '🚭 No smoking in the entire facility (except designated areas)',
        '🎧 Do not listen to music while moving',
        '📸 Photography only with permission',
        '🗑️ Keep clean - use trash bins',
        '👔 Follow dress code (unless stated otherwise)',
    ],
    'uk' => [
        '📵 Мобільні телефони на беззвучному режимі',
        '🚭 Заборона куріння у всьому об\'єкті (крім спеціальних місць)',
        '🎧 Не слухайте музику під час руху',
        '📸 Фотографування лише з дозволу',
        '🗑️ Підтримуйте чистоту - використовуйте смітники',
        '👔 Дотримуйтеся дрес-коду (якщо не вказано інше)',
    ],
];

$emergency_contacts = [
    'emergency' => '112',
    'reception' => '+420 123 456 789',
    'security' => '+420 123 456 788',
];

// Check if already completed
$completed = false;
if ($visitor_id) {
    global $wpdb;
    $visitor = $wpdb->get_row($wpdb->prepare(
        "SELECT training_step_additional FROM {$wpdb->prefix}saw_visitors WHERE id = %d",
        $visitor_id
    ));
    $completed = !empty($visitor['training_step_additional']);
}

$translations = [
    'cs' => [
        'title' => 'Dodatečné informace',
        'subtitle' => 'Firemní politiky a kontakty',
        'policies_title' => 'Pravidla chování:',
        'contacts_title' => 'Důležité kontakty:',
        'emergency' => 'Tísňová linka',
        'reception' => 'Recepce',
        'security' => 'Ostraha',
        'wifi_title' => 'Wi-Fi pro hosty:',
        'wifi_network' => 'Síť',
        'wifi_password' => 'Heslo',
        'final_note' => 'V případě jakýchkoliv dotazů se neváhejte obrátit na hostitele nebo recepci.',
        'confirm' => 'Přečetl/a jsem všechny informace a zavazuji se dodržovat pravidla',
        'continue' => 'Dokončit školení',
    ],
    'en' => [
        'title' => 'Additional Information',
        'subtitle' => 'Company policies and contacts',
        'policies_title' => 'Behavioral rules:',
        'contacts_title' => 'Important contacts:',
        'emergency' => 'Emergency line',
        'reception' => 'Reception',
        'security' => 'Security',
        'wifi_title' => 'Guest Wi-Fi:',
        'wifi_network' => 'Network',
        'wifi_password' => 'Password',
        'final_note' => 'If you have any questions, please do not hesitate to contact your host or reception.',
        'confirm' => 'I have read all information and commit to following the rules',
        'continue' => 'Complete training',
    ],
    'uk' => [
        'title' => 'Додаткова інформація',
        'subtitle' => 'Політика компанії та контакти',
        'policies_title' => 'Правила поведінки:',
        'contacts_title' => 'Важливі контакти:',
        'emergency' => 'Екстрена лінія',
        'reception' => 'Рецепція',
        'security' => 'Охорона',
        'wifi_title' => 'Wi-Fi для гостей:',
        'wifi_network' => 'Мережа',
        'wifi_password' => 'Пароль',
        'final_note' => 'Якщо у вас виникнуть запитання, зв\'яжіться з вашим господарем або рецепцією.',
        'confirm' => 'Я прочитав всю інформацію і зобов\'язуюсь дотримуватися правил',
        'continue' => 'Завершити навчання',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];
$policies = $company_policies[$lang] ?? $company_policies['cs'];
?>

<div class="saw-terminal-card">
    <div class="saw-terminal-card-header">
        <h2 class="saw-terminal-card-title">
            ℹ️ <?php echo esc_html($t['title']); ?>
        </h2>
        <p class="saw-terminal-card-subtitle">
            <?php echo esc_html($t['subtitle']); ?>
        </p>
    </div>
    
    <div class="saw-terminal-card-body">
        
        <!-- Progress indicator -->
        <div class="saw-terminal-progress" style="margin-bottom: 2rem;">
            <div class="saw-terminal-progress-step completed">1</div>
            <div class="saw-terminal-progress-step completed">2</div>
            <div class="saw-terminal-progress-step completed">3</div>
            <div class="saw-terminal-progress-step completed">4</div>
            <div class="saw-terminal-progress-step completed">5</div>
        </div>
        
        <!-- Company policies -->
        <div style="background: #f0f9ff; border: 2px solid #bae6fd; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem;">
            <h3 style="margin: 0 0 1.5rem 0; font-size: 1.25rem; font-weight: 700; color: #0369a1;">
                <?php echo esc_html($t['policies_title']); ?>
            </h3>
            
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <?php foreach ($policies as $policy): ?>
                <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: white; border-radius: 8px;">
                    <span style="font-size: 1.125rem; color: #0c4a6e; line-height: 1.6;">
                        <?php echo esc_html($policy); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Emergency contacts -->
        <div style="background: #fff5f5; border: 2px solid #fc8181; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem;">
            <h3 style="margin: 0 0 1.5rem 0; font-size: 1.25rem; font-weight: 700; color: #c53030;">
                📞 <?php echo esc_html($t['contacts_title']); ?>
            </h3>
            
            <div style="display: grid; gap: 1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: white; border-radius: 8px; border-left: 4px solid #fc8181;">
                    <span style="font-weight: 600; color: #2d3748;">
                        🚨 <?php echo esc_html($t['emergency']); ?>
                    </span>
                    <a href="tel:<?php echo esc_attr($emergency_contacts['emergency']); ?>" 
                       style="font-size: 1.5rem; font-weight: 700; color: #c53030; text-decoration: none;">
                        <?php echo esc_html($emergency_contacts['emergency']); ?>
                    </a>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: white; border-radius: 8px;">
                    <span style="font-weight: 600; color: #2d3748;">
                        📱 <?php echo esc_html($t['reception']); ?>
                    </span>
                    <a href="tel:<?php echo esc_attr($emergency_contacts['reception']); ?>" 
                       style="font-size: 1.125rem; font-weight: 600; color: #0369a1; text-decoration: none;">
                        <?php echo esc_html($emergency_contacts['reception']); ?>
                    </a>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: white; border-radius: 8px;">
                    <span style="font-weight: 600; color: #2d3748;">
                        🛡️ <?php echo esc_html($t['security']); ?>
                    </span>
                    <a href="tel:<?php echo esc_attr($emergency_contacts['security']); ?>" 
                       style="font-size: 1.125rem; font-weight: 600; color: #0369a1; text-decoration: none;">
                        <?php echo esc_html($emergency_contacts['security']); ?>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Guest WiFi -->
        <div style="background: #f0fdf4; border: 2px solid #86efac; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem;">
            <h3 style="margin: 0 0 1rem 0; font-size: 1.125rem; font-weight: 700; color: #16a34a;">
                📶 <?php echo esc_html($t['wifi_title']); ?>
            </h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div style="padding: 1rem; background: white; border-radius: 8px;">
                    <p style="margin: 0 0 0.5rem 0; font-size: 0.875rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">
                        <?php echo esc_html($t['wifi_network']); ?>
                    </p>
                    <p style="margin: 0; font-size: 1.25rem; font-weight: 700; color: #16a34a;">
                        Guest_WiFi
                    </p>
                </div>
                
                <div style="padding: 1rem; background: white; border-radius: 8px;">
                    <p style="margin: 0 0 0.5rem 0; font-size: 0.875rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">
                        <?php echo esc_html($t['wifi_password']); ?>
                    </p>
                    <p style="margin: 0; font-size: 1.25rem; font-weight: 700; color: #16a34a; font-family: monospace;">
                        Welcome2024
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Final note -->
        <div style="background: #fffaf0; border: 2px solid #f6ad55; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; text-align: center;">
            <p style="margin: 0; font-size: 1.125rem; color: #c05621; line-height: 1.6;">
                💡 <strong><?php echo esc_html($t['final_note']); ?></strong>
            </p>
        </div>
        
        <!-- Confirmation form -->
        <form method="POST" id="training-additional-form">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="complete_training_additional">
            
            <?php if (!$completed): ?>
            <div class="saw-terminal-form-checkbox" style="margin-bottom: 1.5rem;">
                <input type="checkbox" 
                       name="additional_confirmed" 
                       id="additional-confirmed" 
                       value="1"
                       required>
                <label for="additional-confirmed">
                    ✅ <?php echo esc_html($t['confirm']); ?>
                </label>
            </div>
            <?php endif; ?>
            
            <button type="submit" 
                    class="saw-terminal-btn saw-terminal-btn-success"
                    id="continue-btn"
                    <?php echo !$completed ? 'disabled' : ''; ?>>
                🎓 <?php echo esc_html($t['continue']); ?>
            </button>
        </form>
        
        <p style="margin-top: 2rem; text-align: center; color: #a0aec0; font-size: 0.875rem;">
            <?php if ($lang === 'cs'): ?>
                Po dokončení budete automaticky přihlášeni
            <?php elseif ($lang === 'en'): ?>
                After completion you will be automatically checked in
            <?php else: ?>
                Після завершення ви автоматично будете зареєстровані
            <?php endif; ?>
        </p>
        
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Enable continue button when confirmation is checked
    $('#additional-confirmed').on('change', function() {
        $('#continue-btn').prop('disabled', !$(this).is(':checked'));
    });
    
    // Success celebration on form submit
    $('#training-additional-form').on('submit', function(e) {
        $('#continue-btn').html('🎉 Dokončuji školení...');
        
        // Confetti effect (simple version)
        for (let i = 0; i < 50; i++) {
            const confetti = $('<div>').css({
                'position': 'fixed',
                'width': '10px',
                'height': '10px',
                'background': ['#667eea', '#764ba2', '#48bb78', '#f6ad55', '#fc8181'][Math.floor(Math.random() * 5)],
                'left': Math.random() * 100 + '%',
                'top': '-10px',
                'border-radius': '50%',
                'z-index': '9999',
                'animation': 'fall ' + (2 + Math.random() * 2) + 's linear'
            });
            
            $('body').append(confetti);
            
            setTimeout(function() {
                confetti.remove();
            }, 4000);
        }
    });
});

// Add confetti animation
$('<style>@keyframes fall { to { transform: translateY(100vh) rotate(360deg); opacity: 0; } }</style>').appendTo('head');
</script>
