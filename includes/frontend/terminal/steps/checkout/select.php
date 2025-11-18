<?php
/**
 * Terminal Step - Checkout Visitor Selection (Unified Design)
 * 
 * @package SAW_Visitors
 * @version 3.3.0
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
    'sk' => [
        'title' => 'Kto odchádza?',
        'subtitle' => 'Označte všetky odchádzajúce osoby',
        'select_all' => 'Vybrať všetkých',
        'deselect_all' => 'Zrušiť výber',
        'submit' => 'Odhlásiť vybraných',
        'no_visitors' => 'Momentálne nie sú žiadni prítomní návštevníci',
        'checked_in_at' => 'Prítomný od',
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
<style>
/* === UNIFIED STYLE === */
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
    --color-danger: #ef4444;
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

.saw-checkout-select-aurora {
    position: fixed;
    inset: 0;
    width: 100vw;
    height: 100vh;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: var(--text-secondary);
    background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
    overflow: hidden;
}

.saw-checkout-select-wrapper {
    height: 100%;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 3rem 2rem 10rem;
}

.saw-checkout-select-wrapper::-webkit-scrollbar {
    width: 8px;
}

.saw-checkout-select-wrapper::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.2);
}

.saw-checkout-select-wrapper::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.3);
    border-radius: 999px;
}

.saw-checkout-select-layout {
    max-width: 900px;
    margin: 0 auto;
}

/* Header */
.saw-checkout-select-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2.5rem;
    padding: 2rem 2.5rem;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(20px) saturate(180%);
    border-radius: 20px;
    border: 1px solid var(--border-glass);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.saw-checkout-select-icon {
    width: 4rem;
    height: 4rem;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.25rem;
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    border-radius: 18px;
    box-shadow: 
        0 10px 30px rgba(239, 68, 68, 0.3),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.saw-checkout-select-title {
    font-size: 2rem;
    font-weight: 700;
    background: linear-gradient(135deg, #f9fafb 0%, #cbd5e1 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: -0.02em;
    margin-bottom: 0.375rem;
}

.saw-checkout-select-subtitle {
    font-size: 0.9375rem;
    color: rgba(203, 213, 225, 0.7);
    font-weight: 500;
}

/* Toggle Button */
.saw-toggle-all-btn {
    width: 100%;
    padding: 1rem;
    background: rgba(102, 126, 234, 0.2);
    border: 1px solid rgba(102, 126, 234, 0.4);
    border-radius: 12px;
    color: #818cf8;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    margin-bottom: 2rem;
}

.saw-toggle-all-btn:hover {
    background: rgba(102, 126, 234, 0.3);
    border-color: rgba(102, 126, 234, 0.6);
    transform: translateY(-2px);
}

/* Visitor List */
.saw-visitor-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 2rem;
}

.saw-visitor-checkbox-card {
    background: var(--bg-glass);
    backdrop-filter: blur(20px) saturate(180%);
    border-radius: 16px;
    border: 1px solid var(--border-glass);
    padding: 1.5rem;
    transition: all 0.2s;
    cursor: pointer;
}

.saw-visitor-checkbox-card:hover {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(239, 68, 68, 0.5);
}

.saw-visitor-checkbox-card.checked {
    background: rgba(239, 68, 68, 0.15);
    border-color: rgba(239, 68, 68, 0.5);
}

.saw-visitor-header {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.saw-visitor-header input[type="checkbox"] {
    width: 24px;
    height: 24px;
    cursor: pointer;
    accent-color: var(--color-danger);
    flex-shrink: 0;
}

.saw-visitor-avatar {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 700;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.saw-visitor-info {
    flex: 1;
}

.saw-visitor-name {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.saw-visitor-position {
    font-size: 0.875rem;
    color: rgba(203, 213, 225, 0.7);
    margin-bottom: 0.375rem;
}

.saw-visitor-time {
    font-size: 0.875rem;
    color: #10b981;
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

/* Empty State */
.saw-empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--bg-glass);
    backdrop-filter: blur(20px) saturate(180%);
    border-radius: 20px;
    border: 1px solid var(--border-glass);
}

.saw-empty-state-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.saw-empty-state-text {
    font-size: 1.125rem;
    color: rgba(203, 213, 225, 0.7);
}

/* Submit Button */
.saw-submit-btn {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    padding: 1rem 2.5rem;
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    border: none;
    border-radius: 16px;
    font-weight: 700;
    font-size: 1.125rem;
    cursor: pointer;
    box-shadow: 0 10px 30px rgba(239, 68, 68, 0.4);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    z-index: 200;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.saw-submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 40px rgba(239, 68, 68, 0.6);
}

/* Responsive */
@media (max-width: 768px) {
    .saw-checkout-select-wrapper {
        padding: 2rem 1rem 12rem;
    }
    
    .saw-checkout-select-header {
        gap: 1rem;
        padding: 1.5rem;
    }
    
    .saw-checkout-select-icon {
        width: 3rem;
        height: 3rem;
        font-size: 1.75rem;
    }
    
    .saw-checkout-select-title {
        font-size: 1.5rem;
    }
    
    .saw-visitor-avatar {
        width: 48px;
        height: 48px;
        font-size: 1.25rem;
    }
    
    .saw-submit-btn {
        bottom: 1rem;
        right: 1rem;
        left: 1rem;
        width: auto;
    }
}
</style>

<div class="saw-checkout-select-aurora">
    <div class="saw-checkout-select-wrapper">
        <div class="saw-checkout-select-layout">
            
            <!-- Header -->
            <header class="saw-checkout-select-header">
                <div class="saw-checkout-select-icon">🚪</div>
                <div>
                    <h1 class="saw-checkout-select-title"><?php echo esc_html($t['title']); ?></h1>
                    <p class="saw-checkout-select-subtitle"><?php echo esc_html($t['subtitle']); ?></p>
                </div>
            </header>
            
            <?php if (empty($visitors)): ?>
                
                <!-- Empty State -->
                <div class="saw-empty-state">
                    <div class="saw-empty-state-icon">😔</div>
                    <p class="saw-empty-state-text"><?php echo esc_html($t['no_visitors']); ?></p>
                </div>
                
            <?php else: ?>
                
                <form method="POST" id="checkout-form">
                    <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
                    <input type="hidden" name="terminal_action" value="checkout_complete">
                    
                    <!-- Toggle All Button -->
                    <button type="button" class="saw-toggle-all-btn" id="toggle-all-btn">
                        ✅ <?php echo esc_html($t['select_all']); ?>
                    </button>
                    
                    <!-- Visitor List -->
                    <div class="saw-visitor-list">
                        <?php foreach ($visitors as $visitor): ?>
                        <div class="saw-visitor-checkbox-card" data-visitor-id="<?php echo $visitor['id']; ?>">
                            <div class="saw-visitor-header">
                                <input type="checkbox" 
                                       name="visitor_ids[]" 
                                       value="<?php echo $visitor['id']; ?>" 
                                       checked>
                                
                                <div class="saw-visitor-avatar">
                                    <?php echo strtoupper(substr($visitor['first_name'], 0, 1)); ?>
                                </div>
                                
                                <div class="saw-visitor-info">
                                    <div class="saw-visitor-name">
                                        <?php echo esc_html($visitor['first_name'] . ' ' . $visitor['last_name']); ?>
                                    </div>
                                    
                                    <?php if (!empty($visitor['position'])): ?>
                                    <div class="saw-visitor-position">
                                        <?php echo esc_html($visitor['position']); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($visitor['checked_in_at'])): ?>
                                    <div class="saw-visitor-time">
                                        <span>🕐</span>
                                        <span><?php echo esc_html($t['checked_in_at']); ?>: <?php echo date('H:i', strtotime($visitor['checked_in_at'])); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" class="saw-submit-btn" id="submit-btn">
                        🚪 <?php echo esc_html($t['submit']); ?> (<span id="count"><?php echo count($visitors); ?></span>)
                    </button>
                    
                </form>
                
            <?php endif; ?>
            
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';
    
    const toggleBtn = document.getElementById('toggle-all-btn');
    const checkboxes = document.querySelectorAll('input[name="visitor_ids[]"]');
    const countSpan = document.getElementById('count');
    const cards = document.querySelectorAll('.saw-visitor-checkbox-card');
    
    // Update card appearance based on checkbox state
    function updateCardAppearance(checkbox) {
        const card = checkbox.closest('.saw-visitor-checkbox-card');
        if (checkbox.checked) {
            card.classList.add('checked');
        } else {
            card.classList.remove('checked');
        }
    }
    
    // Update count
    function updateCount() {
        const checked = document.querySelectorAll('input[name="visitor_ids[]"]:checked').length;
        if (countSpan) {
            countSpan.textContent = checked;
        }
    }
    
    // Toggle all
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(cb => {
                cb.checked = !allChecked;
                updateCardAppearance(cb);
            });
            
            if (allChecked) {
                this.innerHTML = '✅ <?php echo esc_js($t['select_all']); ?>';
            } else {
                this.innerHTML = '❌ <?php echo esc_js($t['deselect_all']); ?>';
            }
            
            updateCount();
        });
    }
    
    // Checkbox change
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateCardAppearance(this);
            updateCount();
        });
        
        // Initial state
        updateCardAppearance(checkbox);
    });
    
    // Click on card = toggle checkbox
    cards.forEach(card => {
        card.addEventListener('click', function(e) {
            // Don't toggle if clicking directly on checkbox
            if (e.target.type === 'checkbox') return;
            
            const checkbox = this.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
            updateCardAppearance(checkbox);
            updateCount();
        });
    });
    
    // Initial count
    updateCount();
})();
</script>

<?php
error_log("[CHECKOUT-SELECT.PHP] Unified design loaded (v3.3.0)");
?>