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

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

.saw-terminal-footer,
.saw-invitation-footer {
    display: none !important;
}

.saw-video-fullscreen {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: #1a202c;
    overflow: hidden;
}

.saw-video-container {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 95%;
    max-width: 1600px;
    aspect-ratio: 16 / 9;
    background: #000;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
}

#video-player {
    width: 100%;
    height: 100%;
}

#video-player iframe {
    width: 100%;
    height: 100%;
    border: none;
}

.saw-video-loading {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    color: white;
}

.saw-video-spinner {
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

/* Progress Indicator - Top Right */
.saw-video-progress-indicator {
    position: fixed;
    top: 2rem;
    right: 2rem;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(20px);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 999px;
    font-weight: 600;
    font-size: 1.125rem;
    z-index: 100;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    min-width: 80px;
    text-align: center;
}

/* Hint Message - HIGHEST Z-INDEX, ABOVE EVERYTHING */
.saw-video-hint-wrapper {
    position: fixed;
    bottom: 10.5rem;
    right: 2rem;
    width: 280px;
    background: rgba(102, 126, 234, 0.25);
    backdrop-filter: blur(20px);
    border-radius: 12px;
    padding: 0.875rem 1.25rem;
    border: 1px solid rgba(102, 126, 234, 0.5);
    z-index: 300;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    opacity: 1;
    transition: opacity 0.4s ease, transform 0.4s ease;
}

.saw-video-hint-wrapper.saw-video-hidden {
    opacity: 0;
    transform: translateY(10px);
    pointer-events: none;
}

.saw-video-hint-icon {
    font-size: 1.25rem;
    flex-shrink: 0;
}

.saw-video-hint-text {
    color: white;
    font-size: 0.875rem;
    font-weight: 600;
    line-height: 1.4;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

/* Progress Bar */
.saw-video-progress-bar {
    position: fixed;
    bottom: 7.5rem;
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

.saw-video-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #48bb78, #38a169);
    border-radius: 12px;
    transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 0 16px rgba(72, 187, 120, 0.8);
}

/* Floating Actions - LOWEST */
.saw-video-confirm-panel {
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

.saw-video-confirm-checkbox {
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

.saw-video-confirm-checkbox:hover {
    background: rgba(255, 255, 255, 0.18);
    border-color: rgba(102, 126, 234, 0.5);
    transform: translateY(-2px);
    box-shadow: 0 6px 30px rgba(0, 0, 0, 0.3);
}

.saw-video-confirm-checkbox.saw-video-checked {
    background: rgba(72, 187, 120, 0.2);
    border-color: rgba(72, 187, 120, 0.5);
}

.saw-video-confirm-checkbox input {
    width: 22px;
    height: 22px;
    cursor: pointer;
    accent-color: #48bb78;
    flex-shrink: 0;
}

.saw-video-confirm-checkbox span {
    font-weight: 600;
    color: white;
    font-size: 0.925rem;
    line-height: 1.4;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.saw-video-continue-btn {
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

.saw-video-continue-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 32px rgba(102, 126, 234, 0.6);
}

.saw-video-continue-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
    transform: none;
}

/* Skip wrapper for invitation */
.saw-training-skip-wrapper {
    position: fixed;
    top: 2rem;
    left: 2rem;
    padding: 1.5rem;
    background: rgba(139, 92, 246, 0.1);
    border: 1px solid rgba(139, 92, 246, 0.3);
    border-radius: 12px;
    text-align: center;
    z-index: 150;
    max-width: 300px;
}

.saw-skip-info {
    color: #c4b5fd;
    margin-bottom: 1rem;
    font-size: 0.875rem;
}

.saw-btn-skip {
    padding: 0.75rem 1.5rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    color: #f9fafb;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 0.875rem;
}

.saw-btn-skip:hover {
    background: rgba(255, 255, 255, 0.15);
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .saw-video-container {
        width: 95%;
        top: 35%;
    }
    
    .saw-video-progress-indicator {
        top: 1rem;
        right: 1rem;
        font-size: 0.95rem;
        padding: 0.625rem 1.25rem;
    }
    
    .saw-video-hint-wrapper {
        bottom: auto;
        top: 4.5rem;
        right: 1rem;
        left: 1rem;
        width: auto;
    }
    
    .saw-video-progress-bar {
        bottom: auto;
        top: 8rem;
        right: 1rem;
        left: 1rem;
        width: auto;
        height: 10px;
    }
    
    /* ✅ KLÍČOVÁ OPRAVA: Posun confirm panelu NAD mobilní progress indicator lištu */
    .saw-video-confirm-panel {
        bottom: calc(90px + env(safe-area-inset-bottom, 0)); /* Bylo 1rem, teď 90px + safe area */
        right: 1rem;
        left: 1rem;
        min-width: 0;
    }
    
    .saw-video-confirm-checkbox {
        padding: 0.875rem 1.25rem;
    }
    
    .saw-video-confirm-checkbox span {
        font-size: 0.875rem;
    }
    
    .saw-video-continue-btn {
        padding: 0.875rem 1.25rem;
    }
    
    .saw-training-skip-wrapper {
        top: 1rem;
        left: 1rem;
        right: 1rem;
        max-width: none;
    }
}
</style>

<div class="saw-video-fullscreen">
    
    <?php if ($is_invitation): ?>
    <!-- Skip button for invitation mode -->
    <div class="saw-training-skip-wrapper">
        <p class="saw-skip-info">
            💡 <?php echo esc_html($t['skip_info']); ?>
        </p>
        <form method="POST" style="display: inline-block;">
            <?php wp_nonce_field($nonce_name, $nonce_field); ?>
            <input type="hidden" name="<?php echo esc_attr($action_name); ?>" value="<?php echo esc_attr($skip_action); ?>">
            <button type="submit" class="saw-btn-skip">
                <?php echo esc_html($t['skip_btn']); ?>
            </button>
        </form>
    </div>
    <?php endif; ?>
    
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
    
    <!-- Progress Bar -->
    <div id="video-progress-bar" class="saw-video-progress-bar" style="display: none;">
        <div id="video-progress-fill" class="saw-video-progress-fill" style="width: 0%;"></div>
    </div>
    
    <!-- Floating Actions -->
    <form method="POST" id="video-form" class="saw-video-confirm-panel">
        <?php wp_nonce_field($nonce_name, $nonce_field); ?>
        <input type="hidden" name="<?php echo esc_attr($action_name); ?>" value="<?php echo esc_attr($complete_action); ?>">
        
        <?php if (!$completed): ?>
        <label class="saw-video-confirm-checkbox" id="checkbox-wrapper">
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
                class="saw-video-continue-btn"
                id="continue-btn"
                <?php echo !$completed ? 'disabled' : ''; ?>>
            <?php echo esc_html($t['continue']); ?> →
        </button>
    </form>
    
    <?php endif; ?>

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

