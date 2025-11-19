/**
 * SAW Video Player
 * 
 * YouTube/Vimeo player wrapper with progress tracking
 * Features: progress tracking, 90% completion threshold, callbacks
 * 
 * @package SAW_Visitors
 * @version 3.0.0
 * @since   3.0.0
 * 
 * Usage:
 * const player = new SAWVideoPlayer({
 *     videoUrl: 'https://www.youtube.com/embed/...',
 *     containerId: 'video-player',
 *     onProgress: function(percent) { console.log(percent); },
 *     onComplete: function() { console.log('Completed 90%'); }
 * });
 */

(function(window) {
    'use strict';
    
    /**
     * Video Player Class
     * 
     * @param {Object} options Configuration options
     */
    function SAWVideoPlayer(options) {
        // Validate options
        if (!options || !options.videoUrl) {
            console.error('[SAW Video Player] Error: videoUrl is required');
            return;
        }
        
        // Configuration
        this.videoUrl = options.videoUrl;
        this.containerId = options.containerId || 'video-player';
        this.onProgress = options.onProgress || null;
        this.onComplete = options.onComplete || null;
        this.completionThreshold = options.completionThreshold || 90; // 90% default
        this.debug = options.debug || false;
        
        // State
        this.player = null;
        this.playerType = null; // 'youtube' or 'vimeo'
        this.duration = 0;
        this.currentTime = 0;
        this.maxProgress = 0; // Track highest progress reached
        this.completed = false;
        this.updateInterval = null;
        
        // Anti-cheat: Track actual watched seconds
        this.watchedSegments = []; // Array of [start, end] time segments
        this.lastRecordedTime = 0;
        this.totalWatchedSeconds = 0;
        
        // Initialize
        this.init();
    }
    
    /**
     * Initialize player
     */
    SAWVideoPlayer.prototype.init = function() {
        // Detect player type
        if (this.videoUrl.indexOf('youtube.com') !== -1 || this.videoUrl.indexOf('youtu.be') !== -1) {
            this.playerType = 'youtube';
            this.initYouTube();
        } else if (this.videoUrl.indexOf('vimeo.com') !== -1) {
            this.playerType = 'vimeo';
            this.initVimeo();
        } else {
            console.error('[SAW Video Player] Unsupported video URL');
            return;
        }
        
        if (this.debug) {
            console.log('[SAW Video Player] Initialized:', this.playerType);
        }
    };
    
    /**
     * Initialize YouTube player
     */
    SAWVideoPlayer.prototype.initYouTube = function() {
        var self = this;
        
        // Load YouTube IFrame API
        if (typeof YT === 'undefined' || typeof YT.Player === 'undefined') {
            // Load API
            var tag = document.createElement('script');
            tag.src = 'https://www.youtube.com/iframe_api';
            var firstScriptTag = document.getElementsByTagName('script')[0];
            firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
            
            // Wait for API to load
            window.onYouTubeIframeAPIReady = function() {
                self.createYouTubePlayer();
            };
        } else {
            // API already loaded
            this.createYouTubePlayer();
        }
    };
    
    /**
     * Create YouTube player instance
     */
    SAWVideoPlayer.prototype.createYouTubePlayer = function() {
        var self = this;
        
        // Extract video ID
        var videoId = this.extractYouTubeId(this.videoUrl);
        if (!videoId) {
            console.error('[SAW Video Player] Invalid YouTube URL');
            return;
        }
        
        // Create player
        this.player = new YT.Player(this.containerId, {
            videoId: videoId,
            width: '100%',
            height: '100%',
            playerVars: {
                autoplay: 0,
                controls: 1,
                rel: 0,
                modestbranding: 1
            },
            events: {
                onReady: function(event) {
                    self.duration = event.target.getDuration();
                    if (self.debug) {
                        console.log('[SAW Video Player] YouTube ready, duration:', self.duration);
                    }
                },
                onStateChange: function(event) {
                    if (event.data === YT.PlayerState.PLAYING) {
                        self.startTracking();
                    } else if (event.data === YT.PlayerState.PAUSED || event.data === YT.PlayerState.ENDED) {
                        self.stopTracking();
                    }
                }
            }
        });
    };
    
    /**
     * Extract YouTube video ID from URL
     */
    SAWVideoPlayer.prototype.extractYouTubeId = function(url) {
        var match = url.match(/(?:youtube\.com\/embed\/|youtube\.com\/watch\?v=|youtu\.be\/)([^&?\/]+)/);
        return match ? match[1] : null;
    };
    
    /**
     * Initialize Vimeo player
     */
    SAWVideoPlayer.prototype.initVimeo = function() {
        var self = this;
        
        // Load Vimeo Player API
        if (typeof Vimeo === 'undefined' || typeof Vimeo.Player === 'undefined') {
            var script = document.createElement('script');
            script.src = 'https://player.vimeo.com/api/player.js';
            script.onload = function() {
                self.createVimeoPlayer();
            };
            document.head.appendChild(script);
        } else {
            this.createVimeoPlayer();
        }
    };
    
    /**
     * Create Vimeo player instance
     */
    SAWVideoPlayer.prototype.createVimeoPlayer = function() {
        var self = this;
        var container = document.getElementById(this.containerId);
        
        // Create iframe
        var iframe = document.createElement('iframe');
        iframe.src = this.videoUrl + '?title=0&byline=0&portrait=0';
        iframe.width = '100%';
        iframe.height = '100%';
        iframe.frameBorder = '0';
        iframe.allow = 'autoplay; fullscreen; picture-in-picture';
        container.appendChild(iframe);
        
        // Create player
        this.player = new Vimeo.Player(iframe);
        
        this.player.getDuration().then(function(duration) {
            self.duration = duration;
            if (self.debug) {
                console.log('[SAW Video Player] Vimeo ready, duration:', self.duration);
            }
        });
        
        this.player.on('play', function() {
            self.startTracking();
        });
        
        this.player.on('pause', function() {
            self.stopTracking();
        });
        
        this.player.on('ended', function() {
            self.stopTracking();
        });
    };
    
    /**
     * Start progress tracking
     */
    SAWVideoPlayer.prototype.startTracking = function() {
        var self = this;
        
        if (this.updateInterval) return;
        
        this.updateInterval = setInterval(function() {
            self.updateProgress();
        }, 1000); // Update every second
        
        if (this.debug) {
            console.log('[SAW Video Player] Tracking started');
        }
    };
    
    /**
     * Stop progress tracking
     */
    SAWVideoPlayer.prototype.stopTracking = function() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
            this.updateInterval = null;
        }
        
        if (this.debug) {
            console.log('[SAW Video Player] Tracking stopped');
        }
    };
    
    /**
     * Update progress
     */
    SAWVideoPlayer.prototype.updateProgress = function() {
        var self = this;
        
        if (this.playerType === 'youtube') {
            this.currentTime = this.player.getCurrentTime();
        } else if (this.playerType === 'vimeo') {
            this.player.getCurrentTime().then(function(time) {
                self.currentTime = time;
                self.processProgress();
            });
            return; // Exit early for async Vimeo
        }
        
        this.processProgress();
    };
    
    /**
     * Process progress and trigger callbacks
     */
    SAWVideoPlayer.prototype.processProgress = function() {
        if (this.duration === 0) return;
        
        // Track watched segments (anti-cheat)
        var timeDiff = Math.abs(this.currentTime - this.lastRecordedTime);
        
        // If time jump is small (< 3 seconds), count as continuous watching
        if (timeDiff < 3 && this.lastRecordedTime > 0) {
            this.totalWatchedSeconds += 1; // Add 1 second
        }
        // If big jump (seeking), don't count it
        
        this.lastRecordedTime = this.currentTime;
        
        // Calculate progress based on WATCHED seconds, not position
        var percent = Math.min(100, Math.round((this.totalWatchedSeconds / this.duration) * 100));
        
        // Track max progress
        if (percent > this.maxProgress) {
            this.maxProgress = percent;
            
            // Trigger progress callback
            if (typeof this.onProgress === 'function') {
                this.onProgress(this.maxProgress);
            }
            
            if (this.debug) {
                console.log('[SAW Video Player] Progress:', this.maxProgress + '% (' + this.totalWatchedSeconds + 's watched)');
            }
        }
        
        // Check completion
        if (!this.completed && this.maxProgress >= this.completionThreshold) {
            this.completed = true;
            
            if (typeof this.onComplete === 'function') {
                this.onComplete({
                    progress: this.maxProgress,
                    threshold: this.completionThreshold,
                    watchedSeconds: this.totalWatchedSeconds,
                    totalDuration: this.duration
                });
            }
            
            if (this.debug) {
                console.log('[SAW Video Player] Completed! (' + this.maxProgress + '%, watched ' + this.totalWatchedSeconds + 's / ' + this.duration + 's)');
            }
        }
    };
    
    /**
     * Destroy player
     */
    SAWVideoPlayer.prototype.destroy = function() {
        this.stopTracking();
        
        if (this.player) {
            if (this.playerType === 'youtube' && this.player.destroy) {
                this.player.destroy();
            }
        }
        
        if (this.debug) {
            console.log('[SAW Video Player] Destroyed');
        }
    };
    
    // Export to global scope
    window.SAWVideoPlayer = SAWVideoPlayer;
    
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = SAWVideoPlayer;
    }
    
})(window);

/**
 * Usage Example:
 * 
 * <div id="video-player"></div>
 * 
 * <script>
 * const player = new SAWVideoPlayer({
 *     videoUrl: 'https://www.youtube.com/embed/dQw4w9WgXcQ',
 *     containerId: 'video-player',
 *     completionThreshold: 90,
 *     debug: true,
 *     onProgress: function(percent) {
 *         console.log('Progress:', percent + '%');
 *         document.getElementById('progress').textContent = percent + '%';
 *     },
 *     onComplete: function(data) {
 *         console.log('Video completed!');
 *         document.getElementById('continue-btn').disabled = false;
 *     }
 * });
 * </script>
 */
