<?php
/**
 * Terminal Step - Checkout Visitor Selection
 * 
 * Select which visitors are leaving
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$flow = $this->session->get('terminal_flow');
$lang = $flow['language'] ?? 'cs';
$visitors = $visitors ?? [];

$translations = [
    'cs' => [
        'title' => 'Kdo odchází?',
        'subtitle' => 'Označte všechny odcházející osoby',
        'select_all' => 'Vybrat všechny',
        'deselect_all' => 'Zrušit výběr',
        'submit' => 'Odhlásit vybrané',
        'no_visitors' => 'Žádní návštěvníci nejsou momentálně přítomni',
        'checked_in_at' => 'Přítomen od',
    ],
    'en' => [
        'title' => 'Who is leaving?',
        'subtitle' => 'Select all departing persons',
        'select_all' => 'Select All',
        'deselect_all' => 'Deselect All',
        'submit' => 'Check out selected',
        'no_visitors' => 'No visitors are currently present',
        'checked_in_at' => 'Present since',
    ],
    'uk' => [
        'title' => 'Хто від\'їжджає?',
        'subtitle' => 'Позначте всіх осіб, які від\'їжджають',
        'select_all' => 'Вибрати всіх',
        'deselect_all' => 'Скасувати вибір',
        'submit' => 'Виписати вибраних',
        'no_visitors' => 'Наразі немає присутніх відвідувачів',
        'checked_in_at' => 'Присутній з',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];
?>

<div class="saw-terminal-card saw-terminal-card-wide">
    <div class="saw-terminal-card-header">
        <h2 class="saw-terminal-card-title">
            🚪 <?php echo esc_html($t['title']); ?>
        </h2>
        <p class="saw-terminal-card-subtitle">
            <?php echo esc_html($t['subtitle']); ?>
        </p>
    </div>
    
    <div class="saw-terminal-card-body">
        
        <?php if (empty($visitors)): ?>
            <div class="saw-terminal-empty-state">
                <p><?php echo esc_html($t['no_visitors']); ?></p>
            </div>
        <?php else: ?>
        
        <form method="POST" class="saw-terminal-form" id="checkout-form">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="checkout_complete">
            
            <!-- Select All Button -->
            <button type="button" 
                    class="saw-terminal-btn saw-terminal-btn-secondary" 
                    id="toggle-all-btn"
                    style="margin-bottom: 2rem; width: 100%;">
                ✅ <?php echo esc_html($t['select_all']); ?>
            </button>
            
            <!-- Visitor List -->
            <div class="saw-checkout-visitor-list">
                <?php foreach ($visitors as $visitor): ?>
                <label class="saw-checkout-visitor-card">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <input type="checkbox" 
                               name="visitor_ids[]" 
                               value="<?php echo $visitor['id']; ?>" 
                               checked
                               style="width: 24px; height: 24px; cursor: pointer;">
                        
                        <div class="saw-checkout-visitor-avatar">
                            <?php echo strtoupper(substr($visitor['first_name'], 0, 1)); ?>
                        </div>
                        
                        <div style="flex: 1;">
                            <div style="font-size: 1.125rem; font-weight: 700; color: #1e293b;">
                                <?php echo esc_html($visitor['first_name'] . ' ' . $visitor['last_name']); ?>
                            </div>
                            
                            <?php if (!empty($visitor['position'])): ?>
                                <div style="color: #64748b; font-size: 0.875rem;">
                                    <?php echo esc_html($visitor['position']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($visitor['checked_in_at'])): ?>
                                <div style="color: #10b981; font-size: 0.875rem; margin-top: 0.25rem;">
                                    🕐 <?php echo esc_html($t['checked_in_at']); ?>: 
                                    <?php echo date('H:i', strtotime($visitor['checked_in_at'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
            
            <!-- Submit Button -->
            <div class="saw-terminal-form-submit" style="margin-top: 2rem;">
                <button type="submit" class="saw-terminal-btn saw-terminal-btn-danger">
                    🚪 <?php echo esc_html($t['submit']); ?> (<?php echo count($visitors); ?>)
                </button>
            </div>
            
        </form>
        
        <?php endif; ?>
        
    </div>
</div>

<style>
.saw-checkout-visitor-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.saw-checkout-visitor-card {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.2s;
}

.saw-checkout-visitor-card:hover {
    border-color: #ef4444;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.15);
}

.saw-checkout-visitor-card:has(input:checked) {
    border-color: #ef4444;
    background: #fef2f2;
}

.saw-checkout-visitor-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    font-weight: 700;
}

.saw-terminal-empty-state {
    text-align: center;
    padding: 3rem;
    color: #64748b;
    font-size: 1.125rem;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle all checkboxes
    $('#toggle-all-btn').on('click', function() {
        const allChecked = $('input[name="visitor_ids[]"]:checked').length === $('input[name="visitor_ids[]"]').length;
        
        $('input[name="visitor_ids[]"]').prop('checked', !allChecked);
        
        if (allChecked) {
            $(this).html('✅ <?php echo esc_js($t['select_all']); ?>');
        } else {
            $(this).html('❌ <?php echo esc_js($t['deselect_all']); ?>');
        }
    });
    
    // Update button count on checkbox change
    $('input[name="visitor_ids[]"]').on('change', function() {
        const count = $('input[name="visitor_ids[]"]:checked').length;
        $('.saw-terminal-btn-danger').html('🚪 <?php echo esc_js($t['submit']); ?> (' + count + ')');
    });
});
</script>