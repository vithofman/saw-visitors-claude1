<?php
/**
 * Terminal Training Step - Video (Fixed)
 * 
 * @package SAW_Visitors
 * @version 3.0.1
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get data from flow
$lang = isset($flow['language']) ? $flow['language'] : 'cs';
$visitor_id = isset($flow['visitor_ids'][0]) ? $flow['visitor_ids'][0] : null;

error_log("[VIDEO.PHP] Language: {$lang}, Visitor ID: {$visitor_id}");
error_log("[VIDEO.PHP] Video URL: " . (isset($video_url) ? $video_url : 'NOT SET'));

// Check if video exists
$has_video = !empty($video_url);

// Check if completed
$completed = false;
if ($visitor_id) {
    global $wpdb;
    $visitor = $wpdb->get_row($wpdb->prepare(
        "SELECT training_step_video FROM {$wpdb->prefix}saw_visitors WHERE id = %d",
        $visitor_id
    ));
    if ($visitor) {
        $completed = !empty($visitor->training_step_video);
    }
}

// Translations
$translations = array(
    'cs' => array(
        'confirm' => 'Potvrzuji zhlédnutí videa',
        'continue' => 'Pokračovat',
        'loading' => 'Načítání...',
        'hint' => 'Shlédněte celé video (min. 90%)',
        'no_video' => 'Video není k dispozici',
    ),
    'en' => array(
        'confirm' => 'I confirm video viewing',
        'continue' => 'Continue',
        'loading' => 'Loading...',
        'hint' => 'Watch the entire video (min. 90%)',
        'no_video' => 'Video not available',
    ),
    'sk' => array(
        'confirm' => 'Potvrdzujem zhliadnutie videa',
        'continue' => 'Pokračovať',
        'loading' => 'Načítavanie...',
        'hint' => 'Pozrite si celé video (min. 90%)',
        'no_video' => 'Video nie je k dispozícii',
    ),
    'uk' => array(
        'confirm' => 'Підтверджую перегляд відео',
        'continue' => 'Продовжити',
        'loading' => 'Завантаження...',
        'hint' => 'Перегляньте все відео (мін. 90%)',
        'no_video' => 'Відео недоступне',
    ),
);

$t = isset($translations[$lang]) ? $translations[$lang] : $translations['cs'];
?>

<!-- Styles moved to pages.css -->

<div class="saw-video-fullscreen">
    
    <?php if (!$has_video): ?>
    
    <div class="saw-video-loading">
        <p style="font-size: 1.5rem; margin-bottom: 2rem;">⚠️</p>
        <p style="font-size: 1.125rem; font-weight: 600;"><?php echo esc_html($t['no_video']); ?></p>
    </div>
    
    <?php else: ?>
    
    <div class="saw-video-container">
        <div id="video-player"></div>
    </div>
    
    <!-- Progress Indicator - Top Right -->
    <div id="progress-indicator" class="saw-video-progress-indicator" style="display: none;">
        0%
    </div>
    
    <!-- Hint Message - ABOVE everything -->
    <div id="video-hint-message" class="saw-video-hint-wrapper" style="display: none;">
        <span class="saw-video-hint-icon">▶️</span>
        <span class="saw-video-hint-text"><?php echo esc_html($t['hint']); ?></span>
    </div>
    
    <!-- Progress Bar - samostatný element NAD formulářem -->
    <div id="video-progress-bar" class="saw-video-progress-bar" style="display: none;">
        <div id="video-progress-fill" class="saw-video-progress-fill" style="width: 0%;"></div>
    </div>
    
    <!-- Floating Actions -->
    <form method="POST" id="video-form" class="saw-panel-confirm">
    
    <?php if (!$completed): ?>
    <label class="saw-panel-checkbox" id="checkbox-wrapper">
        <input type="checkbox" 
               name="video_confirmed" 
               id="video-confirmed"
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
    
    <?php 
    // Skip button for invitation mode
    $is_invitation = ($flow['mode'] ?? '') === 'invitation';
    if ($is_invitation): 
    ?>
        <div class="saw-training-skip-wrapper">
            <p class="saw-skip-info">
                💡 Toto školení je volitelné. Můžete ho přeskočit a projít si později.
            </p>
            <form method="POST" style="display: inline-block;">
                <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
                <input type="hidden" name="terminal_action" value="skip_training">
                <button type="submit" class="saw-btn-skip">
                    ⏭️ Přeskočit školení
                </button>
            </form>
        </div>
    <?php endif; ?>

</div>

<?php if ($is_invitation): ?>

<?php endif; ?>

<?php if ($has_video): ?>
<script>
(function() {
    'use strict';
    
    console.log('[SAW Video] Initializing...');
    
    function initWhenReady() {
        if (typeof SAWVideoPlayer === 'undefined') {
            setTimeout(initWhenReady, 100);
            return;
        }
        
        console.log('[SAW Video] Starting player...');
        
        const indicator = document.getElementById('progress-indicator');
        const hint = document.getElementById('video-hint-message');
        const progressBar = document.getElementById('video-progress-bar');
        
        if (indicator) indicator.style.display = 'block';
        if (hint) hint.style.display = 'flex';
        if (progressBar) progressBar.style.display = 'block';
        
        const player = new SAWVideoPlayer({
            videoUrl: '<?php echo esc_js($video_url); ?>',
            containerId: 'video-player',
            completionThreshold: 90,
            debug: false,
            
            onProgress: function(percent) {
                console.log('[SAW Video] Progress:', percent + '%');
                
                if (indicator) {
                    indicator.textContent = percent + '%';
                }
                
                const progressFill = document.getElementById('video-progress-fill');
                if (progressFill) {
                    progressFill.style.width = percent + '%';
                }
            },
            
            onComplete: function(data) {
                console.log('[SAW Video] Completed!', data);
                
                if (hint) hint.classList.add('saw-video-hidden');
                if (progressBar) {
                    setTimeout(function() {
                        progressBar.style.display = 'none';
                    }, 400);
                }
                
                const checkbox = document.getElementById('video-confirmed');
                const wrapper = document.getElementById('checkbox-wrapper');
                if (checkbox) {
                    checkbox.disabled = false;
                    if (wrapper) wrapper.classList.add('saw-video-checked');
                }
            }
        });
        
        const checkbox = document.getElementById('video-confirmed');
        const continueBtn = document.getElementById('continue-btn');
        const wrapper = document.getElementById('checkbox-wrapper');
        
        if (checkbox && continueBtn) {
            checkbox.addEventListener('change', function() {
                continueBtn.disabled = !this.checked;
                if (wrapper) {
                    if (this.checked) {
                        wrapper.classList.add('saw-video-checked');
                    } else {
                        wrapper.classList.remove('saw-video-checked');
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
error_log("[VIDEO.PHP] Template finished");
?>