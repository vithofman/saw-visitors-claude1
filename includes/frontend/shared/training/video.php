<?php
/**
 * Shared Training Step - Video
 * Works for both Terminal and Invitation flows
 * 
 * @package SAW_Visitors
 * @version 3.0.1
 */

if (!defined('ABSPATH')) {
    exit;
}

// Detect flow type
$is_invitation = isset($is_invitation) ? $is_invitation : false;

error_log("=== VIDEO.PHP TEMPLATE START ===");
error_log("is_invitation: " . ($is_invitation ? 'TRUE' : 'FALSE'));

// Get data from appropriate flow
if ($is_invitation) {
    // Invitation flow
    // ✅ OPRAVA: Použij správnou metodu instance() místo get_instance()
    $session = SAW_Session_Manager::instance();
    $flow = $session->get('invitation_flow');
    $lang = $flow['language'] ?? 'cs';
    
    error_log("[VIDEO.PHP Invitation] Language from session: {$lang}");
    
    // Get visitor ID from invitation flow
    global $wpdb;
    $visit = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}saw_visits WHERE id = %d",
        $flow['visit_id'] ?? 0
    ));
    
    error_log("[VIDEO.PHP Invitation] Visit ID: " . ($flow['visit_id'] ?? 'N/A') . ", Found: " . ($visit ? 'YES' : 'NO'));
    
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
    
    // Get video URL from training content
    $video_url = '';
    if ($visit) {
        error_log("[VIDEO.PHP Invitation] Looking for language_id: customer_id={$visit->customer_id}, lang={$lang}");
        
        $language_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_training_languages 
             WHERE customer_id = %d AND language_code = %s",
            $visit->customer_id,
            $lang
        ));
        
        error_log("[VIDEO.PHP Invitation] Found language_id: " . ($language_id ? $language_id : 'NOT FOUND'));
        
        if ($language_id) {
            $content = $wpdb->get_row($wpdb->prepare(
                "SELECT video_url FROM {$wpdb->prefix}saw_training_content 
                 WHERE customer_id = %d AND branch_id = %d AND language_id = %d",
                $visit->customer_id,
                $visit->branch_id,
                $language_id
            ));
            
            error_log("[VIDEO.PHP Invitation] Content found: " . ($content ? 'YES' : 'NO'));
            if ($content) {
                error_log("[VIDEO.PHP Invitation] Raw video_url: " . ($content->video_url ?? 'EMPTY'));
            }
            
            if ($content && !empty($content->video_url)) {
                $video_url = $content->video_url;
                // Convert YouTube/Vimeo URLs to embed format
                if (strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false) {
                    preg_match('/(?:v=|youtu\.be\/)([^&]+)/', $video_url, $matches);
                    if (!empty($matches[1])) {
                        $video_url = 'https://www.youtube.com/embed/' . $matches[1];
                    }
                } elseif (strpos($video_url, 'vimeo.com') !== false) {
                    preg_match('/vimeo\.com\/(\d+)/', $video_url, $matches);
                    if (!empty($matches[1])) {
                        $video_url = 'https://player.vimeo.com/video/' . $matches[1];
                    }
                }
                
                error_log("[VIDEO.PHP Invitation] Converted video_url: " . $video_url);
            }
        }
    }
} else {
    // Terminal flow - beze změny
    $flow = isset($flow) ? $flow : [];
    $lang = isset($flow['language']) ? $flow['language'] : 'cs';
    $visitor_id = isset($flow['visitor_ids'][0]) ? $flow['visitor_ids'][0] : null;
    $video_url = isset($video_url) ? $video_url : '';
}

error_log("[SHARED VIDEO.PHP] Is Invitation: " . ($is_invitation ? 'yes' : 'no') . ", Language: {$lang}, Visitor ID: " . ($visitor_id ?? 'NULL'));
error_log("[SHARED VIDEO.PHP] Final Video URL: " . ($video_url ? $video_url : 'NOT SET'));

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
        'skip_info' => 'Toto školení je volitelné. Můžete ho přeskočit a projít si později.',
        'skip_btn' => '⏭️ Přeskočit školení',
    ),
    'en' => array(
        'confirm' => 'I confirm video viewing',
        'continue' => 'Continue',
        'loading' => 'Loading...',
        'hint' => 'Watch the entire video (min. 90%)',
        'no_video' => 'Video not available',
        'skip_info' => 'This training is optional. You can skip it and review it later.',
        'skip_btn' => '⏭️ Skip training',
    ),
    'sk' => array(
        'confirm' => 'Potvrdzujem zhliadnutie videa',
        'continue' => 'Pokračovať',
        'loading' => 'Načítavanie...',
        'hint' => 'Pozrite si celé video (min. 90%)',
        'no_video' => 'Video nie je k dispozícii',
        'skip_info' => 'Toto školenie je voliteľné. Môžete ho preskočiť a prejsť si neskôr.',
        'skip_btn' => '⏭️ Preskočiť školenie',
    ),
    'uk' => array(
        'confirm' => 'Підтверджую перегляд відео',
        'continue' => 'Продовжити',
        'loading' => 'Завантаження...',
        'hint' => 'Перегляньте все відео (мін. 90%)',
        'no_video' => 'Відео недоступне',
        'skip_info' => 'Це навчання є опціональним. Ви можете пропустити його та переглянути пізніше.',
        'skip_btn' => '⏭️ Пропустити навчання',
    ),
);

$t = isset($translations[$lang]) ? $translations[$lang] : $translations['cs'];

// Determine nonce and action names
$nonce_name = $is_invitation ? 'saw_invitation_step' : 'saw_terminal_step';
$nonce_field = $is_invitation ? 'invitation_nonce' : 'terminal_nonce';
$action_name = $is_invitation ? 'invitation_action' : 'terminal_action';
$complete_action = $is_invitation ? 'complete_training' : 'complete_training_video';
$skip_action = $is_invitation ? 'skip_training' : 'skip_training';
?>

<!-- Žádný <style> blok! CSS je v pages.css -->

<div class="saw-page-aurora saw-step-video">
    <div class="saw-page-content saw-page-content-scroll">
        <div class="saw-video-wrapper">
    
    <?php if ($is_invitation): ?>
    <!-- Skip button for invitation mode -->
    <div class="saw-panel-skip">
        <p class="saw-panel-skip-info">
            💡 <?php echo esc_html($t['skip_info']); ?>
        </p>
        <form method="POST" style="display: inline-block;">
            <?php wp_nonce_field($nonce_name, $nonce_field); ?>
            <input type="hidden" name="<?php echo esc_attr($action_name); ?>" value="<?php echo esc_attr($skip_action); ?>">
            <button type="submit" class="saw-panel-skip-btn">
                <?php echo esc_html($t['skip_btn']); ?>
            </button>
        </form>
    </div>
    <?php endif; ?>
    
    <?php if (!$has_video): ?>
    
    <div class="saw-empty-state">
        <div class="saw-empty-state-icon">⚠️</div>
        <p class="saw-empty-state-text"><?php echo esc_html($t['no_video']); ?></p>
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
    <div id="video-hint-message" class="saw-video-hint" style="display: none;">
        <span>▶️</span>
        <span><?php echo esc_html($t['hint']); ?></span>
    </div>
    
    <!-- Controls Wrapper (Bottom Right) -->
    <div class="saw-video-controls-wrapper">
        <!-- Progress Bar -->
        <div id="video-progress-bar" class="saw-video-progress-bar" style="display: none;">
            <div id="video-progress-fill" class="saw-video-progress-fill" style="width: 0%;"></div>
        </div>
        
        <!-- Floating Actions -->
        <form method="POST" id="video-form" class="saw-panel-confirm">
            <?php wp_nonce_field($nonce_name, $nonce_field); ?>
            <input type="hidden" name="<?php echo esc_attr($action_name); ?>" value="<?php echo esc_attr($complete_action); ?>">
            
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
    </div><!-- .saw-video-controls-wrapper -->
    </div><!-- .saw-video-controls-wrapper -->
    
    <?php endif; ?>
    </div>
</div>

<?php if ($has_video): ?>

<?php 
// ✅ Načti video player script ze správné cesty
$video_player_path = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/assets/js/terminal/video-player.js';
$video_player_url = SAW_VISITORS_PLUGIN_URL . 'includes/frontend/terminal/assets/js/terminal/video-player.js';

if (file_exists($video_player_path)):
?>
<script src="<?php echo esc_url($video_player_url); ?>?ver=<?php echo time(); ?>"></script>
<?php else: ?>
<!-- Video player script not found at: <?php echo esc_html($video_player_path); ?> -->
<?php endif; ?>

<script>
(function() {
    'use strict';
    
    console.log('[SAW Video] Initializing...');
    
    function initWhenReady() {
        if (typeof SAWVideoPlayer === 'undefined') {
            console.log('[SAW Video] Waiting for SAWVideoPlayer class...');
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
                
                if (hint) hint.style.display = 'none';
                if (progressBar) {
                    setTimeout(function() {
                        progressBar.style.display = 'none';
                    }, 400);
                }
                
                const checkbox = document.getElementById('video-confirmed');
                const wrapper = document.getElementById('checkbox-wrapper');
                if (checkbox) {
                    checkbox.disabled = false;
                    if (wrapper) wrapper.classList.add('checked');
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
```

Klíčová změna je cesta ke scriptu:
```
.../assets/js/video-player.js
```
→
```
.../assets/js/terminal/video-player.js

<?php
error_log("[SHARED VIDEO.PHP] Template finished");
?>

