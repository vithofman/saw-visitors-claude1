<?php
/**
 * Checkout Confirmation Dialog
 * Shown when last visitor(s) are checking out
 * 
 * Design: Unified with other terminal pages (action.php style)
 * 
 * @package SAW_Visitors
 * @since 3.2.0
 */

if (!defined('ABSPATH')) exit;

$flow = $flow ?? [];
$lang = $flow['language'] ?? 'cs';
$visit_info = $visit_info ?? [];
$visitor_ids = $visitor_ids ?? [];
$visitor_count = count($visitor_ids);

// Format dates
$effective_end_date = $visit_info['effective_end_date'] ?? null;
$planned_date_display = '—';

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
    if ($lang === 'sk') {
        if ($count === 1) return 'osobu';
        if ($count >= 2 && $count <= 4) return 'osoby';
        return 'osôb';
    }
    if ($lang === 'uk') {
        if ($count === 1) return 'особу';
        if ($count >= 2 && $count <= 4) return 'особи';
        return 'осіб';
    }
    return 'osob';
}

// Translations
$translations = [
    'cs' => [
        'title' => 'Odhlášení návštěvy',
        'subtitle' => 'Vyberte jednu z možností',
        'checking_out' => 'Odhlašujete',
        'warning' => 'Po tomto odhlášení nebude nikdo z vaší návštěvy přítomen.',
        'planned_until' => 'Návštěva plánována do',
        'last_day_notice' => 'Dnes je poslední den vaší návštěvy',
        'past_end_notice' => 'Plánovaný termín návštěvy již uplynul',
        'btn_return' => 'Ještě se vrátíme',
        'btn_return_desc' => 'Návštěva zůstane aktivní',
        'btn_return_example' => 'např. pauza na oběd, schůzka venku',
        'btn_complete' => 'Ukončit návštěvu',
        'btn_complete_desc' => 'Návštěva bude dokončena',
        'btn_complete_example' => 'definitivní odchod z areálu',
    ],
    'en' => [
        'title' => 'Visit Checkout',
        'subtitle' => 'Choose an option',
        'checking_out' => 'Checking out',
        'warning' => 'After this checkout, no one from your visit will be present.',
        'planned_until' => 'Visit planned until',
        'last_day_notice' => 'Today is the last day of your visit',
        'past_end_notice' => 'The planned visit period has already ended',
        'btn_return' => 'We will return',
        'btn_return_desc' => 'Visit remains active',
        'btn_return_example' => 'e.g. lunch break, outside meeting',
        'btn_complete' => 'Complete visit',
        'btn_complete_desc' => 'Visit will be finished',
        'btn_complete_example' => 'leaving the premises',
    ],
    'sk' => [
        'title' => 'Odhlásenie návštevy',
        'subtitle' => 'Vyberte jednu z možností',
        'checking_out' => 'Odhlasujete',
        'warning' => 'Po tomto odhlásení nebude nikto z vašej návštevy prítomný.',
        'planned_until' => 'Návšteva plánovaná do',
        'last_day_notice' => 'Dnes je posledný deň vašej návštevy',
        'past_end_notice' => 'Plánovaný termín návštevy už uplynul',
        'btn_return' => 'Ešte sa vrátime',
        'btn_return_desc' => 'Návšteva zostane aktívna',
        'btn_return_example' => 'napr. pauza na obed, stretnutie vonku',
        'btn_complete' => 'Ukončiť návštevu',
        'btn_complete_desc' => 'Návšteva bude dokončená',
        'btn_complete_example' => 'definitívny odchod z areálu',
    ],
    'uk' => [
        'title' => 'Виписка з візиту',
        'subtitle' => 'Виберіть опцію',
        'checking_out' => 'Виписуєте',
        'warning' => 'Після цієї виписки ніхто з вашого візиту не буде присутній.',
        'planned_until' => 'Візит заплановано до',
        'last_day_notice' => 'Сьогодні останній день вашого візиту',
        'past_end_notice' => 'Запланований термін візиту вже минув',
        'btn_return' => 'Ми ще повернемось',
        'btn_return_desc' => 'Візит залишиться активним',
        'btn_return_example' => 'напр. обідня перерва, зустріч надворі',
        'btn_complete' => 'Завершити візит',
        'btn_complete_desc' => 'Візит буде завершено',
        'btn_complete_example' => 'остаточний вихід з території',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];
$person_label = saw_person_label_checkout($visitor_count, $lang);
?>
<style>
/* === CHECKOUT CONFIRMATION - UNIFIED TERMINAL STYLE === */
:root {
    --theme-color: #667eea;
    --theme-color-hover: #764ba2;
    --bg-dark: #1a202c;
    --bg-dark-medium: #2d3748;
    --bg-glass: rgba(15, 23, 42, 0.6);
    --bg-glass-light: rgba(255, 255, 255, 0.08);
    --border-glass: rgba(148, 163, 184, 0.12);
    --text-primary: #FFFFFF;
    --text-secondary: #e5e7eb;
    --text-muted: #9ca3af;
}

*,
*::before,
*::after {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

.saw-terminal-footer {
    display: none !important;
}

.saw-confirm-aurora {
    position: fixed;
    inset: 0;
    width: 100vw;
    height: 100vh;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: var(--text-secondary);
    background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

.saw-confirm-content {
    max-width: 800px;
    width: 100%;
    animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Header */
.saw-confirm-header {
    text-align: center;
    margin-bottom: 2.5rem;
}

.saw-confirm-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    animation: scaleIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) 0.2s both;
}

@keyframes scaleIn {
    from { opacity: 0; transform: scale(0.8); }
    to { opacity: 1; transform: scale(1); }
}

.saw-confirm-title {
    font-size: 2.25rem;
    font-weight: 700;
    background: linear-gradient(135deg, #f9fafb 0%, #cbd5e1 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: -0.02em;
    margin-bottom: 0.5rem;
}

.saw-confirm-subtitle {
    font-size: 1.125rem;
    color: rgba(203, 213, 225, 0.8);
    font-weight: 500;
}

/* Info Section - Centered Stack */
.saw-confirm-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 2.5rem;
    animation: fadeIn 0.6s ease 0.2s both;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.saw-info-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.625rem;
    padding: 0.75rem 1.5rem;
    background: var(--bg-glass);
    backdrop-filter: blur(20px);
    border: 1px solid var(--border-glass);
    border-radius: 50px;
    font-size: 0.9375rem;
    font-weight: 500;
    color: var(--text-secondary);
    width: auto;
}

.saw-info-badge.is-warning {
    background: rgba(245, 158, 11, 0.15);
    border-color: rgba(245, 158, 11, 0.3);
    color: #fcd34d;
}

.saw-info-badge.is-date {
    background: rgba(59, 130, 246, 0.15);
    border-color: rgba(59, 130, 246, 0.3);
    color: #93c5fd;
}

.saw-info-badge.is-last-day {
    background: rgba(239, 68, 68, 0.15);
    border-color: rgba(239, 68, 68, 0.3);
    color: #fca5a5;
}

.saw-info-badge .badge-icon {
    font-size: 1.125rem;
}

/* Action Grid */
.saw-confirm-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
    animation: fadeIn 0.6s ease 0.3s both;
}

/* Action Tiles */
.saw-confirm-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    padding: 2rem 1.5rem;
    background: var(--bg-glass);
    backdrop-filter: blur(20px) saturate(180%);
    border: 2px solid var(--border-glass);
    border-radius: 20px;
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    text-decoration: none;
    min-height: 260px;
    width: 100%;
    font-family: inherit;
}

.saw-confirm-btn:hover {
    transform: translateY(-6px);
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.4);
}

.saw-confirm-btn:active {
    transform: translateY(-3px);
}

/* Return Button (Blue) */
.saw-confirm-btn.is-return {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(37, 99, 235, 0.15));
    border-color: rgba(59, 130, 246, 0.4);
}

.saw-confirm-btn.is-return:hover {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.25), rgba(37, 99, 235, 0.25));
    border-color: rgba(59, 130, 246, 0.6);
    box-shadow: 0 16px 48px rgba(59, 130, 246, 0.3);
}

.saw-confirm-btn.is-return .btn-icon-wrap {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    box-shadow: 0 8px 24px rgba(59, 130, 246, 0.4);
}

/* Complete Button (Green) */
.saw-confirm-btn.is-complete {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.15));
    border-color: rgba(16, 185, 129, 0.4);
}

.saw-confirm-btn.is-complete:hover {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.25), rgba(5, 150, 105, 0.25));
    border-color: rgba(16, 185, 129, 0.6);
    box-shadow: 0 16px 48px rgba(16, 185, 129, 0.3);
}

.saw-confirm-btn.is-complete .btn-icon-wrap {
    background: linear-gradient(135deg, #10b981, #059669);
    box-shadow: 0 8px 24px rgba(16, 185, 129, 0.4);
}

/* Icon wrapper */
.btn-icon-wrap {
    width: 72px;
    height: 72px;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.25rem;
    transition: all 0.3s ease;
}

.saw-confirm-btn:hover .btn-icon-wrap {
    transform: scale(1.1);
}

/* Text */
.btn-text {
    text-align: center;
}

.btn-label {
    display: block;
    font-size: 1.375rem;
    font-weight: 700;
    letter-spacing: -0.02em;
    margin-bottom: 0.375rem;
    color: var(--text-primary);
}

.btn-desc {
    display: block;
    font-size: 0.9375rem;
    color: var(--text-muted);
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.btn-example {
    display: block;
    font-size: 0.8125rem;
    color: rgba(148, 163, 184, 0.7);
    font-style: italic;
    font-weight: 400;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .saw-confirm-aurora {
        padding: 1rem;
    }
    
    .saw-confirm-title {
        font-size: 1.75rem;
    }
    
    .saw-confirm-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .saw-confirm-btn {
        min-height: 180px;
        padding: 1.5rem 1.25rem;
    }
    
    .btn-icon-wrap {
        width: 60px;
        height: 60px;
        font-size: 1.75rem;
    }
    
    .btn-label {
        font-size: 1.25rem;
    }
    
    .saw-info-badge {
        font-size: 0.875rem;
        padding: 0.625rem 1.25rem;
    }
}

@media (max-width: 480px) {
    .saw-confirm-icon {
        font-size: 3rem;
    }
    
    .saw-confirm-title {
        font-size: 1.5rem;
    }
    
    .saw-confirm-header {
        margin-bottom: 1.5rem;
    }
    
    .saw-confirm-info {
        margin-bottom: 1.5rem;
    }
}
</style>

<div class="saw-confirm-aurora">
    <div class="saw-confirm-content">
        
        <!-- Header -->
        <div class="saw-confirm-header">
            <div class="saw-confirm-icon">🚪</div>
            <h1 class="saw-confirm-title"><?php echo esc_html($t['title']); ?></h1>
            <p class="saw-confirm-subtitle"><?php echo esc_html($t['subtitle']); ?></p>
        </div>
        
        <!-- Info Badges - Stacked & Centered -->
        <div class="saw-confirm-info">
            <div class="saw-info-badge">
                <span class="badge-icon">👥</span>
                <span><?php echo esc_html($t['checking_out']); ?>: <strong><?php echo $visitor_count; ?> <?php echo esc_html($person_label); ?></strong></span>
            </div>
            
            <div class="saw-info-badge is-warning">
                <span class="badge-icon">⚠️</span>
                <span><?php echo esc_html($t['warning']); ?></span>
            </div>
            
            <?php if ($is_last_day): ?>
                <div class="saw-info-badge is-last-day">
                    <span class="badge-icon">📅</span>
                    <span><?php echo esc_html($t['last_day_notice']); ?></span>
                </div>
            <?php elseif ($is_past_end_date): ?>
                <div class="saw-info-badge is-last-day">
                    <span class="badge-icon">⏰</span>
                    <span><?php echo esc_html($t['past_end_notice']); ?></span>
                </div>
            <?php else: ?>
                <div class="saw-info-badge is-date">
                    <span class="badge-icon">📅</span>
                    <span><?php echo esc_html($t['planned_until']); ?>: <strong><?php echo esc_html($planned_date_display); ?></strong></span>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Action Grid -->
        <form method="post" action="">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="checkout_confirm">
            <input type="hidden" name="visitor_ids" value="<?php echo esc_attr(implode(',', $visitor_ids)); ?>">
            <input type="hidden" name="visit_id" value="<?php echo esc_attr($visit_info['id'] ?? ''); ?>">
            
            <div class="saw-confirm-grid">
                
                <!-- Return Button -->
                <button type="submit" name="checkout_action" value="return" class="saw-confirm-btn is-return">
                    <div class="btn-icon-wrap">↩️</div>
                    <div class="btn-text">
                        <span class="btn-label"><?php echo esc_html($t['btn_return']); ?></span>
                        <span class="btn-desc"><?php echo esc_html($t['btn_return_desc']); ?></span>
                        <span class="btn-example"><?php echo esc_html($t['btn_return_example']); ?></span>
                    </div>
                </button>
                
                <!-- Complete Button -->
                <button type="submit" name="checkout_action" value="complete" class="saw-confirm-btn is-complete">
                    <div class="btn-icon-wrap">✓</div>
                    <div class="btn-text">
                        <span class="btn-label"><?php echo esc_html($t['btn_complete']); ?></span>
                        <span class="btn-desc"><?php echo esc_html($t['btn_complete_desc']); ?></span>
                        <span class="btn-example"><?php echo esc_html($t['btn_complete_example']); ?></span>
                    </div>
                </button>
                
            </div>
        </form>
        
    </div>
</div>