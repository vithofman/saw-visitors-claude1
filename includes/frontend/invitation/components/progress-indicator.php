<?php
/**
 * Progress Indicator Component - Modern Dark Theme v2
 * 
 * IZOLOVANÉ CSS - všechny třídy mají prefix saw-pi-
 * 
 * @package SAW_Visitors
 * @since 3.0.0
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

// ✅ Přidej POUZE DOSTUPNÉ training kroky
if (isset($available_training_steps) && !empty($available_training_steps)) {
    $training_step_definitions = [
        'training-video' => ['icon' => '🎥', 'title_cs' => 'Video', 'title_en' => 'Video'],
        'training-map' => ['icon' => '🗺️', 'title_cs' => 'Mapa', 'title_en' => 'Map'],
        'training-risks' => ['icon' => '⚠️', 'title_cs' => 'Rizika školení', 'title_en' => 'Training Risks'],
        'training-department' => ['icon' => '🏢', 'title_cs' => 'Oddělení', 'title_en' => 'Department'],
        'training-additional' => ['icon' => 'ℹ️', 'title_cs' => 'Další info', 'title_en' => 'Additional'],
    ];
    
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

// Najdi index aktuálního kroku
$current_index = 0;
$step_keys = array_keys($all_steps);
$total_steps = count($all_steps);

foreach ($step_keys as $index => $step) {
    if ($step === $current) {
        $current_index = $index;
        break;
    }
}

// Vypočítej progress procenta
$progress_percent = round((($current_index) / ($total_steps - 1)) * 100);
?>

<style>
/* ============================================
   PROGRESS INDICATOR v2 - ISOLATED CSS
   Prefix: saw-pi- (progress indicator)
   ============================================ */

/* Reset pro celý wrapper */
#saw-pi-wrapper,
#saw-pi-wrapper * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

#saw-pi-wrapper {
    --pi-bg: rgba(15, 23, 42, 0.95);
    --pi-border: rgba(148, 163, 184, 0.15);
    --pi-text: rgba(255, 255, 255, 0.9);
    --pi-text-muted: rgba(148, 163, 184, 0.8);
    --pi-accent: #667eea;
    --pi-accent-glow: rgba(102, 126, 234, 0.4);
    --pi-success: #10b981;
    --pi-success-glow: rgba(16, 185, 129, 0.4);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

/* ============================================
   DESKTOP SIDEBAR
   ============================================ */
#saw-pi-sidebar {
    position: fixed;
    top: 50%;
    left: 1.5rem;
    transform: translateY(-50%);
    z-index: 9998;
    width: 220px;
    background: var(--pi-bg);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    border: 1px solid var(--pi-border);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
    overflow: hidden;
}

/* Header */
#saw-pi-sidebar .saw-pi-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--pi-border);
    background: rgba(255, 255, 255, 0.02);
}

#saw-pi-sidebar .saw-pi-title {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--pi-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin-bottom: 0.75rem;
}

/* Progress Bar */
#saw-pi-sidebar .saw-pi-bar-wrap {
    position: relative;
    height: 6px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 999px;
    overflow: hidden;
}

#saw-pi-sidebar .saw-pi-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--pi-accent), #a78bfa);
    border-radius: 999px;
    transition: width 0.5s ease;
    box-shadow: 0 0 12px var(--pi-accent-glow);
}

#saw-pi-sidebar .saw-pi-percent {
    position: absolute;
    right: 0;
    top: -1.25rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--pi-accent);
}

/* Steps List */
#saw-pi-sidebar .saw-pi-steps {
    padding: 0.75rem 0;
    max-height: 60vh;
    overflow-y: auto;
}

/* Individual Step */
#saw-pi-sidebar .saw-pi-step {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding: 0.75rem 1.5rem;
    text-decoration: none;
    color: var(--pi-text-muted);
    transition: all 0.25s ease;
    position: relative;
    cursor: default;
}

#saw-pi-sidebar .saw-pi-step.clickable {
    cursor: pointer;
}

#saw-pi-sidebar .saw-pi-step.clickable:hover {
    background: rgba(102, 126, 234, 0.1);
    color: var(--pi-text);
}

/* Current Step */
#saw-pi-sidebar .saw-pi-step.current {
    background: linear-gradient(90deg, rgba(102, 126, 234, 0.15), transparent);
    color: var(--pi-text);
}

#saw-pi-sidebar .saw-pi-step.current::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: linear-gradient(180deg, var(--pi-accent), #a78bfa);
    border-radius: 0 999px 999px 0;
}

/* Disabled Step */
#saw-pi-sidebar .saw-pi-step.disabled {
    opacity: 0.4;
}

/* Step Icon Box - SUPER SPECIFIC */
#saw-pi-sidebar .saw-pi-icon-box {
    width: 36px !important;
    height: 36px !important;
    min-width: 36px !important;
    max-width: 36px !important;
    min-height: 36px !important;
    max-height: 36px !important;
    flex: 0 0 36px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--pi-border) !important;
    border-radius: 10px !important;
    transition: all 0.25s ease !important;
    overflow: hidden !important;
    position: relative !important;
}

#saw-pi-sidebar .saw-pi-step.current .saw-pi-icon-box {
    background: linear-gradient(135deg, var(--pi-accent), #a78bfa) !important;
    border-color: transparent !important;
    box-shadow: 0 4px 16px var(--pi-accent-glow) !important;
}

#saw-pi-sidebar .saw-pi-step.completed .saw-pi-icon-box {
    background: linear-gradient(135deg, var(--pi-success), #059669) !important;
    border-color: transparent !important;
    box-shadow: 0 4px 12px var(--pi-success-glow) !important;
}

/* Icon inside box */
#saw-pi-sidebar .saw-pi-icon-box .saw-pi-emoji {
    font-size: 16px !important;
    line-height: 1 !important;
    display: block !important;
}

#saw-pi-sidebar .saw-pi-icon-box .saw-pi-check {
    width: 16px !important;
    height: 16px !important;
    color: white !important;
    stroke-width: 3 !important;
}

/* Step Label */
#saw-pi-sidebar .saw-pi-label {
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1.3;
    color: inherit;
}

#saw-pi-sidebar .saw-pi-step.current .saw-pi-label {
    font-weight: 600;
}

/* ============================================
   MOBILE BOTTOM BAR
   ============================================ */
#saw-pi-mobile-bar {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 9998;
    background: var(--pi-bg);
    backdrop-filter: blur(20px);
    border-top: 1px solid var(--pi-border);
    padding: 0.75rem 1rem;
    padding-bottom: calc(0.75rem + env(safe-area-inset-bottom, 0));
}

#saw-pi-mobile-bar .saw-pi-mobile-content {
    display: flex;
    align-items: center;
    gap: 1rem;
}

#saw-pi-mobile-bar .saw-pi-mobile-info {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 0.875rem;
}

#saw-pi-mobile-bar .saw-pi-mobile-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--pi-accent), #a78bfa);
    border-radius: 12px;
    font-size: 1.125rem;
    box-shadow: 0 4px 16px var(--pi-accent-glow);
}

#saw-pi-mobile-bar .saw-pi-mobile-text {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

#saw-pi-mobile-bar .saw-pi-mobile-step-name {
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--pi-text);
}

#saw-pi-mobile-bar .saw-pi-mobile-counter {
    font-size: 0.75rem;
    color: var(--pi-text-muted);
}

/* Mobile Progress Ring */
#saw-pi-mobile-bar .saw-pi-ring {
    position: relative;
    width: 48px;
    height: 48px;
}

#saw-pi-mobile-bar .saw-pi-ring svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

#saw-pi-mobile-bar .saw-pi-ring .ring-bg {
    fill: none;
    stroke: rgba(255, 255, 255, 0.1);
    stroke-width: 3;
}

#saw-pi-mobile-bar .saw-pi-ring .ring-progress {
    fill: none;
    stroke: var(--pi-accent);
    stroke-width: 3;
    stroke-linecap: round;
    transition: stroke-dashoffset 0.5s ease;
}

#saw-pi-mobile-bar .saw-pi-ring-text {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--pi-text);
}

/* Mobile Menu Button */
#saw-pi-mobile-bar .saw-pi-menu-btn {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid var(--pi-border);
    border-radius: 10px;
    color: var(--pi-text);
    cursor: pointer;
    transition: all 0.2s ease;
}

#saw-pi-mobile-bar .saw-pi-menu-btn:hover {
    background: rgba(255, 255, 255, 0.15);
}

#saw-pi-mobile-bar .saw-pi-menu-btn svg {
    width: 20px;
    height: 20px;
}

/* ============================================
   MOBILE SHEET
   ============================================ */
#saw-pi-sheet-overlay {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 10000;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    opacity: 0;
    transition: opacity 0.3s ease;
}

#saw-pi-sheet-overlay.active {
    display: block;
    opacity: 1;
}

#saw-pi-sheet {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--pi-bg);
    border-radius: 24px 24px 0 0;
    border: 1px solid var(--pi-border);
    border-bottom: none;
    max-height: 80vh;
    overflow: hidden;
    transform: translateY(100%);
    transition: transform 0.4s cubic-bezier(0.32, 0.72, 0, 1);
}

#saw-pi-sheet-overlay.active #saw-pi-sheet {
    transform: translateY(0);
}

#saw-pi-sheet .saw-pi-sheet-handle {
    display: flex;
    justify-content: center;
    padding: 0.75rem;
}

#saw-pi-sheet .saw-pi-sheet-bar {
    width: 36px;
    height: 4px;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 999px;
}

#saw-pi-sheet .saw-pi-sheet-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 1.5rem 1rem;
    border-bottom: 1px solid var(--pi-border);
}

#saw-pi-sheet .saw-pi-sheet-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--pi-text);
}

#saw-pi-sheet .saw-pi-sheet-close {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    border-radius: 8px;
    color: var(--pi-text-muted);
    cursor: pointer;
    transition: all 0.2s ease;
}

#saw-pi-sheet .saw-pi-sheet-close:hover {
    background: rgba(255, 255, 255, 0.15);
    color: var(--pi-text);
}

#saw-pi-sheet .saw-pi-sheet-content {
    padding: 1rem 0;
    max-height: calc(80vh - 120px);
    overflow-y: auto;
    padding-bottom: calc(1rem + env(safe-area-inset-bottom, 0));
}

/* Sheet Steps - copy from sidebar */
#saw-pi-sheet .saw-pi-step {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding: 1rem 1.5rem;
    text-decoration: none;
    color: var(--pi-text-muted);
    transition: all 0.25s ease;
    position: relative;
    cursor: default;
}

#saw-pi-sheet .saw-pi-step.clickable {
    cursor: pointer;
}

#saw-pi-sheet .saw-pi-step.clickable:hover {
    background: rgba(102, 126, 234, 0.1);
    color: var(--pi-text);
}

#saw-pi-sheet .saw-pi-step.current {
    background: linear-gradient(90deg, rgba(102, 126, 234, 0.15), transparent);
    color: var(--pi-text);
}

#saw-pi-sheet .saw-pi-step.current::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: linear-gradient(180deg, var(--pi-accent), #a78bfa);
}

#saw-pi-sheet .saw-pi-step.disabled {
    opacity: 0.4;
}

#saw-pi-sheet .saw-pi-icon-box {
    width: 36px !important;
    height: 36px !important;
    min-width: 36px !important;
    max-width: 36px !important;
    flex: 0 0 36px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--pi-border) !important;
    border-radius: 10px !important;
}

#saw-pi-sheet .saw-pi-step.current .saw-pi-icon-box {
    background: linear-gradient(135deg, var(--pi-accent), #a78bfa) !important;
    border-color: transparent !important;
}

#saw-pi-sheet .saw-pi-step.completed .saw-pi-icon-box {
    background: linear-gradient(135deg, var(--pi-success), #059669) !important;
    border-color: transparent !important;
}

#saw-pi-sheet .saw-pi-icon-box .saw-pi-emoji {
    font-size: 16px !important;
    line-height: 1 !important;
}

#saw-pi-sheet .saw-pi-icon-box .saw-pi-check {
    width: 16px !important;
    height: 16px !important;
    color: white !important;
}

#saw-pi-sheet .saw-pi-label {
    font-size: 0.9375rem;
    font-weight: 500;
    color: inherit;
}

#saw-pi-sheet .saw-pi-step.current .saw-pi-label {
    font-weight: 600;
}

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width: 1024px) {
    #saw-pi-sidebar {
        left: 1rem;
        width: 200px;
    }
}

@media (max-width: 768px) {
    #saw-pi-sidebar {
        display: none !important;
    }
    
    #saw-pi-mobile-bar {
        display: block !important;
    }
    
    #saw-pi-home {
        top: 1rem;
        left: 1rem;
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
}
</style>

<div id="saw-pi-wrapper">    
    
    <!-- Desktop Sidebar -->
    <div id="saw-pi-sidebar">
        <div class="saw-pi-header">
            <div class="saw-pi-title">
                <?php echo $lang === 'en' ? 'Progress' : 'Průběh'; ?>
            </div>
            <div class="saw-pi-bar-wrap">
                <span class="saw-pi-percent"><?php echo $progress_percent; ?>%</span>
                <div class="saw-pi-bar-fill" style="width: <?php echo $progress_percent; ?>%;"></div>
            </div>
        </div>
        
        <div class="saw-pi-steps">
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
                
                $classes = ['saw-pi-step'];
                if ($is_current) $classes[] = 'current';
                if ($is_completed) $classes[] = 'completed';
                if ($can_navigate && !$is_current) $classes[] = 'clickable';
                if (!$can_navigate && !$is_current) $classes[] = 'disabled';
            ?>
                <?php if ($can_navigate && !$is_current): ?>
                    <a href="<?php echo esc_url($step_url); ?>" class="<?php echo implode(' ', $classes); ?>">
                <?php else: ?>
                    <div class="<?php echo implode(' ', $classes); ?>">
                <?php endif; ?>
                
                    <div class="saw-pi-icon-box">
                        <?php if ($is_completed): ?>
                            <svg class="saw-pi-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                        <?php else: ?>
                            <span class="saw-pi-emoji"><?php echo $data['icon']; ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="saw-pi-label"><?php echo esc_html($data[$title_key]); ?></span>
                
                <?php if ($can_navigate && !$is_current): ?>
                    </a>
                <?php else: ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Mobile Bottom Bar -->
    <div id="saw-pi-mobile-bar">
        <div class="saw-pi-mobile-content">
            <div class="saw-pi-mobile-info">
                <div class="saw-pi-mobile-icon">
                    <?php echo $all_steps[$current]['icon'] ?? '📋'; ?>
                </div>
                <div class="saw-pi-mobile-text">
                    <span class="saw-pi-mobile-step-name">
                        <?php echo esc_html($all_steps[$current][$title_key] ?? ''); ?>
                    </span>
                    <span class="saw-pi-mobile-counter">
                        <?php echo $lang === 'en' ? 'Step' : 'Krok'; ?> <?php echo $current_index + 1; ?>/<?php echo $total_steps; ?>
                    </span>
                </div>
            </div>
            
            <div class="saw-pi-ring">
                <?php 
                $circumference = 2 * 3.14159 * 18;
                $offset = $circumference - ($progress_percent / 100) * $circumference;
                ?>
                <svg viewBox="0 0 48 48">
                    <circle class="ring-bg" cx="24" cy="24" r="18"/>
                    <circle class="ring-progress" cx="24" cy="24" r="18" 
                            stroke-dasharray="<?php echo $circumference; ?>" 
                            stroke-dashoffset="<?php echo $offset; ?>"/>
                </svg>
                <span class="saw-pi-ring-text"><?php echo $progress_percent; ?>%</span>
            </div>
            
            <button type="button" class="saw-pi-menu-btn" id="saw-pi-menu-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>
        </div>
    </div>
    
    <!-- Mobile Sheet Overlay -->
    <div id="saw-pi-sheet-overlay">
        <div id="saw-pi-sheet">
            <div class="saw-pi-sheet-handle">
                <div class="saw-pi-sheet-bar"></div>
            </div>
            
            <div class="saw-pi-sheet-header">
                <span class="saw-pi-sheet-title">
                    <?php echo $lang === 'en' ? 'Progress' : 'Průběh'; ?> — <?php echo $progress_percent; ?>%
                </span>
                <button type="button" class="saw-pi-sheet-close" id="saw-pi-sheet-close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            
            <div class="saw-pi-sheet-content">
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
                    
                    $classes = ['saw-pi-step'];
                    if ($is_current) $classes[] = 'current';
                    if ($is_completed) $classes[] = 'completed';
                    if ($can_navigate && !$is_current) $classes[] = 'clickable';
                    if (!$can_navigate && !$is_current) $classes[] = 'disabled';
                ?>
                    <?php if ($can_navigate && !$is_current): ?>
                        <a href="<?php echo esc_url($step_url); ?>" class="<?php echo implode(' ', $classes); ?>">
                    <?php else: ?>
                        <div class="<?php echo implode(' ', $classes); ?>">
                    <?php endif; ?>
                    
                        <div class="saw-pi-icon-box">
                            <?php if ($is_completed): ?>
                                <svg class="saw-pi-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                            <?php else: ?>
                                <span class="saw-pi-emoji"><?php echo $data['icon']; ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="saw-pi-label"><?php echo esc_html($data[$title_key]); ?></span>
                    
                    <?php if ($can_navigate && !$is_current): ?>
                        </a>
                    <?php else: ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';
    
    var menuBtn = document.getElementById('saw-pi-menu-btn');
    var sheetOverlay = document.getElementById('saw-pi-sheet-overlay');
    var closeBtn = document.getElementById('saw-pi-sheet-close');
    
    function openSheet() {
        if (sheetOverlay) {
            sheetOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }
    
    function closeSheet() {
        if (sheetOverlay) {
            sheetOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
    
    if (menuBtn) {
        menuBtn.addEventListener('click', openSheet);
    }
    
    if (closeBtn) {
        closeBtn.addEventListener('click', closeSheet);
    }
    
    if (sheetOverlay) {
        sheetOverlay.addEventListener('click', function(e) {
            if (e.target === sheetOverlay) {
                closeSheet();
            }
        });
    }
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sheetOverlay && sheetOverlay.classList.contains('active')) {
            closeSheet();
        }
    });
})();
</script>