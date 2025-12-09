<?php
/**
 * Shared Training Step - Video
 * Works for Terminal, Invitation and Visitor Info flows
 * 
 * @package SAW_Visitors
 * @version 3.9.9
 * 
 * ZMĚNA v 3.9.9:
 * - REMOVED: Skip training sekce úplně odstraněna
 * - NEW: Free mode pro invitation - checkbox volitelný, video threshold 0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ===== CONTEXT DETECTION =====
$context = 'terminal';
if (isset($is_invitation) && $is_invitation === true) {
    $context = 'invitation';
}
if (isset($is_visitor_info) && $is_visitor_info === true) {
    $context = 'visitor_info';
}

$context_settings = array(
    'terminal' => array(
        'nonce_name' => 'saw_terminal_step',
        'nonce_field' => 'terminal_nonce',
        'action_name' => 'terminal_action',
        'complete_action' => 'complete_training_video',
    ),
    'invitation' => array(
        'nonce_name' => 'saw_invitation_step',
        'nonce_field' => 'invitation_nonce',
        'action_name' => 'invitation_action',
        'complete_action' => 'complete_training',
    ),
    'visitor_info' => array(
        'nonce_name' => 'saw_visitor_info_step',
        'nonce_field' => 'visitor_info_nonce',
        'action_name' => 'visitor_info_action',
        'complete_action' => 'complete_training_video',
    ),
);

$ctx = $context_settings[$context];
$nonce_name = $ctx['nonce_name'];
$nonce_field = $ctx['nonce_field'];
$action_name = $ctx['action_name'];
$complete_action = $ctx['complete_action'];

// FREE MODE for invitation - no confirmation required, instant access
$free_mode = ($context === 'invitation');

// Initialize variables - preserve if already set by controller
if (!isset($video_url)) {
    $video_url = '';
}
if (!isset($visitor_id)) {
    $visitor_id = null;
}
if (!isset($lang)) {
    $lang = 'cs';
}

// Get data based on flow
if ($context === 'invitation') {
    $session = SAW_Session_Manager::instance();
    $flow = $session->get('invitation_flow');
    $lang = $flow['language'] ?? 'cs';
    
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
    
    // Load video URL from DB
    if ($visit && empty($video_url)) {
        $language_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_training_languages 
             WHERE customer_id = %d AND language_code = %s",
            $visit->customer_id,
            $lang
        ));
        
        if ($language_id) {
            $content = $wpdb->get_row($wpdb->prepare(
                "SELECT video_url FROM {$wpdb->prefix}saw_training_content 
                 WHERE customer_id = %d AND branch_id = %d AND language_id = %d",
                $visit->customer_id,
                $visit->branch_id,
                $language_id
            ));
            
            if ($content) {
                $video_url = $content->video_url ?? '';
            }
        }
    }
} elseif ($context === 'visitor_info') {
    // $video_url, $lang and $visitor_id are set by controller
    if (!isset($lang) || empty($lang)) {
        $lang = isset($flow['language']) ? $flow['language'] : 'cs';
    }
    if (!isset($visitor_id)) {
        $visitor_id = isset($flow['visitor_id']) ? $flow['visitor_id'] : null;
    }
} else {
    $flow = isset($flow) ? $flow : [];
    $lang = isset($flow['language']) ? $flow['language'] : 'cs';
    $visitor_id = isset($flow['visitor_ids'][0]) ? $flow['visitor_ids'][0] : null;
    $video_url = isset($video_url) ? $video_url : '';
}

$has_video = !empty($video_url);

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

<div class="saw-page-aurora saw-step-video">
    <div class="saw-page-content saw-page-content-scroll">
    
    <!-- NO skip button - removed in v3.9.9 -->
    
    <div class="saw-video-wrapper">
    
    <?php if (!$has_video): ?>
    
    <div class="saw-empty-state">
        <div class="saw-empty-state-icon">⚠️</div>
        <p class="saw-empty-state-text"><?php echo esc_html($t['no_video']); ?></p>
    </div>
    
    <?php else: ?>
    
    <div class="saw-video-container">
        <div id="video-player"></div>
    </div>
    
    <div id="progress-indicator" class="saw-video-progress-indicator" style="display: none;">
        0%
    </div>
    
    <div class="saw-video-controls-wrapper">
        <div id="video-progress-bar" class="saw-video-progress-bar" style="display: none;">
            <div id="video-progress-fill" class="saw-video-progress-fill" style="width: 0%;"></div>
        </div>
        
        <form method="POST" id="video-form" class="saw-panel-confirm">
            <?php wp_nonce_field($nonce_name, $nonce_field); ?>
            <input type="hidden" name="<?php echo esc_attr($action_name); ?>" value="<?php echo esc_attr($complete_action); ?>">
            
            <div id="video-hint-message" style="display: none; font-size: 0.8rem; padding: 0.4rem 0.8rem; margin-bottom: 0.75rem; background: rgba(59, 130, 246, 0.2); border-radius: 6px; text-align: center; color: #93c5fd;">
                <span style="margin-right: 0.25rem;">▶️</span>
                <span><?php echo esc_html($t['hint']); ?></span>
            </div>
            
            <?php if (!$completed): ?>
            <label class="saw-panel-checkbox" id="checkbox-wrapper">
                <input type="checkbox" 
                       name="video_confirmed" 
                       id="video-confirmed"
                       value="1"
                       <?php if (!$free_mode): ?>required<?php endif; ?>
                       <?php if (!$free_mode): ?>disabled<?php endif; ?>>
                <span><?php echo esc_html($t['confirm']); ?></span>
            </label>
            <?php endif; ?>
            
            <button type="submit" 
                    class="saw-panel-btn"
                    id="continue-btn"
                    <?php echo (!$completed && !$free_mode) ? 'disabled' : ''; ?>>
                <?php echo esc_html($t['continue']); ?> →
            </button>
        </form>
    </div>
    
    <?php endif; ?>
    
    </div>
    </div>
</div>

<?php if ($has_video): ?>
<?php 
$video_player_path = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/assets/js/terminal/video-player.js';
$video_player_url = SAW_VISITORS_PLUGIN_URL . 'includes/frontend/terminal/assets/js/terminal/video-player.js';
if (file_exists($video_player_path)):
?>
<script src="<?php echo esc_url($video_player_url); ?>?ver=<?php echo time(); ?>"></script>
<?php endif; ?>

<script>
(function() {
    'use strict';
    
    var videoCompleted = <?php echo $completed ? 'true' : 'false'; ?>;
    
    function initPlayer() {
        if (typeof SAWVideoPlayer === 'undefined') {
            setTimeout(initPlayer, 100);
            return;
        }
        
        showProgressBar();
        showHint();
        
        var player = new SAWVideoPlayer({
            videoUrl: '<?php echo esc_js($video_url); ?>',
            containerId: 'video-player',
            completionThreshold: <?php echo $free_mode ? '0' : '90'; ?>,
            debug: false,
            
            onProgress: function(progress) {
                updateProgress(progress);
            },
            
            onComplete: function() {
                hideHint();
                enableCheckbox();
            }
        });
    }
    
    function updateProgress(progress) {
        var indicator = document.getElementById('progress-indicator');
        var fill = document.getElementById('video-progress-fill');
        
        if (indicator) {
            indicator.textContent = Math.round(progress) + '%';
        }
        if (fill) {
            fill.style.width = progress + '%';
        }
    }
    
    function showHint() {
        var hint = document.getElementById('video-hint-message');
        if (hint) hint.style.display = 'flex';
    }
    
    function hideHint() {
        var hint = document.getElementById('video-hint-message');
        if (hint) hint.style.display = 'none';
    }
    
    function showProgressBar() {
        var bar = document.getElementById('video-progress-bar');
        var indicator = document.getElementById('progress-indicator');
        if (bar) bar.style.display = 'block';
        if (indicator) indicator.style.display = 'flex';
    }
    
    function enableCheckbox() {
        var checkbox = document.getElementById('video-confirmed');
        var wrapper = document.getElementById('checkbox-wrapper');
        var btn = document.getElementById('continue-btn');
        
        if (checkbox) {
            checkbox.disabled = false;
            checkbox.addEventListener('change', function() {
                if (btn) btn.disabled = !this.checked;
            });
        }
        if (wrapper) {
            wrapper.style.opacity = '1';
        }
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPlayer);
    } else {
        initPlayer();
    }
    
    if (videoCompleted) {
        var btn = document.getElementById('continue-btn');
        if (btn) btn.disabled = false;
    }
    
    <?php if ($free_mode): ?>
    // FREE MODE - enable checkbox and button immediately
    var freeCheckbox = document.getElementById('video-confirmed');
    var freeBtn = document.getElementById('continue-btn');
    if (freeCheckbox) freeCheckbox.disabled = false;
    if (freeBtn) freeBtn.disabled = false;
    <?php endif; ?>
})();
</script>
<?php endif; ?>
