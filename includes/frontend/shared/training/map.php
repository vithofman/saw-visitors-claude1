<?php
/**
 * Shared Training Step - Map
 * Works for both Terminal and Invitation flows
 * 
 * @package SAW_Visitors
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Detect flow type
$is_invitation = isset($is_invitation) ? $is_invitation : false;

// Get data from appropriate flow
if ($is_invitation) {
    // Invitation flow
    $session = SAW_Session_Manager::instance();
    $flow = $session->get('invitation_flow');
    $lang = $flow['language'] ?? 'cs';
    
    // Get visitor ID from invitation flow
    global $wpdb;
    $visit = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}saw_visits WHERE id = %d",
        $flow['visit_id'] ?? 0
    ));
    
    $visitor_id = null;
    if ($visit) {
        $visitor = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_visitors 
             WHERE visit_id = %d AND training_skipped = 0 
             ORDER BY created_at ASC LIMIT 1",
            $visit->id
        ));
        if ($visitor) {
            $visitor_id = $visitor->id;
        }
    }
    
    // Get PDF path from training content
    $pdf_path = '';
    if ($visit) {
        $language_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_training_languages 
             WHERE customer_id = %d AND language_code = %s",
            $visit->customer_id,
            $lang
        ));
        
        if ($language_id) {
            $content = $wpdb->get_row($wpdb->prepare(
                "SELECT pdf_map_path FROM {$wpdb->prefix}saw_training_content 
                 WHERE customer_id = %d AND branch_id = %d AND language_id = %d",
                $visit->customer_id,
                $visit->branch_id,
                $language_id
            ));
            
            if ($content && !empty($content->pdf_map_path)) {
                $pdf_path = $content->pdf_map_path;
            }
        }
    }
} else {
    // Terminal flow
    $flow = isset($flow) ? $flow : [];
    $lang = isset($flow['language']) ? $flow['language'] : 'cs';
    $visitor_id = isset($flow['visitor_ids'][0]) ? $flow['visitor_ids'][0] : null;
    $pdf_path = isset($pdf_path) ? $pdf_path : '';
}

error_log("[SHARED MAP.PHP] Is Invitation: " . ($is_invitation ? 'yes' : 'no') . ", Language: {$lang}, Visitor ID: {$visitor_id}");
error_log("[SHARED MAP.PHP] PDF path: " . ($pdf_path ? $pdf_path : 'NOT SET'));

// Build PDF URL
$has_pdf = !empty($pdf_path);
$pdf_url = '';
if ($has_pdf) {
    $pdf_url = content_url() . '/uploads' . $pdf_path;
    error_log("[SHARED MAP.PHP] Full PDF URL: {$pdf_url}");
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
        'prev' => 'Předchozí',
        'next' => 'Další',
        'no_pdf' => 'Mapa není k dispozici',
    ),
    'en' => array(
        'confirm' => 'I confirm map review',
        'continue' => 'Continue',
        'loading' => 'Loading...',
        'hint' => 'Review all pages',
        'prev' => 'Previous',
        'next' => 'Next',
        'no_pdf' => 'Map not available',
    ),
    'sk' => array(
        'confirm' => 'Potvrdzujem oboznámenie s mapou',
        'continue' => 'Pokračovať',
        'loading' => 'Načítavanie...',
        'hint' => 'Prejdite si všetky stránky',
        'prev' => 'Predchádzajúce',
        'next' => 'Ďalšie',
        'no_pdf' => 'Mapa nie je k dispozícii',
    ),
    'uk' => array(
        'confirm' => 'Підтверджую ознайомлення з картою',
        'continue' => 'Продовжити',
        'loading' => 'Завантаження...',
        'hint' => 'Перегляньте всі сторінки',
        'prev' => 'Попередній',
        'next' => 'Наступний',
        'no_pdf' => 'Карта недоступна',
    ),
);

$t = isset($translations[$lang]) ? $translations[$lang] : $translations['cs'];
?>

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
        <?php 
        $nonce_name = $is_invitation ? 'saw_invitation_step' : 'saw_terminal_step';
        $nonce_field = $is_invitation ? 'invitation_nonce' : 'terminal_nonce';
        $action_name = $is_invitation ? 'invitation_action' : 'terminal_action';
        $complete_action = $is_invitation ? 'complete_training' : 'complete_training_map';
        wp_nonce_field($nonce_name, $nonce_field); 
        ?>
        <input type="hidden" name="<?php echo esc_attr($action_name); ?>" value="<?php echo esc_attr($complete_action); ?>">
        
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

<?php if ($is_invitation): ?>
<!-- Skip button for invitation mode -->
<div class="saw-panel-skip">
    <p class="saw-panel-skip-info">
        💡 Toto školení je volitelné. Můžete ho přeskočit a projít si později.
    </p>
    <form method="POST" style="display: inline-block;">
        <?php wp_nonce_field($nonce_name, $nonce_field); ?>
        <input type="hidden" name="<?php echo esc_attr($action_name); ?>" value="skip_training">
        <button type="submit" class="saw-panel-skip-btn">
            ⏭️ Přeskočit školení
        </button>
    </form>
</div>
<?php endif; ?>


<?php if ($has_pdf): ?>
    <?php 
// ✅ Načti PDF viewer script
$pdf_viewer_path = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/assets/js/terminal/pdf-viewer.js';
$pdf_viewer_url = SAW_VISITORS_PLUGIN_URL . 'includes/frontend/terminal/assets/js/terminal/pdf-viewer.js';

if (file_exists($pdf_viewer_path)):
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="<?php echo esc_url($pdf_viewer_url); ?>?ver=<?php echo time(); ?>"></script>
<?php else: ?>
<!-- PDF viewer script not found at: <?php echo esc_html($pdf_viewer_path); ?> -->
<?php endif; ?>
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
                    
                    // Show hint and progress bar only if multiple pages (> 1)
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
error_log("[SHARED MAP.PHP] Template finished");
?>