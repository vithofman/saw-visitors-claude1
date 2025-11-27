<?php
/**
 * Progress Indicator Component
 * 
 * Zobrazuje vizuální progres skrze invitation flow.
 * Ukazuje dokončené, aktuální a zbývající kroky.
 * Podporuje klikatelnou navigaci a mobilní verzi.
 * 
 * @package SAW_Visitors
 * @since 2.0.0
 */

if (!defined('ABSPATH')) exit;

// Použij proměnné předané z render_header()
$completed = $flow['completed_steps'] ?? [];
$history = $flow['history'] ?? [];
$current = $current_step;
$token = $token ?? ($flow['token'] ?? '');

// Zjisti jazyk pro UI
$lang = $flow['language'] ?? 'cs';
$title_key = ($lang === 'en') ? 'title_en' : 'title_cs';

// Definuj ZÁKLADNÍ kroky (vždy zobrazené)
$all_steps = [
    'language' => [
        'icon' => '🌐',
        'title_cs' => 'Jazyk',
        'title_en' => 'Language'
    ],
    'risks' => [
        'icon' => '⚠️',
        'title_cs' => 'Rizika',
        'title_en' => 'Risks'
    ],
    'visitors' => [
        'icon' => '👥',
        'title_cs' => 'Návštěvníci',
        'title_en' => 'Visitors'
    ],
];

// ✅ OPRAVENO: Přidej POUZE DOSTUPNÉ training kroky
if (isset($available_training_steps) && !empty($available_training_steps)) {
    $training_step_definitions = [
        'training-video' => ['icon' => '🎥', 'title_cs' => 'Video', 'title_en' => 'Video'],
        'training-map' => ['icon' => '🗺️', 'title_cs' => 'Mapa', 'title_en' => 'Map'],
        'training-risks' => ['icon' => '⚠️', 'title_cs' => 'Školení rizika', 'title_en' => 'Training Risks'],
        'training-department' => ['icon' => '🏢', 'title_cs' => 'Oddělení', 'title_en' => 'Department'],
        'training-additional' => ['icon' => 'ℹ️', 'title_cs' => 'Další info', 'title_en' => 'Additional'],
    ];
    
    // Přidej JEN kroky které jsou v $available_training_steps
    foreach ($available_training_steps as $step) {
        $step_key = $step['step'];
        if (isset($training_step_definitions[$step_key])) {
            $all_steps[$step_key] = $training_step_definitions[$step_key];
        }
    }
}

// Přidat summary a success na konec
$all_steps['summary'] = [
    'icon' => '📋',
    'title_cs' => 'Přehled',
    'title_en' => 'Summary'
];

$all_steps['success'] = [
    'icon' => '✅',
    'title_cs' => 'Hotovo',
    'title_en' => 'Done'
];

// Najdi index aktuálního kroku pro mobilní zobrazení
$current_index = 0;
$step_keys = array_keys($all_steps);
foreach ($step_keys as $index => $step) {
    if ($step === $current) {
        $current_index = $index;
        break;
    }
}
?>

<style>
/* Desktop Progress Indicator */
.progress-indicator {
    position: fixed;
    top: 6rem;
    left: 1.5rem;
    z-index: 9998;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    padding: 1rem;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
    max-width: 200px;
    max-height: calc(100vh - 8rem);
    overflow-y: auto;
}

.progress-indicator-header {
    font-size: 0.75rem;
    font-weight: 600;
    color: #666;
    margin-bottom: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.progress-step {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem;
    transition: all 0.3s ease;
    border-radius: 6px;
    text-decoration: none;
    color: inherit;
}

.progress-step.clickable {
    cursor: pointer;
    text-decoration: none;
    color: inherit;
}

.progress-step.clickable:hover {
    background: rgba(102, 126, 234, 0.15);
    transform: translateX(4px);
}

.progress-step.disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.progress-step.current {
    background: rgba(102, 126, 234, 0.1);
    border-left: 3px solid #667eea;
    padding-left: 0.5rem;
    font-weight: 700;
}

.progress-step .step-icon {
    font-size: 1.25rem;
    flex-shrink: 0;
}

.progress-step .step-label {
    font-size: 0.875rem;
}

/* Mobile trigger button */
.progress-mobile-trigger {
    display: none;
    position: fixed;
    top: 1.5rem;
    right: 1.5rem;
    z-index: 9999;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: none;
    border-radius: 50px;
    padding: 0.75rem 1rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    cursor: pointer;
    gap: 0.5rem;
    align-items: center;
    font-size: 0.875rem;
    font-weight: 600;
    color: #667eea;
}

.progress-mobile-trigger .current-step-icon {
    font-size: 1.25rem;
}

.progress-mobile-trigger .current-step-number {
    color: #666;
}

/* Bottom sheet overlay */
.progress-mobile-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 10000;
}

.progress-mobile-overlay.active {
    display: block;
}

.progress-mobile-sheet {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: #fff;
    border-radius: 20px 20px 0 0;
    padding: 1.5rem;
    max-height: 70vh;
    overflow-y: auto;
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from { transform: translateY(100%); }
    to { transform: translateY(0); }
}

.sheet-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e5e7eb;
    font-weight: 600;
    color: #333;
}

.sheet-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #666;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background 0.2s;
}

.sheet-close:hover {
    background: #f3f4f6;
}

.sheet-content {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

/* Mobile styles */
@media (max-width: 768px) {
    .progress-indicator {
        display: none !important;
    }
    
    .progress-mobile-trigger {
        display: flex !important;
    }
}
</style>

<!-- Desktop Progress Indicator -->
<div class="progress-indicator">
    <div class="progress-indicator-header">
        <?php echo $lang === 'en' ? 'Progress' : 'Průběh'; ?>
    </div>
    
    <?php foreach ($all_steps as $step => $data): 
        $is_completed = in_array($step, $completed);
        $is_current = ($step === $current);
        
        // Logika navigace
        $can_navigate = false;
        if ($step === 'language') {
            $can_navigate = true;
        } elseif (in_array($step, $completed) || in_array($step, $history)) {
            $can_navigate = true;
        }
        
        if ($current === 'language' && empty($flow['language']) && $step !== 'language') {
            $can_navigate = false;
        }
        
        $step_url = home_url('/visitor-invitation/' . $token . '/?step=' . $step);
    ?>
        <?php if ($can_navigate && $step !== $current): ?>
            <a href="<?php echo esc_url($step_url); ?>" class="progress-step clickable">
        <?php else: ?>
            <div class="progress-step <?php echo $is_current ? 'current' : 'disabled'; ?>">
        <?php endif; ?>
        
        <span class="step-icon">
            <?php echo $is_completed ? '✅' : $data['icon']; ?>
        </span>
        <span class="step-label">
            <?php echo esc_html($data[$title_key]); ?>
        </span>
        
        <?php if ($can_navigate && $step !== $current): ?>
            </a>
        <?php else: ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<!-- Mobile: Floating button -->
<button class="progress-mobile-trigger" id="progress-toggle">
    <span class="current-step-icon"><?php echo $all_steps[$current]['icon'] ?? '📋'; ?></span>
    <span class="current-step-number"><?php echo ($current_index + 1); ?>/<?php echo count($all_steps); ?></span>
</button>

<!-- Mobile: Bottom sheet overlay -->
<div class="progress-mobile-overlay" id="progress-overlay">
    <div class="progress-mobile-sheet">
        <div class="sheet-header">
            <span><?php echo $lang === 'en' ? 'Progress' : 'Průběh'; ?></span>
            <button class="sheet-close" id="progress-close">✕</button>
        </div>
        <div class="sheet-content">
            <?php foreach ($all_steps as $step => $data): 
                $is_completed = in_array($step, $completed);
                $is_current = ($step === $current);
                
                $can_navigate = false;
                if ($step === 'language') {
                    $can_navigate = true;
                } elseif (in_array($step, $completed) || in_array($step, $history)) {
                    $can_navigate = true;
                }
                
                if ($current === 'language' && empty($flow['language']) && $step !== 'language') {
                    $can_navigate = false;
                }
                
                $step_url = home_url('/visitor-invitation/' . $token . '/?step=' . $step);
            ?>
                <?php if ($can_navigate && $step !== $current): ?>
                    <a href="<?php echo esc_url($step_url); ?>" class="progress-step clickable">
                <?php else: ?>
                    <div class="progress-step <?php echo $is_current ? 'current' : 'disabled'; ?>">
                <?php endif; ?>
                
                <span class="step-icon">
                    <?php echo $is_completed ? '✅' : $data['icon']; ?>
                </span>
                <span class="step-label">
                    <?php echo esc_html($data[$title_key]); ?>
                </span>
                
                <?php if ($can_navigate && $step !== $current): ?>
                    </a>
                <?php else: ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
(function() {
    var toggleBtn = document.getElementById('progress-toggle');
    var overlay = document.getElementById('progress-overlay');
    var closeBtn = document.getElementById('progress-close');
    
    if (toggleBtn && overlay) {
        toggleBtn.addEventListener('click', function() {
            overlay.classList.add('active');
        });
    }
    
    if (closeBtn && overlay) {
        closeBtn.addEventListener('click', function() {
            overlay.classList.remove('active');
        });
    }
    
    if (overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                overlay.classList.remove('active');
            }
        });
    }
})();
</script>