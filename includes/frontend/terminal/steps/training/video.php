<?php
/**
 * Terminal Training Step - BOZP Video
 * 
 * @package SAW_Visitors
 * @version 2.2.0 - Rebuilt from working minimal version
 */

if (!defined('ABSPATH')) {
    exit;
}

error_log("[VIDEO.PHP] Template started");

// Get language from flow
$lang = isset($flow['language']) ? $flow['language'] : 'cs';
$visitor_id = isset($flow['visitor_id']) ? $flow['visitor_id'] : null;

error_log("[VIDEO.PHP] Language: {$lang}, Visitor ID: {$visitor_id}");
error_log("[VIDEO.PHP] Video URL: " . (isset($video_url) ? $video_url : 'NOT SET'));

// Check if video URL exists
$has_video = !empty($video_url);

// Check if already completed
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
        'title' => 'Školení BOZP',
        'subtitle' => 'Sledujte prosím celé video',
        'watched' => 'Video bylo shlédnuto',
        'mark_watched' => 'Potvrdit zhlédnutí',
        'continue' => 'Pokračovat',
        'required' => 'Sledování je povinné',
    ),
    'en' => array(
        'title' => 'Safety Training',
        'subtitle' => 'Please watch the entire video',
        'watched' => 'Video has been watched',
        'mark_watched' => 'Confirm watched',
        'continue' => 'Continue',
        'required' => 'Watching is required',
    ),
    'sk' => array(
        'title' => 'Školenie BOZP',
        'subtitle' => 'Prosím sledujte celé video',
        'watched' => 'Video bolo zhliadnuté',
        'mark_watched' => 'Potvrdiť zhliadnutie',
        'continue' => 'Pokračovať',
        'required' => 'Sledovanie je povinné',
    ),
    'uk' => array(
        'title' => 'Навчання з охорони праці',
        'subtitle' => 'Будь ласка, перегляньте все відео',
        'watched' => 'Відео переглянуто',
        'mark_watched' => 'Підтвердити перегляд',
        'continue' => 'Продовжити',
        'required' => 'Перегляд обов\'язковий',
    ),
);

$t = isset($translations[$lang]) ? $translations[$lang] : $translations['cs'];

error_log("[VIDEO.PHP] Has video: " . ($has_video ? 'YES' : 'NO'));
error_log("[VIDEO.PHP] Completed: " . ($completed ? 'YES' : 'NO'));
?>

<div class="saw-terminal-card">
    <div class="saw-terminal-card-header">
        <h2 class="saw-terminal-card-title">
            📹 <?php echo esc_html($t['title']); ?>
        </h2>
        <p class="saw-terminal-card-subtitle">
            <?php echo esc_html($t['subtitle']); ?>
        </p>
    </div>
    
    <div class="saw-terminal-card-body">
        
        <?php if (!$has_video): ?>
        <!-- Error: No video URL -->
        <div style="background: #fff5f5; border: 2px solid #fc8181; border-radius: 12px; padding: 2rem; margin-bottom: 2rem; text-align: center;">
            <p style="margin: 0 0 1rem 0; font-size: 1.25rem; color: #c53030; font-weight: 600;">
                ⚠️ Video není k dispozici
            </p>
        </div>
        
        <form method="POST">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="complete_training_video">
            <button type="submit" class="saw-terminal-btn saw-terminal-btn-success">
                Pokračovat bez videa →
            </button>
        </form>
        
        <?php else: ?>
        
        <!-- Video container -->
        <div class="saw-training-video-container" style="margin-bottom: 2rem;">
            <div class="saw-training-video-wrapper" style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 12px; background: #000;">
                <div id="training-video"></div>
            </div>
            
            <!-- Video progress bar -->
            <div class="saw-training-video-progress" style="margin-top: 1rem;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="flex: 1; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                        <div id="video-progress-bar" 
                             style="height: 100%; width: 0%; background: linear-gradient(90deg, #48bb78, #38a169); transition: width 0.3s ease;">
                        </div>
                    </div>
                    <span id="video-progress-text" style="font-size: 0.875rem; color: #718096; min-width: 50px; text-align: right;">0%</span>
                </div>
            </div>
        </div>
        
        <?php if ($completed): ?>
        <!-- Already watched -->
        <div style="background: #f0fdf4; border: 2px solid #86efac; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; text-align: center;">
            <p style="margin: 0; font-size: 1.125rem; color: #16a34a; font-weight: 600;">
                ✅ <?php echo esc_html($t['watched']); ?>
            </p>
        </div>
        <?php endif; ?>
        
        <!-- Form -->
        <form method="POST" id="training-video-form">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="complete_training_video">
            <input type="hidden" name="video_watched" id="video-watched-input" value="<?php echo $completed ? '1' : '0'; ?>">
            
            <?php if (!$completed): ?>
            <button type="button" 
                    id="mark-watched-btn"
                    class="saw-terminal-btn saw-terminal-btn-secondary" 
                    style="margin-bottom: 1rem;"
                    disabled>
                <?php echo esc_html($t['mark_watched']); ?>
            </button>
            <?php endif; ?>
            
            <button type="submit" 
                    class="saw-terminal-btn saw-terminal-btn-success saw-terminal-btn-disabled"
                    id="continue-btn"
                    style="opacity: 0.5; cursor: not-allowed;"
                    disabled>
                <?php echo esc_html($t['continue']); ?> →
            </button>
        </form>
        
        <p style="margin-top: 1.5rem; text-align: center; color: #a0aec0; font-size: 0.875rem;">
            <strong>⚠️ <?php echo esc_html($t['required']); ?></strong>
        </p>
        
        <?php endif; ?>
        
    </div>
</div>

<?php if ($has_video): ?>
<?php
// Extrahuj YouTube video ID z URL
$video_id = '';
if (preg_match('/youtube\.com\/embed\/([^?\/]+)/', $video_url, $matches)) {
    $video_id = $matches[1];
} elseif (preg_match('/youtu\.be\/([^?\/]+)/', $video_url, $matches)) {
    $video_id = $matches[1];
}
?>
<script src="https://www.youtube.com/iframe_api"></script>
<script>
var player;
var videoWatched = <?php echo $completed ? 'true' : 'false'; ?>;
var maxWatchedPercentage = 0;

function onYouTubeIframeAPIReady() {
    console.log('YouTube API ready');
    player = new YT.Player('training-video', {
        height: '100%',
        width: '100%',
        videoId: '<?php echo esc_js($video_id); ?>',
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

function onPlayerReady(event) {
    console.log('Player ready');
    // Přidej CSS styling pro player
    jQuery('#training-video').css({
        'position': 'absolute',
        'top': '0',
        'left': '0',
        'width': '100%',
        'height': '100%'
    });
}

function onPlayerStateChange(event) {
    if (event.data == YT.PlayerState.PLAYING) {
        console.log('Video playing - starting progress tracking');
        startProgressTracking();
    }
}

var progressInterval;

function startProgressTracking() {
    if (progressInterval) {
        clearInterval(progressInterval);
    }
    
    progressInterval = setInterval(function() {
        if (player && player.getCurrentTime && player.getDuration) {
            var current = player.getCurrentTime();
            var duration = player.getDuration();
            
            if (duration > 0) {
                var percentage = Math.floor((current / duration) * 100);
                
                // Sleduj maximální dosažený progress
                if (percentage > maxWatchedPercentage) {
                    maxWatchedPercentage = percentage;
                }
                
                jQuery('#video-progress-bar').css('width', percentage + '%');
                jQuery('#video-progress-text').text(percentage + '%');
                
                console.log('Video progress:', percentage + '%', 'Max:', maxWatchedPercentage + '%');
                
                // Povolit potvrzení když dosáhne 90%
                if (maxWatchedPercentage >= 90 && !videoWatched) {
                    jQuery('#mark-watched-btn').prop('disabled', false);
                    console.log('90% reached - enabling confirm button');
                }
            }
        }
    }, 1000);
}

jQuery(document).ready(function($) {
    console.log('Video template JS started');
    
    // Mark as watched handler
    $('#mark-watched-btn').on('click', function() {
        videoWatched = true;
        $('#video-watched-input').val('1');
        
        // Vizuálně aktivuj tlačítko Pokračovat
        $('#continue-btn')
            .prop('disabled', false)
            .removeClass('saw-terminal-btn-disabled')
            .css({
                'opacity': '1',
                'cursor': 'pointer'
            });
        
        if (progressInterval) {
            clearInterval(progressInterval);
        }
        
        $(this).fadeOut(300, function() {
            $('<div style="background: #f0fdf4; border: 2px solid #86efac; border-radius: 12px; padding: 1.5rem; margin-bottom: 1rem; text-align: center;">' +
              '<p style="margin: 0; font-size: 1.125rem; color: #16a34a; font-weight: 600;">✅ Video bylo shlédnuto</p>' +
              '</div>').insertBefore('#continue-btn').hide().fadeIn(300);
        });
    });
    
    // EMERGENCY FALLBACK: Po 2 minutách povolit pokračovat i bez sledování
    setTimeout(function() {
        if (!videoWatched) {
            console.log('Emergency fallback - enabling buttons after 2 minutes');
            $('#mark-watched-btn').prop('disabled', false);
        }
    }, 120000);
});
</script>
<?php endif; ?>

<?php
error_log("[VIDEO.PHP] Template finished");
?>