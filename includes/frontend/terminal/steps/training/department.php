<?php
/**
 * Terminal Training Step - Department Specific Risks
 * 
 * Display department/area specific hazards
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
$visit_id = $flow['visit_id'] ?? null;

// TODO: Load department-specific risks from database
$department_name = 'Výroba'; // TODO: Get from visit/host
$department_risks = [
    'cs' => [
        '🏭 Pohybující se stroje - udržujte bezpečnou vzdálenost',
        '🔊 Vysoká hlučnost - ochrana sluchu je povinná',
        '⚡ Elektrická zařízení - nedotýkejte se',
        '🔥 Vysoké teploty v některých prostorách',
        '📦 Riziko pádu materiálu z výšky',
    ],
    'en' => [
        '🏭 Moving machinery - keep safe distance',
        '🔊 High noise levels - hearing protection required',
        '⚡ Electrical equipment - do not touch',
        '🔥 High temperatures in some areas',
        '📦 Risk of falling materials from height',
    ],
    'uk' => [
        '🏭 Рухоме обладнання - тримайте безпечну відстань',
        '🔊 Високий рівень шуму - потрібен захист слуху',
        '⚡ Електричне обладнання - не торкатися',
        '🔥 Високі температури в деяких зонах',
        '📦 Ризик падіння матеріалів з висоти',
    ],
];

// Check if already completed
$completed = false;
if ($visitor_id) {
    global $wpdb;
    $visitor = $wpdb->get_row($wpdb->prepare(
        "SELECT training_step_department FROM {$wpdb->prefix}saw_visitors WHERE id = %d",
        $visitor_id
    ));
    $completed = !empty($visitor['training_step_department']);
}

$translations = [
    'cs' => [
        'title' => 'Rizika pracoviště',
        'subtitle' => 'Specifická rizika pro vaši návštěvu',
        'department' => 'Oddělení',
        'risks_title' => 'Specifická rizika tohoto pracoviště:',
        'confirm' => 'Potvrzuji, že jsem si přečetl/a všechna specifická rizika',
        'continue' => 'Pokračovat',
    ],
    'en' => [
        'title' => 'Workplace Hazards',
        'subtitle' => 'Specific risks for your visit',
        'department' => 'Department',
        'risks_title' => 'Specific risks of this workplace:',
        'confirm' => 'I confirm that I have read all specific risks',
        'continue' => 'Continue',
    ],
    'uk' => [
        'title' => 'Ризики робочого місця',
        'subtitle' => 'Специфічні ризики для вашого візиту',
        'department' => 'Відділ',
        'risks_title' => 'Специфічні ризики цього робочого місця:',
        'confirm' => 'Підтверджую, що прочитав усі специфічні ризики',
        'continue' => 'Продовжити',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];
$risks = $department_risks[$lang] ?? $department_risks['cs'];
?>

<div class="saw-terminal-card">
    <div class="saw-terminal-card-header">
        <h2 class="saw-terminal-card-title">
            🏭 <?php echo esc_html($t['title']); ?>
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
            <div class="saw-terminal-progress-step active">5</div>
        </div>
        
        <!-- Department info -->
        <div style="background: #f0f9ff; border: 2px solid #bae6fd; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; text-align: center;">
            <h3 style="margin: 0 0 0.5rem 0; font-size: 1rem; color: #0369a1; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">
                <?php echo esc_html($t['department']); ?>
            </h3>
            <p style="margin: 0; font-size: 1.75rem; color: #0c4a6e; font-weight: 700;">
                <?php echo esc_html($department_name); ?>
            </p>
        </div>
        
        <!-- Department-specific risks -->
        <div style="background: #fff5f5; border: 2px solid #fc8181; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem;">
            <h3 style="margin: 0 0 1.5rem 0; font-size: 1.25rem; font-weight: 700; color: #c53030;">
                <?php echo esc_html($t['risks_title']); ?>
            </h3>
            
            <div class="saw-training-dept-risks">
                <?php foreach ($risks as $index => $risk): ?>
                <div class="saw-training-dept-risk" 
                     style="display: flex; align-items: center; gap: 1rem; padding: 1.25rem; background: white; border-radius: 8px; margin-bottom: 0.75rem; border-left: 4px solid #fc8181;">
                    <span style="font-size: 2rem; flex-shrink: 0;">
                        <?php 
                        $icons = ['⚠️', '🚨', '⛔', '🔴', '❌'];
                        echo $icons[$index % count($icons)]; 
                        ?>
                    </span>
                    <span style="flex: 1; font-size: 1.125rem; color: #2d3748; font-weight: 500; line-height: 1.6;">
                        <?php echo esc_html($risk); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Required PPE (if any) -->
        <div style="background: #fffaf0; border: 2px solid #f6ad55; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem;">
            <h3 style="margin: 0 0 1rem 0; font-size: 1.125rem; font-weight: 700; color: #c05621;">
                <?php if ($lang === 'cs'): ?>
                    🦺 Požadované ochranné pomůcky:
                <?php elseif ($lang === 'en'): ?>
                    🦺 Required protective equipment:
                <?php else: ?>
                    🦺 Необхідні засоби захисту:
                <?php endif; ?>
            </h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                <div style="text-align: center; padding: 1rem; background: white; border-radius: 8px;">
                    <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🦺</div>
                    <p style="margin: 0; font-size: 0.875rem; color: #c05621; font-weight: 600;">
                        <?php if ($lang === 'cs'): ?>Reflexní vesta<?php elseif ($lang === 'en'): ?>Safety vest<?php else: ?>Жилет<?php endif; ?>
                    </p>
                </div>
                <div style="text-align: center; padding: 1rem; background: white; border-radius: 8px;">
                    <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">👷</div>
                    <p style="margin: 0; font-size: 0.875rem; color: #c05621; font-weight: 600;">
                        <?php if ($lang === 'cs'): ?>Přilba<?php elseif ($lang === 'en'): ?>Helmet<?php else: ?>Шолом<?php endif; ?>
                    </p>
                </div>
                <div style="text-align: center; padding: 1rem; background: white; border-radius: 8px;">
                    <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">👂</div>
                    <p style="margin: 0; font-size: 0.875rem; color: #c05621; font-weight: 600;">
                        <?php if ($lang === 'cs'): ?>Ochr. sluchu<?php elseif ($lang === 'en'): ?>Ear protection<?php else: ?>Захист слуху<?php endif; ?>
                    </p>
                </div>
                <div style="text-align: center; padding: 1rem; background: white; border-radius: 8px;">
                    <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">👓</div>
                    <p style="margin: 0; font-size: 0.875rem; color: #c05621; font-weight: 600;">
                        <?php if ($lang === 'cs'): ?>Ochranné brýle<?php elseif ($lang === 'en'): ?>Safety goggles<?php else: ?>Захисні окуляри<?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Confirmation form -->
        <form method="POST" id="training-dept-form">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="complete_training_department">
            
            <?php if (!$completed): ?>
            <div class="saw-terminal-form-checkbox" style="margin-bottom: 1.5rem;">
                <input type="checkbox" 
                       name="dept_risks_confirmed" 
                       id="dept-risks-confirmed" 
                       value="1"
                       required>
                <label for="dept-risks-confirmed">
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
    // Enable continue button when confirmation is checked
    $('#dept-risks-confirmed').on('change', function() {
        $('#continue-btn').prop('disabled', !$(this).is(':checked'));
    });
    
    // Animate risk items on load
    $('.saw-training-dept-risk').each(function(index) {
        $(this).css({
            'opacity': '0',
            'transform': 'translateX(-20px)'
        }).delay(index * 100).animate({
            'opacity': '1'
        }, 300).css('transform', 'translateX(0)');
    });
});
</script>
