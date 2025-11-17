<?php
/**
 * Terminal Training Step - BOZP Video
 * 
 * Safety training video with progress tracking
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$flow = $this->session->get('terminal_flow');
$lang = $flow['language'] ?? 'cs';
$visitor_id = $flow['visitor_id'] ?? null;

// TODO: Load video URL from settings/database
$video_url = 'https://www.youtube.com/embed/SAMPLE_VIDEO_ID';

// Check if already completed
$completed = false;
if ($visitor_id) {
    global $wpdb;
    $visitor = $wpdb->get_row($wpdb->prepare(
        "SELECT training_step_video FROM {$wpdb->prefix}saw_visitors WHERE id = %d",
        $visitor_id
    ));
    $completed = !empty($visitor['training_step_video']);
}

$translations = [
    'cs' => [
        'title' => 'Školení BOZP',
        'subtitle' => 'Sledujte prosím celé video',
        'video_label' => 'Bezpečnost práce',
        'watched' => 'Video bylo shlédnuto',
        'mark_watched' => 'Potvrdit zhlédnutí',
        'continue' => 'Pokračovat',
        'required' => 'Sledování je povinné',
    ],
    'en' => [
        'title' => 'Safety Training',
        'subtitle' => 'Please watch the entire video',
        'video_label' => 'Occupational Safety',
        'watched' => 'Video has been watched',
        'mark_watched' => 'Confirm watched',
        'continue' => 'Continue',
        'required' => 'Watching is required',
    ],
    'uk' => [
        'title' => 'Навчання з охорони праці',
        'subtitle' => 'Будь ласка, перегляньте все відео',
        'video_label' => 'Безпека праці',
        'watched' => 'Відео переглянуто',
        'mark_watched' => 'Підтвердити перегляд',
        'continue' => 'Продовжити',
        'required' => 'Перегляд обов\'язковий',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];
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
        
        <!-- Progress indicator -->
        <div class="saw-terminal-progress" style="margin-bottom: 2rem;">
            <div class="saw-terminal-progress-step completed">1</div>
            <div class="saw-terminal-progress-step active">2</div>
            <div class="saw-terminal-progress-step">3</div>
            <div class="saw-terminal-progress-step">4</div>
            <div class="saw-terminal-progress-step">5</div>
        </div>
        
        <!-- Video container -->
        <div class="saw-training-video-container" style="margin-bottom: 2rem;">
            <div class="saw-training-video-wrapper" style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 12px; background: #000;">
                <iframe id="training-video"
                        src="<?php echo esc_url($video_url); ?>?enablejsapi=1"
                        style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen>
                </iframe>
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
                    class="saw-terminal-btn saw-terminal-btn-success"
                    id="continue-btn"
                    <?php echo !$completed ? 'disabled' : ''; ?>>
                <?php echo esc_html($t['continue']); ?> →
            </button>
        </form>
        
        <p style="margin-top: 1.5rem; text-align: center; color: #a0aec0; font-size: 0.875rem;">
            <strong>⚠️ <?php echo esc_html($t['required']); ?></strong>
        </p>
        
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    let videoWatched = <?php echo $completed ? 'true' : 'false'; ?>;
    let watchedPercentage = 0;
    
    // YouTube API
    let player;
    function onYouTubeIframeAPIReady() {
        player = new YT.Player('training-video', {
            events: {
                'onStateChange': onPlayerStateChange
            }
        });
    }
    
    function onPlayerStateChange(event) {
        if (event.data == YT.PlayerState.PLAYING) {
            startProgressTracking();
        }
    }
    
    // Track video progress
    function startProgressTracking() {
        setInterval(function() {
            if (player && player.getCurrentTime) {
                const current = player.getCurrentTime();
                const duration = player.getDuration();
                
                if (duration > 0) {
                    watchedPercentage = Math.floor((current / duration) * 100);
                    $('#video-progress-bar').css('width', watchedPercentage + '%');
                    $('#video-progress-text').text(watchedPercentage + '%');
                    
                    // Enable mark as watched button at 90%
                    if (watchedPercentage >= 90 && !videoWatched) {
                        $('#mark-watched-btn').prop('disabled', false);
                    }
                }
            }
        }, 1000);
    }
    
    // Load YouTube API
    if (!window.YT) {
        const tag = document.createElement('script');
        tag.src = "https://www.youtube.com/iframe_api";
        const firstScriptTag = document.getElementsByTagName('script')[0];
        firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
    }
    
    // Mark as watched
    $('#mark-watched-btn').on('click', function() {
        videoWatched = true;
        $('#video-watched-input').val('1');
        $('#continue-btn').prop('disabled', false);
        
        $(this).fadeOut(300, function() {
            $('<div style="background: #f0fdf4; border: 2px solid #86efac; border-radius: 12px; padding: 1.5rem; margin-bottom: 1rem; text-align: center;">' +
              '<p style="margin: 0; font-size: 1.125rem; color: #16a34a; font-weight: 600;">✅ <?php echo esc_js($t['watched']); ?></p>' +
              '</div>').insertBefore('#continue-btn').hide().fadeIn(300);
        });
    });
});
</script>
