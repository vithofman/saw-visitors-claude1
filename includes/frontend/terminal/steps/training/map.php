<?php
/**
 * Terminal Training Step - Map (Ultra Minimal)
 * 
 * @package SAW_Visitors
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get data from flow
$lang = isset($flow['language']) ? $flow['language'] : 'cs';
$visitor_id = isset($flow['visitor_ids'][0]) ? $flow['visitor_ids'][0] : null;

error_log("[MAP.PHP] Language: {$lang}, Visitor ID: {$visitor_id}");
error_log("[MAP.PHP] PDF path: " . (isset($pdf_path) ? $pdf_path : 'NOT SET'));

// Build PDF URL
$has_pdf = !empty($pdf_path);
$pdf_url = '';
if ($has_pdf) {
    $pdf_url = content_url() . '/uploads' . $pdf_path;
    error_log("[MAP.PHP] Full PDF URL: {$pdf_url}");
}

// Check if completed
$completed = false;
if ($visitor_id) {
    global $wpdb;
    $visitor = $wpdb->get_row($wpdb->prepare(
        "SELECT training_step_map FROM {$wpdb->prefix}saw_visitors WHERE id = %d",
        $visitor_id
    ));
    if ($visitor) {
        $completed = !empty($visitor->training_step_map);
    }
}

// Translations
$translations = array(
    'cs' => array(
        'confirm' => 'Potvrzuji seznámení s mapou',
        'continue' => 'Pokračovat',
        'loading' => 'Načítání...',
        'hint' => 'Projděte si všechny stránky',
    ),
    'en' => array(
        'confirm' => 'I confirm map review',
        'continue' => 'Continue',
        'loading' => 'Loading...',
        'hint' => 'Review all pages',
    ),
    'sk' => array(
        'confirm' => 'Potvrdzujem oboznámenie s mapou',
        'continue' => 'Pokračovať',
        'loading' => 'Načítavanie...',
        'hint' => 'Prejdite si všetky stránky',
    ),
    'uk' => array(
        'confirm' => 'Підтверджую ознайомлення з картою',
        'continue' => 'Продовжити',
        'loading' => 'Завантаження...',
        'hint' => 'Перегляньте всі сторінки',
    ),
);

$t = isset($translations[$lang]) ? $translations[$lang] : $translations['cs'];
?>

<!-- Ultra Minimal Fullscreen PDF Viewer -->
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

/* Hide footer on this page */
.saw-terminal-footer {
    display: none !important;
}

.saw-pdf-fullscreen {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: #1a202c;
    overflow: hidden;
}

/* PDF Canvas */
#pdf-canvas {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    max-width: 100%;
    max-height: 100%;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
}

/* Loading */
.saw-loading {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    color: white;
}

.saw-spinner {
    width: 3rem;
    height: 3rem;
    border: 3px solid rgba(255, 255, 255, 0.1);
    border-top-color: #667eea;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
    margin: 0 auto 1rem;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Navigation Arrows - LEFT & RIGHT EDGE */
.saw-nav-arrow {
    position: fixed;
    top: 50%;
    transform: translateY(-50%);
    width: 80px;
    height: 120px;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(10px);
    border: none;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    z-index: 100;
    opacity: 0.7;
}

.saw-nav-arrow:hover:not(:disabled) {
    opacity: 1;
    background: rgba(0, 0, 0, 0.8);
}

.saw-nav-arrow:disabled {
    opacity: 0.2;
    cursor: not-allowed;
}

.saw-nav-arrow.left {
    left: 0;
    border-radius: 0 12px 12px 0;
}

.saw-nav-arrow.right {
    right: 0;
    border-radius: 12px 0 0 12px;
}

.saw-nav-arrow svg {
    width: 32px;
    height: 32px;
}

/* Page Indicator - Top Right Corner */
.saw-page-indicator {
    position: fixed;
    top: 2rem;
    right: 2rem;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(20px);
    color: white;
    padding: 0.625rem 1.25rem;
    border-radius: 999px;
    font-weight: 600;
    font-size: 0.9rem;
    z-index: 100;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

/* Progress Bar - Above Action Panel */
.saw-progress-bar {
    position: fixed;
    bottom: 8.5rem;
    right: 2rem;
    width: 280px;
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(20px);
    border-radius: 12px;
    height: 12px;
    overflow: hidden;
    z-index: 199;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.saw-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #48bb78, #38a169);
    border-radius: 12px;
    transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 0 16px rgba(72, 187, 120, 0.8);
}

/* Hint Message - Above Progress Bar */
.saw-hint-message {
    position: fixed;
    bottom: 10.5rem;
    right: 2rem;
    width: 280px;
    background: rgba(102, 126, 234, 0.2);
    backdrop-filter: blur(20px);
    border-radius: 12px;
    padding: 0.875rem 1.25rem;
    border: 1px solid rgba(102, 126, 234, 0.4);
    z-index: 198;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    opacity: 1;
    transition: opacity 0.4s ease, transform 0.4s ease;
}

.saw-hint-message.hidden {
    opacity: 0;
    transform: translateY(10px);
    pointer-events: none;
}

.saw-hint-icon {
    font-size: 1.25rem;
    flex-shrink: 0;
}

.saw-hint-text {
    color: white;
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1.4;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

/* Floating Actions - Right Bottom */
.saw-confirm-panel {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 0.75rem;
    z-index: 200;
    min-width: 280px;
}

.saw-confirm-checkbox {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    padding: 1rem 1.5rem;
    background: rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(20px) saturate(180%);
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.2);
}

.saw-confirm-checkbox:hover {
    background: rgba(255, 255, 255, 0.18);
    border-color: rgba(102, 126, 234, 0.5);
    transform: translateY(-2px);
    box-shadow: 0 6px 30px rgba(0, 0, 0, 0.3);
}

.saw-confirm-checkbox.checked {
    background: rgba(72, 187, 120, 0.2);
    border-color: rgba(72, 187, 120, 0.5);
}

.saw-confirm-checkbox input {
    width: 22px;
    height: 22px;
    cursor: pointer;
    accent-color: #48bb78;
    flex-shrink: 0;
}

.saw-confirm-checkbox span {
    font-weight: 600;
    color: white;
    font-size: 0.925rem;
    line-height: 1.4;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.saw-continue-btn {
    padding: 1rem 1.5rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 16px;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    box-shadow: 0 4px 24px rgba(102, 126, 234, 0.4);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.saw-continue-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 32px rgba(102, 126, 234, 0.6);
}

.saw-continue-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
    transform: none;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .saw-nav-arrow {
        width: 56px;
        height: 90px;
    }
    
    .saw-nav-arrow svg {
        width: 20px;
        height: 20px;
    }
    
    .saw-page-indicator {
        top: 1rem;
        right: 1rem;
        font-size: 0.825rem;
        padding: 0.5rem 1rem;
    }
    
    .saw-hint-message {
        bottom: auto;
        top: 4.5rem;
        right: 1rem;
        left: 1rem;
        width: auto;
    }
    
    .saw-progress-bar {
        bottom: auto;
        top: 8rem;
        right: 1rem;
        left: 1rem;
        width: auto;
        height: 10px;
    }
    
    .saw-confirm-panel {
        bottom: 1rem;
        right: 1rem;
        left: 1rem;
        min-width: 0;
    }
    
    .saw-confirm-checkbox {
        padding: 0.875rem 1.25rem;
    }
    
    .saw-confirm-checkbox span {
        font-size: 0.875rem;
    }
    
    .saw-continue-btn {
        padding: 0.875rem 1.25rem;
    }
}
</style>

<div class="saw-pdf-fullscreen">
    
    <?php if ($has_pdf): ?>
    
    <!-- Loading -->
    <div id="pdf-loading" class="saw-loading">
        <div class="saw-spinner"></div>
        <p style="font-size: 1.125rem; font-weight: 600;"><?php echo esc_html($t['loading']); ?></p>
    </div>
    
    <!-- Canvas -->
    <canvas id="pdf-canvas" style="display: none;"></canvas>
    
    <!-- Navigation Arrows -->
    <button type="button" id="pdf-prev" class="saw-nav-arrow left" style="display: none;" disabled>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
    </button>
    
    <button type="button" id="pdf-next" class="saw-nav-arrow right" style="display: none;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="9 18 15 12 9 6"></polyline>
        </svg>
    </button>
    
    <!-- Page Indicator - Top Right -->
    <div id="pdf-page-indicator" class="saw-page-indicator" style="display: none;">
        1 / 1
    </div>
    
    <!-- Hint Message - Above Progress Bar -->
    <div id="pdf-hint-message" class="saw-hint-message" style="display: none;">
        <span class="saw-hint-icon">💡</span>
        <span class="saw-hint-text"><?php echo esc_html($t['hint']); ?></span>
    </div>
    
    <!-- Progress Bar - Above Actions -->
    <div id="pdf-progress-bar" class="saw-progress-bar" style="display: none;">
        <div id="pdf-progress-fill" class="saw-progress-fill" style="width: 0%;"></div>
    </div>
    
    <!-- Floating Confirmation Panel -->
    <form method="POST" id="map-form" class="saw-confirm-panel">
        <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
        <input type="hidden" name="terminal_action" value="complete_training_map">
        
        <?php if (!$completed): ?>
        <label class="saw-confirm-checkbox" id="checkbox-wrapper">
            <input type="checkbox" 
                   name="map_confirmed" 
                   id="map-confirmed"
                   value="1"
                   required
                   disabled>
            <span><?php echo esc_html($t['confirm']); ?></span>
        </label>
        <?php endif; ?>
        
        <button type="submit" 
                class="saw-continue-btn"
                id="continue-btn"
                <?php echo !$completed ? 'disabled' : ''; ?>>
            <?php echo esc_html($t['continue']); ?> →
        </button>
    </form>
    
    <?php endif; ?>
    
</div>

<?php if ($has_pdf): ?>
<script>
(function() {
    'use strict';
    
    console.log('[SAW Map] Minimal PDF viewer initializing...');
    
    function initWhenReady() {
        if (typeof SAWPDFViewer === 'undefined') {
            setTimeout(initWhenReady, 100);
            return;
        }
        
        console.log('[SAW Map] Starting viewer...');
        
        const viewer = new SAWPDFViewer({
            pdfUrl: '<?php echo esc_js($pdf_url); ?>',
            canvasId: 'pdf-canvas',
            debug: false,
            
            onComplete: function(data) {
                console.log('[SAW Map] Completed!');
                
                // Hide hint and progress bar when completed
                const hint = document.getElementById('pdf-hint-message');
                const progressBar = document.getElementById('pdf-progress-bar');
                if (hint) hint.classList.add('hidden');
                if (progressBar && data.totalPages > 1) {
                    setTimeout(function() {
                        progressBar.classList.add('hidden');
                    }, 400);
                }
                
                // Enable checkbox
                const checkbox = document.getElementById('map-confirmed');
                const wrapper = document.getElementById('checkbox-wrapper');
                if (checkbox) {
                    checkbox.disabled = false;
                    if (wrapper) wrapper.classList.add('checked');
                }
            },
            
            onPageChange: function(data) {
                // Hide loading on first page
                if (data.currentPage === 1) {
                    const loading = document.getElementById('pdf-loading');
                    if (loading) loading.style.display = 'none';
                    
                    const canvas = document.getElementById('pdf-canvas');
                    const prevBtn = document.getElementById('pdf-prev');
                    const nextBtn = document.getElementById('pdf-next');
                    const indicator = document.getElementById('pdf-page-indicator');
                    const hint = document.getElementById('pdf-hint-message');
                    const progressBar = document.getElementById('pdf-progress-bar');
                    
                    if (canvas) canvas.style.display = 'block';
                    if (prevBtn) prevBtn.style.display = 'flex';
                    if (nextBtn) nextBtn.style.display = 'flex';
                    if (indicator) indicator.style.display = 'block';
                    
                    // Show hint and progress bar only if multiple pages (> 1)
                    if (data.totalPages > 1) {
                        if (hint) hint.style.display = 'flex';
                        if (progressBar) progressBar.style.display = 'block';
                    }
                }
                
                // Update progress bar
                const progressFill = document.getElementById('pdf-progress-fill');
                if (progressFill && data.totalPages > 1) {
                    const progress = (data.viewedPages / data.totalPages) * 100;
                    progressFill.style.width = progress + '%';
                }
            }
        });
        
        // Checkbox listener
        const checkbox = document.getElementById('map-confirmed');
        const continueBtn = document.getElementById('continue-btn');
        const wrapper = document.getElementById('checkbox-wrapper');
        
        if (checkbox && continueBtn) {
            checkbox.addEventListener('change', function() {
                continueBtn.disabled = !this.checked;
                if (wrapper) {
                    if (this.checked) {
                        wrapper.classList.add('checked');
                    } else {
                        wrapper.classList.remove('checked');
                    }
                }
            });
        }
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWhenReady);
    } else {
        initWhenReady();
    }
})();
</script>
<?php endif; ?>

<?php
error_log("[MAP.PHP] Minimal template finished");
?>