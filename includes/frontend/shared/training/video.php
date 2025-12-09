<?php
/**
 * Shared Training Step - Video
 * Works for Terminal, Invitation and Visitor Info flows
 * 
 * @package SAW_Visitors
 * @version 3.9.4
 * 
 * ZMĚNA v 3.9.4:
 * - OPRAVA: Hint přesunut DOVNITŘ formu (saw-panel-confirm) - to je ten fixní panel vpravo dole
 * 
 * ZMĚNA v 3.9.3:
 * - Hint přesunut do controls wrapper (nestačilo - wrapper není fixní panel)
 * 
 * ZMĚNA v 3.9.2:
 * - OPRAVA: HTML struktura - skip button byl UVNITŘ video-wrapper
 * 
 * ZMĚNA v 3.9.1:
 * - OPRAVA: Podmíněná inicializace $video_url
 * 
 * ZMĚNA v 3.9.0:
 * - OPRAVA: Skip button POUZE pro invitation
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

// Context-specific form settings
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
$skip_action = 'skip_training';

// Initialize variables - BUT preserve if already set by controller (visitor_info context)
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
    
    $video_url = '';
    if ($visit) {
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
            
            if ($content && !empty($content->video_url)) {
                $video_url = $content->video_url;
                
                if (strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false) {
                    preg_match('/(?:v=|youtu\.be\/)([^&\?]+)/', $video_url, $matches);
                    if (!empty($matches[1])) {
                        $video_url = 'https://www.youtube.com/embed/' . $matches[1];
                    }
                } elseif (strpos($video_url, 'vimeo.com') !== false) {
                    preg_match('/vimeo\.com\/(\d+)/', $video_url, $matches);
                    if (!empty($matches[1])) {
                        $video_url = 'https://player.vimeo.com/video/' . $matches[1];
                    }
                }
            }
        }
    }
} elseif ($context === 'visitor_info') {
    // For visitor_info, variables are set by controller BEFORE include
    // Just ensure flow array exists for any template needs
    $flow = isset($flow) ? $flow : array();
    // $video_url is already set by controller - don't overwrite!
    // $lang and $visitor_id are also set by controller
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
        'skip_info' => 'Toto školení je volitelné. Můžete ho přeskočit a projít si později.',
        'skip_button' => 'Přeskočit školení',
    ),
    'en' => array(
        'confirm' => 'I confirm video viewing',
        'continue' => 'Continue',
        'loading' => 'Loading...',
        'hint' => 'Watch the entire video (min. 90%)',
        'no_video' => 'Video not available',
        'skip_info' => 'This training is optional. You can skip it and review it later.',
        'skip_button' => 'Skip training',
    ),
    'sk' => array(
        'confirm' => 'Potvrdzujem zhliadnutie videa',
        'continue' => 'Pokračovať',
        'loading' => 'Načítavanie...',
        'hint' => 'Pozrite si celé video (min. 90%)',
        'no_video' => 'Video nie je k dispozícii',
        'skip_info' => 'Toto školenie je voliteľné. Môžete ho preskočiť a prejsť si neskôr.',
        'skip_button' => 'Preskočiť školenie',
    ),
    'uk' => array(
        'confirm' => 'Підтверджую перегляд відео',
        'continue' => 'Продовжити',
        'loading' => 'Завантаження...',
        'hint' => 'Перегляньте все відео (мін. 90%)',
        'no_video' => 'Відео недоступне',
        'skip_info' => 'Це навчання є необов\'язковим. Ви можете пропустити його та переглянути пізніше.',
        'skip_button' => 'Пропустити навчання',
    ),
);

$t = isset($translations[$lang]) ? $translations[$lang] : $translations['cs'];
?>

<div class="saw-page-aurora saw-step-video">
    <div class="saw-page-content saw-page-content-scroll">
    
    <?php // v3.9.0 FIX: Skip button ONLY for invitation, NOT for visitor_info ?>
    <?php if ($context === 'invitation'): ?>
    <div class="saw-panel-skip">
        <p class="saw-panel-skip-info">
            💡 <?php echo esc_html($t['skip_info']); ?>
        </p>
        <form method="POST" style="display: inline-block;">
            <?php wp_nonce_field($nonce_name, $nonce_field); ?>
            <input type="hidden" name="<?php echo esc_attr($action_name); ?>" value="<?php echo esc_attr($skip_action); ?>">
            <button type="submit" class="saw-panel-skip-btn">
                ⏭️ <?php echo esc_html($t['skip_button']); ?>
            </button>
        </form>
    </div>
    <?php endif; ?>
    
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
            
            <!-- v3.9.4: Hint přesunut DOVNITŘ formu, před checkbox -->
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
    </div>
    
    <?php endif; ?>
    
        </div>
    </div>
</div>

<?php if ($has_video): ?>
<script src="https://www.youtube.com/iframe_api"></script>
<script>
(function() {
    'use strict';
    
    var videoUrl = '<?php echo esc_js($video_url); ?>';
    var player = null;
    var progressInterval = null;
    var maxProgress = 0;
    var videoCompleted = <?php echo $completed ? 'true' : 'false'; ?>;
    var isVimeo = videoUrl.indexOf('vimeo.com') !== -1;
    var isYouTube = videoUrl.indexOf('youtube.com') !== -1;
    
    function initPlayer() {
        var container = document.getElementById('video-player');
        if (!container) return;
        
        if (isYouTube) {
            initYouTubePlayer();
        } else if (isVimeo) {
            initVimeoPlayer();
        } else {
            initGenericPlayer();
        }
    }
    
    function initYouTubePlayer() {
        var videoId = extractYouTubeId(videoUrl);
        if (!videoId) return;
        
        if (typeof YT !== 'undefined' && YT.Player) {
            createYouTubePlayer(videoId);
        } else {
            window.onYouTubeIframeAPIReady = function() {
                createYouTubePlayer(videoId);
            };
        }
    }
    
    function createYouTubePlayer(videoId) {
        player = new YT.Player('video-player', {
            videoId: videoId,
            width: '100%',
            height: '100%',
            playerVars: {
                'playsinline': 1,
                'rel': 0,
                'modestbranding': 1
            },
            events: {
                'onReady': onPlayerReady,
                'onStateChange': onPlayerStateChange
            }
        });
    }
    
    function extractYouTubeId(url) {
        var match = url.match(/(?:embed\/|v=|youtu\.be\/)([^&\?]+)/);
        return match ? match[1] : null;
    }
    
    function onPlayerReady(event) {
        showHint();
        if (!videoCompleted) {
            startProgressTracking();
        }
    }
    
    function onPlayerStateChange(event) {
        if (event.data === YT.PlayerState.PLAYING) {
            hideHint();
            showProgressBar();
        }
    }
    
    function initVimeoPlayer() {
        var container = document.getElementById('video-player');
        container.innerHTML = '<iframe id="vimeo-iframe" src="' + videoUrl + '?api=1" width="100%" height="100%" frameborder="0" allow="autoplay; fullscreen" allowfullscreen></iframe>';
        
        var script = document.createElement('script');
        script.src = 'https://player.vimeo.com/api/player.js';
        script.onload = function() {
            var iframe = document.getElementById('vimeo-iframe');
            player = new Vimeo.Player(iframe);
            
            player.on('play', function() {
                hideHint();
                showProgressBar();
            });
            
            showHint();
            if (!videoCompleted) {
                startVimeoProgressTracking();
            }
        };
        document.head.appendChild(script);
    }
    
    function initGenericPlayer() {
        var container = document.getElementById('video-player');
        container.innerHTML = '<iframe src="' + videoUrl + '" width="100%" height="100%" frameborder="0" allowfullscreen></iframe>';
        showHint();
        
        setTimeout(function() {
            enableCheckbox();
        }, 5000);
    }
    
    function startProgressTracking() {
        progressInterval = setInterval(function() {
            if (player && player.getCurrentTime && player.getDuration) {
                var current = player.getCurrentTime();
                var duration = player.getDuration();
                
                if (duration > 0) {
                    var progress = (current / duration) * 100;
                    if (progress > maxProgress) {
                        maxProgress = progress;
                    }
                    updateProgressUI(maxProgress);
                    
                    if (maxProgress >= 90) {
                        enableCheckbox();
                        clearInterval(progressInterval);
                    }
                }
            }
        }, 1000);
    }
    
    function startVimeoProgressTracking() {
        progressInterval = setInterval(function() {
            if (player) {
                Promise.all([player.getCurrentTime(), player.getDuration()]).then(function(values) {
                    var current = values[0];
                    var duration = values[1];
                    
                    if (duration > 0) {
                        var progress = (current / duration) * 100;
                        if (progress > maxProgress) {
                            maxProgress = progress;
                        }
                        updateProgressUI(maxProgress);
                        
                        if (maxProgress >= 90) {
                            enableCheckbox();
                            clearInterval(progressInterval);
                        }
                    }
                });
            }
        }, 1000);
    }
    
    function updateProgressUI(progress) {
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
})();
</script>
<?php endif; ?>