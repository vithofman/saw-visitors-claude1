<?php
/**
 * Checkout Confirmation Dialog
 * 
 * Shown when last visitor(s) are checking out.
 * Allows user to choose between keeping visit active or completing it.
 * 
 * @package     SAW_Visitors
 * @subpackage  Frontend/Terminal
 * @since       3.1.0
 */

if (!defined('ABSPATH')) exit;

$flow = $flow ?? [];
$lang = $flow['language'] ?? 'cs';
$visit_info = $visit_info ?? [];
$visitor_ids = $visitor_ids ?? [];
$visitor_count = count($visitor_ids);

// Format dates
$planned_date_display = '—';
$effective_end_date = $visit_info['effective_end_date'] ?? null;

if (!empty($effective_end_date)) {
    $planned_date_display = date_i18n('d.m.Y', strtotime($effective_end_date));
}

$is_last_day = $visit_info['is_last_day'] ?? false;
$is_past_end_date = $visit_info['is_past_end_date'] ?? false;

// Person count label (Czech grammar)
function saw_person_label_checkout($count, $lang) {
    if ($lang === 'cs') {
        if ($count === 1) return 'osobu';
        if ($count >= 2 && $count <= 4) return 'osoby';
        return 'osob';
    }
    if ($lang === 'en') {
        return $count === 1 ? 'person' : 'people';
    }
    if ($lang === 'uk') {
        if ($count === 1) return 'особу';
        if ($count >= 2 && $count <= 4) return 'особи';
        return 'осіб';
    }
    if ($lang === 'sk') {
        if ($count === 1) return 'osobu';
        if ($count >= 2 && $count <= 4) return 'osoby';
        return 'osôb';
    }
    return 'osob';
}

// Translations
$translations = [
    'cs' => [
        'title' => 'Odhlášení návštěvy',
        'checking_out' => 'Odhlašujete',
        'warning' => 'Po tomto odhlášení nebude nikdo z vaší návštěvy přítomen.',
        'planned_until' => 'Návštěva plánována do',
        'last_day_notice' => 'Dnes je poslední den vaší návštěvy',
        'past_end_notice' => 'Plánovaný termín návštěvy již uplynul',
        'btn_return' => 'Ještě se vrátíme',
        'btn_return_desc' => 'Návštěva zůstane aktivní',
        'btn_complete' => 'Ukončit návštěvu',
        'btn_complete_desc' => 'Návštěva bude dokončena',
    ],
    'en' => [
        'title' => 'Visit Checkout',
        'checking_out' => 'Checking out',
        'warning' => 'After this checkout, no one from your visit will be present.',
        'planned_until' => 'Visit planned until',
        'last_day_notice' => 'Today is the last day of your visit',
        'past_end_notice' => 'The planned visit period has already ended',
        'btn_return' => 'We will return',
        'btn_return_desc' => 'Visit will remain active',
        'btn_complete' => 'Complete visit',
        'btn_complete_desc' => 'Visit will be finished',
    ],
    'sk' => [
        'title' => 'Odhlásenie návštevy',
        'checking_out' => 'Odhlasujete',
        'warning' => 'Po tomto odhlásení nebude nikto z vašej návštevy prítomný.',
        'planned_until' => 'Návšteva plánovaná do',
        'last_day_notice' => 'Dnes je posledný deň vašej návštevy',
        'past_end_notice' => 'Plánovaný termín návštevy už uplynul',
        'btn_return' => 'Ešte sa vrátime',
        'btn_return_desc' => 'Návšteva zostane aktívna',
        'btn_complete' => 'Ukončiť návštevu',
        'btn_complete_desc' => 'Návšteva bude dokončená',
    ],
    'uk' => [
        'title' => 'Виписка з візиту',
        'checking_out' => 'Виписуєте',
        'warning' => 'Після цієї виписки ніхто з вашого візиту не буде присутній.',
        'planned_until' => 'Візит заплановано до',
        'last_day_notice' => 'Сьогодні останній день вашого візиту',
        'past_end_notice' => 'Запланований термін візиту вже минув',
        'btn_return' => 'Ми ще повернемось',
        'btn_return_desc' => 'Візит залишиться активним',
        'btn_complete' => 'Завершити візит',
        'btn_complete_desc' => 'Візит буде завершено',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];
$person_label = saw_person_label_checkout($visitor_count, $lang);
?>

<style>
/* Checkout Confirmation Dialog Styles */
.saw-checkout-confirmation {
    max-width: 600px;
    margin: 0 auto;
    padding: 2rem;
    text-align: center;
}

.saw-confirmation-header {
    margin-bottom: 2rem;
}

.saw-confirmation-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    background: rgba(59, 130, 246, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #3b82f6;
}

.saw-confirmation-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}

.saw-confirmation-info {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 2rem;
}

.saw-info-box {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-radius: 12px;
    font-size: 1.05rem;
    text-align: left;
}

.saw-info-visitors {
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border: 1px solid #bae6fd;
}

.saw-info-warning {
    background: linear-gradient(135deg, #fefce8 0%, #fef9c3 100%);
    border: 1px solid #fde047;
}

.saw-info-date {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border: 1px solid #86efac;
}

.saw-info-icon {
    font-size: 1.5rem;
    flex-shrink: 0;
}

.saw-info-text {
    flex: 1;
    color: #334155;
}

.saw-info-text strong {
    color: #0f172a;
}

.saw-confirmation-form {
    margin-top: 1.5rem;
}

.saw-confirmation-buttons {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.saw-btn-confirm {
    display: flex;
    align-items: center;
    gap: 1rem;
    width: 100%;
    padding: 1.25rem 1.5rem;
    border: none;
    border-radius: 16px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: left;
    font-family: inherit;
}

.saw-btn-confirm:active {
    transform: scale(0.98);
}

.saw-btn-icon {
    font-size: 2rem;
    flex-shrink: 0;
}

.saw-btn-text {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.saw-btn-label {
    font-size: 1.25rem;
    font-weight: 600;
}

.saw-btn-desc {
    font-size: 0.875rem;
    opacity: 0.8;
}

/* Return button - blue */
.saw-btn-return {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
}

.saw-btn-return:hover {
    box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
}

/* Complete button - green */
.saw-btn-complete {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.saw-btn-complete:hover {
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
}

/* Mobile adjustments */
@media (max-width: 480px) {
    .saw-checkout-confirmation {
        padding: 1rem;
    }
    
    .saw-confirmation-title {
        font-size: 1.5rem;
    }
    
    .saw-btn-confirm {
        padding: 1rem;
    }
    
    .saw-btn-label {
        font-size: 1.1rem;
    }
}
</style>

<div class="saw-checkout-confirmation">
    <div class="saw-confirmation-header">
        <div class="saw-confirmation-icon">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
        </div>
        <h1 class="saw-confirmation-title"><?php echo esc_html($t['title']); ?></h1>
    </div>
    
    <div class="saw-confirmation-info">
        <!-- Počet osob -->
        <div class="saw-info-box saw-info-visitors">
            <span class="saw-info-icon">👥</span>
            <span class="saw-info-text">
                <?php echo esc_html($t['checking_out']); ?>: 
                <strong><?php echo $visitor_count; ?> <?php echo esc_html($person_label); ?></strong>
            </span>
        </div>
        
        <!-- Varování -->
        <div class="saw-info-box saw-info-warning">
            <span class="saw-info-icon">⚠️</span>
            <span class="saw-info-text"><?php echo esc_html($t['warning']); ?></span>
        </div>
        
        <!-- Datum -->
        <div class="saw-info-box saw-info-date">
            <span class="saw-info-icon">📅</span>
            <span class="saw-info-text">
                <?php if ($is_last_day): ?>
                    <strong><?php echo esc_html($t['last_day_notice']); ?></strong>
                <?php elseif ($is_past_end_date): ?>
                    <strong style="color: #ef4444;"><?php echo esc_html($t['past_end_notice']); ?></strong>
                <?php else: ?>
                    <?php echo esc_html($t['planned_until']); ?>: <strong><?php echo esc_html($planned_date_display); ?></strong>
                <?php endif; ?>
            </span>
        </div>
    </div>
    
    <form method="post" action="" class="saw-confirmation-form">
        <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
        <input type="hidden" name="terminal_action" value="checkout_confirm">
        <input type="hidden" name="visitor_ids" value="<?php echo esc_attr(implode(',', $visitor_ids)); ?>">
        <input type="hidden" name="visit_id" value="<?php echo esc_attr($visit_info['id'] ?? ''); ?>">
        
        <div class="saw-confirmation-buttons">
            <!-- Return button -->
            <button type="submit" name="checkout_action" value="return" class="saw-btn-confirm saw-btn-return">
                <span class="saw-btn-icon">↩️</span>
                <span class="saw-btn-text">
                    <span class="saw-btn-label"><?php echo esc_html($t['btn_return']); ?></span>
                    <span class="saw-btn-desc"><?php echo esc_html($t['btn_return_desc']); ?></span>
                </span>
            </button>
            
            <!-- Complete button -->
            <button type="submit" name="checkout_action" value="complete" class="saw-btn-confirm saw-btn-complete">
                <span class="saw-btn-icon">✅</span>
                <span class="saw-btn-text">
                    <span class="saw-btn-label"><?php echo esc_html($t['btn_complete']); ?></span>
                    <span class="saw-btn-desc"><?php echo esc_html($t['btn_complete_desc']); ?></span>
                </span>
            </button>
        </div>
    </form>
</div>