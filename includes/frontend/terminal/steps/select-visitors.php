<?php
/**
 * Terminal Step - Visitor Selection (Planned Visit)
 * 
 * Select which registered visitors actually arrived
 * Can add more visitors if someone not on the list shows up
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
        'title' => 'Kdo přišel?',
        'subtitle' => 'Označte přítomné návštěvníky',
        'skip_training' => 'Absolvoval/a školení do 1 roku',
        'continue' => 'Pokračovat',
        'add_more' => '+ Přidat dalšího návštěvníka',
        'add_more_text' => 'Přišla ještě další osoba, která není v seznamu?',
    ],
    'en' => [
        'title' => 'Who arrived?',
        'subtitle' => 'Select visitors who are present',
        'skip_training' => 'Completed training within 1 year',
        'continue' => 'Continue',
        'add_more' => '+ Add Another Visitor',
        'add_more_text' => 'Did someone else arrive who is not on the list?',
    ],
    'uk' => [
        'title' => 'Хто прийшов?',
        'subtitle' => 'Позначте присутніх відвідувачів',
        'skip_training' => 'Пройшов навчання протягом 1 року',
        'continue' => 'Продовжити',
        'add_more' => '+ Додати іншого відвідувача',
        'add_more_text' => 'Хтось інший прийшов, кого немає в списку?',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];
?>

<div class="saw-terminal-card">
    <div class="saw-terminal-card-header">
        <h2 class="saw-terminal-card-title">
            👥 <?php echo esc_html($t['title']); ?>
        </h2>
        <p class="saw-terminal-card-subtitle">
            <?php echo esc_html($t['subtitle']); ?>
        </p>
    </div>
    
    <div class="saw-terminal-card-body">
        <form method="POST" class="saw-terminal-form">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="confirm_visitors">
            
            <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2rem;">
                <?php foreach ($visitors as $visitor): ?>
                <label class="saw-terminal-visitor-card">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <input type="checkbox" 
                               name="visitor_ids[]" 
                               value="<?php echo $visitor['id']; ?>" 
                               checked 
                               style="width: 24px; height: 24px; cursor: pointer;">
                        <div style="flex: 1;">
                            <div style="font-size: 1.25rem; font-weight: 700; color: #1e293b;">
                                <?php echo esc_html($visitor['first_name'] . ' ' . $visitor['last_name']); ?>
                            </div>
                            <?php if (!empty($visitor['position'])): ?>
                                <div style="color: #64748b; font-size: 0.875rem;">
                                    <?php echo esc_html($visitor['position']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid #e2e8f0;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; color: #475569; cursor: pointer;">
                            <input type="checkbox" 
                                   name="training_skip[<?php echo $visitor['id']; ?>]" 
                                   value="1"
                                   style="cursor: pointer;">
                            <span><?php echo esc_html($t['skip_training']); ?></span>
                        </label>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
            
            <button type="submit" class="saw-terminal-btn saw-terminal-btn-success" style="width: 100%;">
                <?php echo esc_html($t['continue']); ?> →
            </button>
        </form>
        
        <!-- Přidat další návštěvníky -->
        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px dashed #e2e8f0; text-align: center;">
            <p style="color: #64748b; margin-bottom: 1rem; font-size: 0.9375rem;">
                <?php echo esc_html($t['add_more_text']); ?>
            </p>
            <a href="<?php echo home_url('/terminal/register/'); ?>" 
               class="saw-terminal-btn saw-terminal-btn-secondary" 
               style="display: inline-block; text-decoration: none;">
                <?php echo esc_html($t['add_more']); ?>
            </a>
        </div>
    </div>
</div>

<style>
.saw-terminal-visitor-card {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.2s;
}

.saw-terminal-visitor-card:hover {
    border-color: #10b981;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
}

.saw-terminal-btn-secondary {
    background: #f1f5f9;
    color: #475569;
    border: 2px solid #cbd5e1;
}

.saw-terminal-btn-secondary:hover {
    background: #e2e8f0;
    border-color: #94a3b8;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(71, 85, 105, 0.2);
}
</style>