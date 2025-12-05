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
<!-- Styles moved to pages.css -->

<div class="saw-pdf-fullscreen">
    
    <?php if (!$has_pdf): ?>
    <div class="saw-pdf-loading">
        <p style="font-size: 1.5rem; margin-bottom: 2rem;">⚠️</p>
        <p style="font-size: 1.125rem; font-weight: 600;"><?php echo esc_html($t['no_pdf']); ?></p>
    </div>
    <?php else: ?>
    
    <!-- Loading -->
    <div id="pdf-loading" class="saw-pdf-loading">
        <div class="saw-pdf-spinner"></div>
        <p><?php echo esc_html($t['loading']); ?></p>
    </div>
    
    <!-- PDF Canvas - fullscreen -->
    <canvas id="pdf-canvas"></canvas>
    
    <!-- Page Indicator - Top Right -->
    <div id="pdf-page-indicator" class="saw-video-progress-indicator" style="display: none;">
        1 / 1
    </div>
    
    <!-- Hint Message -->
    <div id="pdf-hint-message" class="saw-video-hint-wrapper" style="display: none;">
        <span class="saw-video-hint-icon">💡</span>
        <span class="saw-video-hint-text"><?php echo esc_html($t['hint']); ?></span>
    </div>
    
    <!-- Navigation Arrows - Above progress bar -->
    <div id="pdf-navigation" class="saw-pdf-navigation-bar" style="display: none;">
        <button type="button" id="pdf-prev" class="saw-pdf-nav-btn" disabled>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </button>
        <button type="button" id="pdf-next" class="saw-pdf-nav-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
        </button>
    </div>
    
    <!-- Progress Bar -->
    <div id="pdf-progress-bar" class="saw-video-progress-bar" style="display: none;">
        <div id="pdf-progress-fill" class="saw-video-progress-fill" style="width: 0%;"></div>
    </div>
    
    <!-- Floating Actions -->
    <form method="POST" id="map-form" class="saw-panel-confirm">
        <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
        <input type="hidden" name="terminal_action" value="complete_training_map">
        
        <?php if (!$completed): ?>
        <label class="saw-panel-checkbox" id="checkbox-wrapper">
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
                class="saw-panel-btn"
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
                if (hint) hint.style.display = 'none';
                if (progressBar && data.totalPages > 1) {
                    setTimeout(function() {
                        progressBar.style.display = 'none';
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
                    const indicator = document.getElementById('pdf-page-indicator');
                    
                    if (canvas) canvas.style.display = 'block';
                    if (indicator) indicator.style.display = 'block';
                    
                    // Show hint and progress only for multi-page PDFs
                    if (data.totalPages > 1) {
                        const hint = document.getElementById('pdf-hint-message');
                        const progressBar = document.getElementById('pdf-progress-bar');
                        if (hint) hint.style.display = 'flex';
                        if (progressBar) progressBar.style.display = 'block';
                    }
                }

                // Show navigation always (if it exists)
                const navigation = document.getElementById('pdf-navigation');
                if (navigation) navigation.style.display = 'flex';
                
                // Update navigation button states
                const prevBtn = document.getElementById('pdf-prev');
                const nextBtn = document.getElementById('pdf-next');
                if (prevBtn) prevBtn.disabled = (data.currentPage === 1);
                if (nextBtn) nextBtn.disabled = (data.currentPage === data.totalPages);
                
                // Update progress bar
                const progressFill = document.getElementById('pdf-progress-fill');
                if (progressFill && data.totalPages > 1) {
                    const progress = (data.viewedPages / data.totalPages) * 100;
                    progressFill.style.width = progress + '%';
                }
                
                // Update page indicator
                const indicator = document.getElementById('pdf-page-indicator');
                if (indicator) {
                    indicator.textContent = data.currentPage + ' / ' + data.totalPages;
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