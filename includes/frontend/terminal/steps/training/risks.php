<?php
/**
 * Terminal Training Step - General Risks
 * 
 * Display general workplace hazards and safety rules
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

// Check if already completed
$completed = false;
if ($visitor_id) {
    global $wpdb;
    $visitor = $wpdb->get_row($wpdb->prepare(
        "SELECT training_step_risks FROM {$wpdb->prefix}saw_visitors WHERE id = %d",
        $visitor_id
    ));
    $completed = !empty($visitor['training_step_risks']);
}

$translations = [
    'cs' => [
        'title' => 'Obecná rizika',
        'subtitle' => 'Bezpečnostní pravidla pro návštěvníky',
        'rules_title' => 'Důležitá bezpečnostní pravidla:',
        'confirm' => 'Potvrzuji, že jsem si přečetl/a všechna rizika',
        'continue' => 'Pokračovat',
        'rules' => [
            '👷 Pohybujte se pouze v povolených prostorách',
            '🚫 Nevstupujte do prostor označených jako nebezpečné',
            '⚠️ Noste vždy ochranné pomůcky (přilba, vesta), pokud jsou vyžadovány',
            '🚶 Nepohybujte se v místech s pohybem vozíků a strojů',
            '📱 V případě nouze volejte: 112',
            '🧯 Seznamte se s umístěním hasicích přístrojů',
            '🚪 Zapamatujte si nejbližší nouzový východ',
            '⛔ Zákaz kouření mimo vyhrazené prostory',
            '📸 Fotografování pouze se souhlasem',
            '🔊 V případě poplachu postupujte podle pokynů',
        ],
    ],
    'en' => [
        'title' => 'General Risks',
        'subtitle' => 'Safety rules for visitors',
        'rules_title' => 'Important safety rules:',
        'confirm' => 'I confirm that I have read all risks',
        'continue' => 'Continue',
        'rules' => [
            '👷 Move only in authorized areas',
            '🚫 Do not enter areas marked as dangerous',
            '⚠️ Always wear protective equipment (helmet, vest) if required',
            '🚶 Do not move in areas with vehicle and machinery traffic',
            '📱 In case of emergency call: 112',
            '🧯 Familiarize yourself with fire extinguisher locations',
            '🚪 Remember the nearest emergency exit',
            '⛔ No smoking outside designated areas',
            '📸 Photography only with permission',
            '🔊 In case of alarm follow instructions',
        ],
    ],
    'uk' => [
        'title' => 'Загальні ризики',
        'subtitle' => 'Правила безпеки для відвідувачів',
        'rules_title' => 'Важливі правила безпеки:',
        'confirm' => 'Підтверджую, що прочитав усі ризики',
        'continue' => 'Продовжити',
        'rules' => [
            '👷 Рухайтеся лише в дозволених зонах',
            '🚫 Не входьте в зони, позначені як небезпечні',
            '⚠️ Завжди використовуйте засоби захисту (шолом, жилет), якщо потрібно',
            '🚶 Не рухайтеся в місцях руху транспорту та обладнання',
            '📱 У разі надзвичайної ситуації дзвоніть: 112',
            '🧯 Ознайомтеся з розташуванням вогнегасників',
            '🚪 Запам\'ятайте найближчий аварійний вихід',
            '⛔ Куріння заборонено поза спеціальними зонами',
            '📸 Фотографування лише з дозволу',
            '🔊 У разі тривоги дотримуйтесь інструкцій',
        ],
    ],
];

$t = $translations[$lang] ?? $translations['cs'];
?>

<div class="saw-terminal-card">
    <div class="saw-terminal-card-header">
        <h2 class="saw-terminal-card-title">
            ⚠️ <?php echo esc_html($t['title']); ?>
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
            <div class="saw-terminal-progress-step active">4</div>
            <div class="saw-terminal-progress-step">5</div>
        </div>
        
        <!-- Safety rules -->
        <div style="background: #fff5f5; border: 2px solid #fc8181; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem;">
            <h3 style="margin: 0 0 1.5rem 0; font-size: 1.25rem; font-weight: 700; color: #c53030;">
                <?php echo esc_html($t['rules_title']); ?>
            </h3>
            
            <div class="saw-training-rules-list">
                <?php foreach ($t['rules'] as $index => $rule): ?>
                <div class="saw-training-rule-item" 
                     style="display: flex; align-items: start; gap: 1rem; padding: 1rem; background: white; border-radius: 8px; margin-bottom: 0.75rem; cursor: pointer; transition: all 0.2s ease;"
                     data-rule-index="<?php echo $index; ?>">
                    <input type="checkbox" 
                           class="rule-checkbox"
                           style="width: 1.5rem; height: 1.5rem; margin-top: 0.25rem; flex-shrink: 0;">
                    <span style="flex: 1; font-size: 1.125rem; color: #2d3748; line-height: 1.6;">
                        <?php echo esc_html($rule); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Progress counter -->
            <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px solid #fed7d7; text-align: center;">
                <p style="margin: 0; font-size: 1rem; color: #c53030; font-weight: 600;">
                    <span id="rules-checked-count">0</span> / <?php echo count($t['rules']); ?> pravidel přečteno
                </p>
                <div style="height: 8px; background: #fed7d7; border-radius: 4px; margin-top: 0.75rem; overflow: hidden;">
                    <div id="rules-progress-bar" 
                         style="height: 100%; width: 0%; background: linear-gradient(90deg, #f56565, #e53e3e); transition: width 0.3s ease;">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Additional warnings -->
        <div style="background: #fffaf0; border: 2px solid #f6ad55; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem;">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                <span style="font-size: 2rem;">⚡</span>
                <h3 style="margin: 0; font-size: 1.125rem; font-weight: 700; color: #c05621;">
                    Zvláštní upozornění:
                </h3>
            </div>
            <p style="margin: 0; color: #c05621; line-height: 1.6;">
                <?php if ($lang === 'cs'): ?>
                    V případě úrazu nebo nehody okamžitě informujte odpovědnou osobu nebo zavolejte linku 155 (zdravotnická záchranná služba).
                <?php elseif ($lang === 'en'): ?>
                    In case of injury or accident, immediately inform the responsible person or call 155 (emergency medical service).
                <?php else: ?>
                    У разі травми або нещасного випадку негайно повідомте відповідальну особу або зателефонуйте на 155 (швидка медична допомога).
                <?php endif; ?>
            </p>
        </div>
        
        <!-- Confirmation form -->
        <form method="POST" id="training-risks-form">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="complete_training_risks">
            
            <?php if (!$completed): ?>
            <div class="saw-terminal-form-checkbox" style="margin-bottom: 1.5rem;">
                <input type="checkbox" 
                       name="risks_confirmed" 
                       id="risks-confirmed" 
                       value="1"
                       required
                       disabled>
                <label for="risks-confirmed">
                    ✅ <?php echo esc_html($t['confirm']); ?>
                </label>
            </div>
            <?php endif; ?>
            
            <button type="submit" 
                    class="saw-terminal-btn saw-terminal-btn-success"
                    id="continue-btn"
                    <?php echo !$completed ? 'disabled' : ''; ?>>
                <?php echo esc_html($t['continue']); ?> →
            </button>
        </form>
        
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const totalRules = <?php echo count($t['rules']); ?>;
    let checkedCount = 0;
    
    // Make rule items clickable
    $('.saw-training-rule-item').on('click', function() {
        const checkbox = $(this).find('.rule-checkbox');
        const isChecked = checkbox.prop('checked');
        
        checkbox.prop('checked', !isChecked);
        updateProgress();
        
        // Visual feedback
        if (!isChecked) {
            $(this).css({
                'background': '#f0fdf4',
                'border': '2px solid #86efac'
            });
        } else {
            $(this).css({
                'background': 'white',
                'border': 'none'
            });
        }
    });
    
    // Prevent checkbox click from double-toggling
    $('.rule-checkbox').on('click', function(e) {
        e.stopPropagation();
        updateProgress();
    });
    
    function updateProgress() {
        checkedCount = $('.rule-checkbox:checked').length;
        const percentage = Math.floor((checkedCount / totalRules) * 100);
        
        $('#rules-checked-count').text(checkedCount);
        $('#rules-progress-bar').css('width', percentage + '%');
        
        // Enable confirmation checkbox when all rules are checked
        if (checkedCount === totalRules) {
            $('#risks-confirmed').prop('disabled', false);
            
            // Show success message
            if (!$('.all-rules-checked-msg').length) {
                $('<div class="all-rules-checked-msg" style="background: #f0fdf4; border: 2px solid #86efac; border-radius: 12px; padding: 1rem; margin-bottom: 1rem; text-align: center; animation: slideDown 0.3s ease;">' +
                  '<p style="margin: 0; font-size: 1.125rem; color: #16a34a; font-weight: 600;">🎉 Všechna pravidla přečtena!</p>' +
                  '</div>').insertBefore('#risks-confirmed').closest('.saw-terminal-form-checkbox');
            }
        } else {
            $('#risks-confirmed').prop('disabled', true).prop('checked', false);
            $('#continue-btn').prop('disabled', true);
            $('.all-rules-checked-msg').remove();
        }
    }
    
    // Enable continue button when confirmation is checked
    $('#risks-confirmed').on('change', function() {
        $('#continue-btn').prop('disabled', !$(this).is(':checked'));
    });
    
    <?php if ($completed): ?>
    // Pre-check all if already completed
    $('.rule-checkbox').prop('checked', true);
    $('.saw-training-rule-item').css({
        'background': '#f0fdf4',
        'border': '2px solid #86efac'
    });
    updateProgress();
    <?php endif; ?>
});
</script>
