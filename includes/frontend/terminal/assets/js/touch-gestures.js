/**
 * SAW Touch Gestures Handler
 * 
 * Reusable touch gesture detection library
 * Detects: swipe (left/right/up/down), tap, long press
 * 
 * @package SAW_Visitors
 * @version 3.0.0
 * @since   3.0.0
 * 
 * Usage:
 * const gestures = new SAWTouchGestures(element, {
 *     onSwipeLeft: () => console.log('Swiped left'),
 *     onSwipeRight: () => console.log('Swiped right'),
 *     swipeThreshold: 50
 * });
 */

(function(window) {
    'use strict';
    
    /**
     * Touch Gesture Handler Class
     * 
     * @param {HTMLElement} element Target element for gesture detection
     * @param {Object} options Configuration options and callbacks
     */
    function SAWTouchGestures(element, options) {
        // Validate element
        if (!element || !(element instanceof HTMLElement)) {
            console.error('[SAW Touch Gestures] Invalid element provided');
            return;
        }
        
        this.element = element;
        this.options = Object.assign({}, this.defaults, options || {});
        
        // State
        this.touchStartX = 0;
        this.touchStartY = 0;
        this.touchEndX = 0;
        this.touchEndY = 0;
        this.touchStartTime = 0;
        this.isSwiping = false;
        this.isScrolling = false;
        
        // Bind methods
        this.handleTouchStart = this.handleTouchStart.bind(this);
        this.handleTouchMove = this.handleTouchMove.bind(this);
        this.handleTouchEnd = this.handleTouchEnd.bind(this);
        
        // Initialize
        this.init();
    }
    
    /**
     * Default options
     */
    SAWTouchGestures.prototype.defaults = {
        // Thresholds
        swipeThreshold: 50,        // Minimum distance for swipe (px)
        tapThreshold: 10,          // Maximum distance for tap (px)
        longPressThreshold: 500,   // Minimum time for long press (ms)
        velocityThreshold: 0.3,    // Minimum velocity for swipe
        
        // Callbacks
        onSwipeLeft: null,
        onSwipeRight: null,
        onSwipeUp: null,
        onSwipeDown: null,
        onTap: null,
        onLongPress: null,
        
        // Options
        preventScroll: false,      // Prevent scroll during horizontal swipe
        debug: false               // Enable debug logging
    };
    
    /**
     * Initialize gesture detection
     */
    SAWTouchGestures.prototype.init = function() {
        // Add touch event listeners
        this.element.addEventListener('touchstart', this.handleTouchStart, { passive: false });
        this.element.addEventListener('touchmove', this.handleTouchMove, { passive: false });
        this.element.addEventListener('touchend', this.handleTouchEnd, { passive: false });
        
        if (this.options.debug) {
            console.log('[SAW Touch Gestures] Initialized on element:', this.element);
        }
    };
    
    /**
     * Handle touch start
     * 
     * @param {TouchEvent} event Touch start event
     */
    SAWTouchGestures.prototype.handleTouchStart = function(event) {
        const touch = event.touches[0];
        
        this.touchStartX = touch.clientX;
        this.touchStartY = touch.clientY;
        this.touchStartTime = Date.now();
        this.isSwiping = false;
        this.isScrolling = false;
        
        if (this.options.debug) {
            console.log('[SAW Touch Gestures] Touch start:', {
                x: this.touchStartX,
                y: this.touchStartY
            });
        }
    };
    
    /**
     * Handle touch move
     * 
     * @param {TouchEvent} event Touch move event
     */
    SAWTouchGestures.prototype.handleTouchMove = function(event) {
        if (this.isScrolling) return;
        
        const touch = event.touches[0];
        const moveX = Math.abs(touch.clientX - this.touchStartX);
        const moveY = Math.abs(touch.clientY - this.touchStartY);
        
        // Determine if horizontal or vertical movement
        if (!this.isSwiping && (moveX > 10 || moveY > 10)) {
            if (moveX > moveY) {
                // Horizontal swipe
                this.isSwiping = true;
                
                // Prevent scroll if option enabled
                if (this.options.preventScroll) {
                    event.preventDefault();
                }
                
                if (this.options.debug) {
                    console.log('[SAW Touch Gestures] Horizontal swipe detected');
                }
            } else {
                // Vertical scroll
                this.isScrolling = true;
                
                if (this.options.debug) {
                    console.log('[SAW Touch Gestures] Vertical scroll detected');
                }
            }
        }
        
        // Continue preventing scroll during horizontal swipe
        if (this.isSwiping && this.options.preventScroll) {
            event.preventDefault();
        }
    };
    
    /**
     * Handle touch end
     * 
     * @param {TouchEvent} event Touch end event
     */
    SAWTouchGestures.prototype.handleTouchEnd = function(event) {
        const touch = event.changedTouches[0];
        
        this.touchEndX = touch.clientX;
        this.touchEndY = touch.clientY;
        
        const touchEndTime = Date.now();
        const touchDuration = touchEndTime - this.touchStartTime;
        
        if (this.options.debug) {
            console.log('[SAW Touch Gestures] Touch end:', {
                x: this.touchEndX,
                y: this.touchEndY,
                duration: touchDuration
            });
        }
        
        // Detect gesture type
        this.detectGesture(touchDuration);
        
        // Reset state
        this.isSwiping = false;
        this.isScrolling = false;
    };
    
    /**
     * Detect gesture type based on touch data
     * 
     * @param {number} duration Touch duration in milliseconds
     */
    SAWTouchGestures.prototype.detectGesture = function(duration) {
        const deltaX = this.touchStartX - this.touchEndX;
        const deltaY = this.touchStartY - this.touchEndY;
        
        const absX = Math.abs(deltaX);
        const absY = Math.abs(deltaY);
        
        const velocity = absX / duration; // pixels per millisecond
        
        if (this.options.debug) {
            console.log('[SAW Touch Gestures] Gesture data:', {
                deltaX: deltaX,
                deltaY: deltaY,
                absX: absX,
                absY: absY,
                velocity: velocity,
                duration: duration
            });
        }
        
        // Check for swipe horizontal
        if (absX > this.options.swipeThreshold && absX > absY) {
            if (velocity >= this.options.velocityThreshold) {
                if (deltaX > 0) {
                    // Swipe left
                    this.trigger('onSwipeLeft', { deltaX: deltaX, velocity: velocity });
                } else {
                    // Swipe right
                    this.trigger('onSwipeRight', { deltaX: deltaX, velocity: velocity });
                }
            }
        }
        // Check for swipe vertical
        else if (absY > this.options.swipeThreshold && absY > absX) {
            if (velocity >= this.options.velocityThreshold) {
                if (deltaY > 0) {
                    // Swipe up
                    this.trigger('onSwipeUp', { deltaY: deltaY, velocity: velocity });
                } else {
                    // Swipe down
                    this.trigger('onSwipeDown', { deltaY: deltaY, velocity: velocity });
                }
            }
        }
        // Check for tap
        else if (absX < this.options.tapThreshold && absY < this.options.tapThreshold) {
            if (duration < this.options.longPressThreshold) {
                // Tap
                this.trigger('onTap', { x: this.touchEndX, y: this.touchEndY });
            } else {
                // Long press
                this.trigger('onLongPress', { x: this.touchEndX, y: this.touchEndY, duration: duration });
            }
        }
    };
    
    /**
     * Trigger callback
     * 
     * @param {string} callbackName Name of the callback option
     * @param {Object} data Event data to pass to callback
     */
    SAWTouchGestures.prototype.trigger = function(callbackName, data) {
        if (typeof this.options[callbackName] === 'function') {
            if (this.options.debug) {
                console.log('[SAW Touch Gestures] Triggering:', callbackName, data);
            }
            
            this.options[callbackName](data);
        }
    };
    
    /**
     * Destroy gesture handler
     * Remove all event listeners
     */
    SAWTouchGestures.prototype.destroy = function() {
        this.element.removeEventListener('touchstart', this.handleTouchStart);
        this.element.removeEventListener('touchmove', this.handleTouchMove);
        this.element.removeEventListener('touchend', this.handleTouchEnd);
        
        if (this.options.debug) {
            console.log('[SAW Touch Gestures] Destroyed');
        }
    };
    
    /**
     * Update options
     * 
     * @param {Object} newOptions New options to merge
     */
    SAWTouchGestures.prototype.updateOptions = function(newOptions) {
        this.options = Object.assign({}, this.options, newOptions || {});
        
        if (this.options.debug) {
            console.log('[SAW Touch Gestures] Options updated:', this.options);
        }
    };
    
    // Export to global scope
    window.SAWTouchGestures = SAWTouchGestures;
    
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = SAWTouchGestures;
    }
    
})(window);

/**
 * Usage Examples:
 * 
 * // Basic usage
 * const canvas = document.getElementById('pdf-canvas');
 * const gestures = new SAWTouchGestures(canvas, {
 *     onSwipeLeft: function() {
 *         console.log('Next page');
 *     },
 *     onSwipeRight: function() {
 *         console.log('Previous page');
 *     }
 * });
 * 
 * // With options
 * const gestures = new SAWTouchGestures(element, {
 *     swipeThreshold: 75,
 *     preventScroll: true,
 *     debug: true,
 *     onSwipeLeft: function(data) {
 *         console.log('Swiped left with velocity:', data.velocity);
 *     },
 *     onTap: function(data) {
 *         console.log('Tapped at:', data.x, data.y);
 *     }
 * });
 * 
 * // Update options later
 * gestures.updateOptions({
 *     swipeThreshold: 100
 * });
 * 
 * // Destroy when done
 * gestures.destroy();
 */
