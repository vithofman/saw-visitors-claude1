<?php
/**
 * Progress Indicator Component v5 - Clean UX Design
 * 
 * @package SAW_Visitors
 * @since 3.0.0
 * @version 3.9.9
 * 
 * ZMĚNA v 3.9.9:
 * - NEW: Free navigation mode - všechny kroky jsou klikatelné bez nutnosti dokončit předchozí
 * - Uživatel může libovolně přecházet mezi kroky školení
 */

if (!defined('ABSPATH')) exit;

$completed = $flow['completed_steps'] ?? [];
$history = $flow['history'] ?? [];
$current = $current_step;
$token = $token ?? ($flow['token'] ?? '');
$lang = $flow['language'] ?? 'cs';
$title_key = ($lang === 'en') ? 'title_en' : 'title_cs';

// FREE NAVIGATION MODE v3.9.9 - all steps clickable (invitation only)
// Users can freely navigate between training steps without completing them
$free_navigation = true;

// Definice kroků
$all_steps = [
    'language' => ['icon' => '🌐', 'title_cs' => 'Jazyk', 'title_en' => 'Language'],
    'risks' => ['icon' => '⚠️', 'title_cs' => 'Rizika', 'title_en' => 'Risks'],
    'visitors' => ['icon' => '👥', 'title_cs' => 'Návštěvníci', 'title_en' => 'Visitors'],
];

if (isset($available_training_steps) && !empty($available_training_steps)) {
    $training_defs = [
        'training-video' => ['icon' => '🎥', 'title_cs' => 'Video', 'title_en' => 'Video'],
        'training-map' => ['icon' => '🗺️', 'title_cs' => 'Mapa', 'title_en' => 'Map'],
        'training-risks' => ['icon' => '⚠️', 'title_cs' => 'Rizika školení', 'title_en' => 'Training Risks'],
        'training-department' => ['icon' => '🏢', 'title_cs' => 'Oddělení', 'title_en' => 'Department'],
        'training-oopp' => ['icon' => '🦺', 'title_cs' => 'OOPP', 'title_en' => 'PPE'],
        'training-additional' => ['icon' => 'ℹ️', 'title_cs' => 'Další info', 'title_en' => 'Additional'],
    ];
    foreach ($available_training_steps as $step) {
        if (isset($training_defs[$step['step']])) {
            $all_steps[$step['step']] = $training_defs[$step['step']];
        }
    }
}

$all_steps['summary'] = ['icon' => '📋', 'title_cs' => 'Přehled', 'title_en' => 'Summary'];
$all_steps['success'] = ['icon' => '✅', 'title_cs' => 'Hotovo', 'title_en' => 'Done'];

$step_keys = array_keys($all_steps);
$total_steps = count($all_steps);
$current_index = array_search($current, $step_keys);
if ($current_index === false) $current_index = 0;
$progress_percent = $total_steps > 1 ? round(($current_index / ($total_steps - 1)) * 100) : 0;
?>

<style>
/* ========================================
   PROGRESS INDICATOR v5
   Clean, minimal, UX-focused
   ======================================== */

.pi5 {
    --pi-width-collapsed: 48px;
    --pi-width-expanded: 200px;
    --pi-radius: 12px;
    --pi-gap: 4px;
    
    --pi-glass: rgba(17, 24, 39, 0.7);
    --pi-glass-border: rgba(255, 255, 255, 0.08);
    --pi-glass-hover: rgba(255, 255, 255, 0.05);
    
    --pi-text: #f3f4f6;
    --pi-text-dim: #9ca3af;
    --pi-text-faint: #6b7280;
    
    --pi-accent: #6366f1;
    --pi-accent-glow: rgba(99, 102, 241, 0.4);
    --pi-success: #22c55e;
    --pi-success-glow: rgba(34, 197, 94, 0.4);
    
    font-family: system-ui, -apple-system, sans-serif;
}

/* ========================================
   DESKTOP PANEL - LEFT SIDEBAR
   ======================================== */

.pi5-panel {
    position: fixed;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 99999;
    width: var(--pi-width-collapsed);
    background: var(--pi-glass);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--pi-glass-border);
    border-radius: var(--pi-radius);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    transition: width 0.25s ease;
    overflow: visible;
}

.pi5-panel.is-open {
    width: var(--pi-width-expanded);
}

/* ========================================
   PROGRESS TRACK (always visible)
   ======================================== */

.pi5-track {
    height: 3px;
    background: rgba(255, 255, 255, 0.1);
    margin: 10px 10px 6px 10px;
    border-radius: 99px;
    overflow: hidden;
}

.pi5-track-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--pi-accent), #a78bfa);
    border-radius: 99px;
    transition: width 0.4s ease;
}

/* ========================================
   STEPS LIST
   ======================================== */

.pi5-steps {
    padding: 4px 6px 10px 6px;
    display: flex;
    flex-direction: column;
    gap: var(--pi-gap);
    overflow: hidden;
}

/* ========================================
   SINGLE STEP
   ======================================== */

.pi5-step {
    display: flex;
    align-items: center;
    height: 36px;
    padding: 0 5px;
    border-radius: 8px;
    text-decoration: none;
    color: var(--pi-text-faint);
    cursor: default;
    transition: background 0.15s ease, color 0.15s ease;
    position: relative;
}

.pi5-step.is-clickable {
    cursor: pointer;
}

.pi5-step.is-clickable:hover {
    background: var(--pi-glass-hover);
    color: var(--pi-text-dim);
}

.pi5-step.is-current {
    color: var(--pi-text);
}

.pi5-step.is-disabled {
    opacity: 0.3;
    pointer-events: none;
}

/* ========================================
   STEP NUMBER
   ======================================== */

.pi5-num {
    width: 28px;
    height: 28px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.08);
    transition: all 0.2s ease;
    position: relative;
}

.pi5-step.is-current .pi5-num {
    background: var(--pi-accent);
    border-color: var(--pi-accent);
    color: white;
    box-shadow: 0 0 12px var(--pi-accent-glow);
}

.pi5-step.is-done .pi5-num {
    background: var(--pi-success);
    border-color: var(--pi-success);
    color: white;
    box-shadow: 0 0 10px var(--pi-success-glow);
}

/* Done checkmark */
.pi5-step.is-done .pi5-num::after {
    content: '';
    position: absolute;
    bottom: -2px;
    right: -2px;
    width: 12px;
    height: 12px;
    background: var(--pi-success);
    border: 2px solid var(--pi-glass);
    border-radius: 50%;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='20 6 9 17 4 12'%3E%3C/polyline%3E%3C/svg%3E");
    background-size: 8px;
    background-repeat: no-repeat;
    background-position: center;
}

/* ========================================
   STEP LABEL (visible when expanded)
   ======================================== */

.pi5-label {
    margin-left: 10px;
    font-size: 13px;
    font-weight: 500;
    white-space: nowrap;
    opacity: 0;
    transform: translateX(-8px);
    transition: opacity 0.2s ease, transform 0.2s ease;
    pointer-events: none;
}

.pi5-panel.is-open .pi5-label {
    opacity: 1;
    transform: translateX(0);
}

.pi5-step.is-current .pi5-label {
    font-weight: 600;
    color: var(--pi-text);
}

/* ========================================
   TOOLTIP (visible when collapsed)
   ======================================== */

.pi5-tip {
    position: fixed;
    left: 72px;
    background: rgba(17, 24, 39, 0.95);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 8px;
    padding: 8px 14px;
    font-size: 13px;
    font-weight: 500;
    color: var(--pi-text);
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: all 0.15s ease;
    pointer-events: none;
    z-index: 99999;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
    transform: translateX(-8px);
}

/* Tooltip arrow */
.pi5-tip::before {
    content: '';
    position: absolute;
    right: 100%;
    top: 50%;
    transform: translateY(-50%);
    border: 6px solid transparent;
    border-right-color: rgba(17, 24, 39, 0.95);
}

/* Show tooltip on hover when collapsed */
.pi5-panel:not(.is-open) .pi5-step:hover .pi5-tip {
    opacity: 1;
    visibility: visible;
    transform: translateX(0);
}

/* Hide tooltips when expanded */
.pi5-panel.is-open .pi5-tip {
    display: none !important;
}

/* ========================================
   TOGGLE BUTTON
   ======================================== */

.pi5-toggle {
    position: absolute;
    top: 50%;
    right: -16px;
    transform: translateY(-50%);
    width: 32px;
    height: 32px;
    background: rgba(99, 102, 241, 0.9);
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    transition: all 0.2s ease;
    box-shadow: 0 2px 12px rgba(99, 102, 241, 0.5);
    z-index: 100;
}

.pi5-toggle:hover {
    background: rgba(99, 102, 241, 1);
    transform: translateY(-50%) scale(1.1);
    box-shadow: 0 4px 20px rgba(99, 102, 241, 0.6);
}

.pi5-toggle svg {
    width: 14px;
    height: 14px;
    transition: transform 0.25s ease;
}

.pi5-panel.is-open .pi5-toggle svg {
    transform: rotate(180deg);
}

/* ========================================
   MOBILE BAR - BOTTOM FIXED
   ======================================== */

.pi5-mobile {
    display: none;
    flex-direction: column;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    top: auto;
    z-index: 99998;
    background: rgba(17, 24, 39, 0.95);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
    transform: none;
}

.pi5-mobile-inner {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    padding-bottom: calc(14px + env(safe-area-inset-bottom, 0));
}

.pi5-mobile-step {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 0;
}

.pi5-mobile-num {
    width: 36px;
    height: 36px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--pi-accent);
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    color: white;
    box-shadow: 0 0 16px var(--pi-accent-glow);
}

.pi5-mobile-info {
    flex: 1;
    min-width: 0;
}

.pi5-mobile-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--pi-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pi5-mobile-sub {
    font-size: 12px;
    color: var(--pi-text-dim);
    margin-top: 2px;
}

/* Progress bar on mobile */
.pi5-mobile-progress {
    width: 100%;
    max-width: 80px;
    height: 6px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 99px;
    overflow: hidden;
    flex-shrink: 0;
}

.pi5-mobile-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--pi-accent), #a78bfa);
    border-radius: 99px;
    transition: width 0.4s ease;
}

/* Chevron indicator */
.pi5-mobile-chevron {
    width: 20px;
    height: 20px;
    color: var(--pi-text-dim);
    flex-shrink: 0;
    transition: transform 0.3s ease;
}

.pi5-mobile.is-sheet-open .pi5-mobile-chevron {
    transform: rotate(180deg);
}

/* ========================================
   MOBILE SHEET - FULL OVERLAY
   ======================================== */

.pi5-sheet-overlay {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 99999;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.pi5-sheet-overlay.is-active {
    display: block;
    opacity: 1;
}

.pi5-sheet {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(17, 24, 39, 0.98);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 20px 20px 0 0;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-bottom: none;
    max-height: 80vh;
    transform: translateY(100%);
    transition: transform 0.35s cubic-bezier(0.32, 0.72, 0, 1);
}

.pi5-sheet-overlay.is-active .pi5-sheet {
    transform: translateY(0);
}

.pi5-sheet-handle {
    display: flex;
    justify-content: center;
    padding: 12px;
    cursor: grab;
}

.pi5-sheet-bar {
    width: 40px;
    height: 5px;
    background: rgba(255, 255, 255, 0.25);
    border-radius: 99px;
}

.pi5-sheet-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.pi5-sheet-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--pi-text);
}

.pi5-sheet-pct {
    font-size: 14px;
    font-weight: 600;
    color: var(--pi-accent);
}

.pi5-sheet-body {
    padding: 8px 8px 20px;
    padding-bottom: calc(20px + env(safe-area-inset-bottom, 0));
    max-height: calc(80vh - 100px);
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}

/* Sheet steps */
.pi5-sheet-step {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 12px;
    border-radius: 12px;
    text-decoration: none;
    color: var(--pi-text-faint);
    transition: all 0.15s ease;
    margin-bottom: 4px;
}

.pi5-sheet-step.is-clickable {
    cursor: pointer;
}

.pi5-sheet-step.is-clickable:active {
    background: rgba(255, 255, 255, 0.08);
    transform: scale(0.98);
}

.pi5-sheet-step.is-current {
    background: rgba(99, 102, 241, 0.15);
    color: var(--pi-text);
}

.pi5-sheet-step.is-disabled {
    opacity: 0.35;
    pointer-events: none;
}

.pi5-sheet-num {
    width: 40px;
    height: 40px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.1);
    position: relative;
}

.pi5-sheet-step.is-current .pi5-sheet-num {
    background: var(--pi-accent);
    border-color: var(--pi-accent);
    color: white;
    box-shadow: 0 0 20px var(--pi-accent-glow);
}

.pi5-sheet-step.is-done .pi5-sheet-num {
    background: var(--pi-success);
    border-color: var(--pi-success);
    color: white;
    box-shadow: 0 0 16px var(--pi-success-glow);
}

.pi5-sheet-step.is-done .pi5-sheet-num::after {
    content: '';
    position: absolute;
    bottom: -4px;
    right: -4px;
    width: 16px;
    height: 16px;
    background: var(--pi-success);
    border: 2px solid rgba(17, 24, 39, 0.98);
    border-radius: 50%;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='20 6 9 17 4 12'%3E%3C/polyline%3E%3C/svg%3E");
    background-size: 10px;
    background-repeat: no-repeat;
    background-position: center;
}

.pi5-sheet-content {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.pi5-sheet-label {
    font-size: 16px;
    font-weight: 500;
}

.pi5-sheet-step.is-current .pi5-sheet-label {
    font-weight: 600;
}

.pi5-sheet-icon {
    font-size: 13px;
    color: var(--pi-text-dim);
}

/* ========================================
   RESPONSIVE
   ======================================== */

/* Desktop - show panel, hide mobile */
@media (min-width: 769px) {
    .pi5-panel {
        display: block !important;
    }
    
    .pi5-mobile {
        display: none !important;
    }
}

/* Mobile - hide panel, show mobile bar */
@media (max-width: 768px) {
    .pi5-panel,
    .pi5-toggle {
        display: none !important;
    }
    
    .pi5-mobile {
        display: flex !important;
        position: fixed !important;
        bottom: 0 !important;
        left: 0 !important;
        right: 0 !important;
        top: auto !important;
        transform: none !important;
    }
}
</style>

<div class="pi5">
    <!-- Desktop Panel -->
    <div class="pi5-panel" id="pi5Panel">
        <!-- Toggle -->
        <button type="button" class="pi5-toggle" id="pi5Toggle" aria-label="Toggle">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
        </button>
        
        <!-- Progress Track -->
        <div class="pi5-track">
            <div class="pi5-track-fill" style="width: <?php echo $progress_percent; ?>%;"></div>
        </div>
        
        <!-- Steps -->
        <div class="pi5-steps">
            <?php 
            $n = 0;
            foreach ($all_steps as $key => $step): 
                $n++;
                $done = in_array($key, $completed);
                $now = ($key === $current);
                
                // FREE NAVIGATION v3.9.9 - all steps clickable except 'success'
                if ($free_navigation) {
                    $can = ($key !== 'success');
                } else {
                    // Original logic - step must be completed or in history
                    $can = ($key === 'language') || in_array($key, $completed) || in_array($key, $history);
                    if ($current === 'language' && empty($flow['language']) && $key !== 'language') $can = false;
                }
                
                $cls = 'pi5-step';
                if ($now) $cls .= ' is-current';
                if ($done) $cls .= ' is-done';
                if ($can && !$now) $cls .= ' is-clickable';
                if (!$can && !$now) $cls .= ' is-disabled';
                
                $url = home_url('/visitor-invitation/' . $token . '/?step=' . $key);
            ?>
                <?php if ($can && !$now): ?>
                <a href="<?php echo esc_url($url); ?>" class="<?php echo $cls; ?>">
                <?php else: ?>
                <div class="<?php echo $cls; ?>">
                <?php endif; ?>
                    <div class="pi5-num"><?php echo $n; ?></div>
                    <span class="pi5-label"><?php echo esc_html($step[$title_key]); ?></span>
                    <span class="pi5-tip"><?php echo esc_html($step[$title_key]); ?></span>
                <?php if ($can && !$now): ?>
                </a>
                <?php else: ?>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Mobile Bar - tap anywhere to open sheet -->
    <div class="pi5-mobile" id="pi5Mobile">
        <div class="pi5-mobile-inner">
            <div class="pi5-mobile-step">
                <div class="pi5-mobile-num"><?php echo $current_index + 1; ?></div>
                <div class="pi5-mobile-info">
                    <div class="pi5-mobile-title"><?php echo esc_html($all_steps[$current][$title_key] ?? ''); ?></div>
                    <div class="pi5-mobile-sub"><?php echo $lang === 'en' ? 'Step' : 'Krok'; ?> <?php echo $current_index + 1; ?> <?php echo $lang === 'en' ? 'of' : 'z'; ?> <?php echo $total_steps; ?></div>
                </div>
            </div>
            <div class="pi5-mobile-progress">
                <div class="pi5-mobile-progress-fill" style="width: <?php echo $progress_percent; ?>%;"></div>
            </div>
            <svg class="pi5-mobile-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="18 15 12 9 6 15"></polyline>
            </svg>
        </div>
    </div>
    
    <!-- Mobile Sheet -->
    <div class="pi5-sheet-overlay" id="pi5SheetOverlay">
        <div class="pi5-sheet" id="pi5Sheet">
            <div class="pi5-sheet-handle"><div class="pi5-sheet-bar"></div></div>
            <div class="pi5-sheet-head">
                <span class="pi5-sheet-title"><?php echo $lang === 'en' ? 'Progress' : 'Průběh'; ?></span>
                <span class="pi5-sheet-pct"><?php echo $progress_percent; ?>%</span>
            </div>
            <div class="pi5-sheet-body">
                <?php 
                $n = 0;
                foreach ($all_steps as $key => $step): 
                    $n++;
                    $done = in_array($key, $completed);
                    $now = ($key === $current);
                    
                    // FREE NAVIGATION v3.9.9 - all steps clickable except 'success'
                    if ($free_navigation) {
                        $can = ($key !== 'success');
                    } else {
                        // Original logic
                        $can = ($key === 'language') || in_array($key, $completed) || in_array($key, $history);
                        if ($current === 'language' && empty($flow['language']) && $key !== 'language') $can = false;
                    }
                    
                    $cls = 'pi5-sheet-step';
                    if ($now) $cls .= ' is-current';
                    if ($done) $cls .= ' is-done';
                    if ($can && !$now) $cls .= ' is-clickable';
                    if (!$can && !$now) $cls .= ' is-disabled';
                    
                    $url = home_url('/visitor-invitation/' . $token . '/?step=' . $key);
                ?>
                    <?php if ($can && !$now): ?>
                    <a href="<?php echo esc_url($url); ?>" class="<?php echo $cls; ?>">
                    <?php else: ?>
                    <div class="<?php echo $cls; ?>">
                    <?php endif; ?>
                        <div class="pi5-sheet-num"><?php echo $n; ?></div>
                        <div class="pi5-sheet-content">
                            <span class="pi5-sheet-label"><?php echo esc_html($step[$title_key]); ?></span>
                            <?php if ($done): ?>
                            <span class="pi5-sheet-icon"><?php echo $lang === 'en' ? '✓ Completed' : '✓ Dokončeno'; ?></span>
                            <?php elseif ($now): ?>
                            <span class="pi5-sheet-icon"><?php echo $lang === 'en' ? '● Current step' : '● Aktuální krok'; ?></span>
                            <?php endif; ?>
                        </div>
                    <?php if ($can && !$now): ?>
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
    var panel = document.getElementById('pi5Panel');
    var toggle = document.getElementById('pi5Toggle');
    var mobile = document.getElementById('pi5Mobile');
    var overlay = document.getElementById('pi5SheetOverlay');
    var sheet = document.getElementById('pi5Sheet');
    
    // Desktop toggle
    if (localStorage.getItem('pi5-open') === 'true') panel.classList.add('is-open');
    
    toggle.onclick = function() {
        panel.classList.toggle('is-open');
        localStorage.setItem('pi5-open', panel.classList.contains('is-open'));
    };
    
    // Tooltip positioning
    var steps = panel.querySelectorAll('.pi5-step');
    steps.forEach(function(step) {
        var tip = step.querySelector('.pi5-tip');
        if (!tip) return;
        
        step.addEventListener('mouseenter', function() {
            if (panel.classList.contains('is-open')) return;
            var rect = step.getBoundingClientRect();
            tip.style.top = (rect.top + rect.height / 2) + 'px';
            tip.style.transform = 'translateY(-50%)';
        });
    });
    
    // Mobile - tap bar to open sheet
    function openSheet() {
        overlay.classList.add('is-active');
        mobile.classList.add('is-sheet-open');
        document.body.style.overflow = 'hidden';
    }
    
    function closeSheet() {
        overlay.classList.remove('is-active');
        mobile.classList.remove('is-sheet-open');
        document.body.style.overflow = '';
    }
    
    mobile.onclick = function(e) {
        if (!overlay.classList.contains('is-active')) {
            openSheet();
        }
    };
    
    // Close on overlay tap
    overlay.onclick = function(e) {
        if (e.target === overlay) {
            closeSheet();
        }
    };
    
    // Close on handle tap
    var handle = sheet.querySelector('.pi5-sheet-handle');
    if (handle) {
        handle.onclick = function() {
            closeSheet();
        };
    }
})();
</script>
