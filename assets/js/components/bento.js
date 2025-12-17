/**
 * SAW Bento - JavaScript Interactions
 * 
 * Handles interactive behaviors for Bento components:
 * - Text expand/collapse
 * - Copy to clipboard
 * - Card animations
 * 
 * @version 1.0.0
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    /**
     * Bento Module
     */
    const SAWBento = {
        
        /**
         * Initialize Bento interactions
         */
        init: function() {
            this.bindTextToggle();
            this.bindCopyButtons();
            this.observeNewCards();
        },
        
        /**
         * Bind text expand/collapse toggle
         */
        bindTextToggle: function() {
            $(document).on('click', '.bento-text-toggle', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const $card = $button.closest('.bento-text');
                const $content = $card.find('.bento-text-content');
                const isExpanded = $card.attr('data-expanded') === 'true';
                
                if (isExpanded) {
                    // Collapse
                    $card.attr('data-expanded', 'false');
                    $content.css('max-height', $content.data('original-height') || '200px');
                } else {
                    // Expand
                    if (!$content.data('original-height')) {
                        $content.data('original-height', $content.css('max-height'));
                    }
                    $card.attr('data-expanded', 'true');
                    $content.css('max-height', $content[0].scrollHeight + 'px');
                }
            });
        },
        
        /**
         * Bind copy to clipboard buttons
         */
        bindCopyButtons: function() {
            $(document).on('click', '.bento-copy-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const $button = $(this);
                const textToCopy = $button.data('copy');
                
                if (!textToCopy) return;
                
                // Use modern clipboard API if available
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(textToCopy).then(function() {
                        SAWBento.showCopyFeedback($button);
                    }).catch(function() {
                        SAWBento.fallbackCopy(textToCopy, $button);
                    });
                } else {
                    SAWBento.fallbackCopy(textToCopy, $button);
                }
            });
        },
        
        /**
         * Fallback copy method for older browsers
         */
        fallbackCopy: function(text, $button) {
            const $temp = $('<textarea>');
            $temp.val(text).css({
                position: 'fixed',
                left: '-9999px'
            }).appendTo('body');
            
            $temp[0].select();
            
            try {
                document.execCommand('copy');
                SAWBento.showCopyFeedback($button);
            } catch (err) {
                console.error('Copy failed:', err);
            }
            
            $temp.remove();
        },
        
        /**
         * Show copy success feedback
         */
        showCopyFeedback: function($button) {
            $button.addClass('copied');
            
            setTimeout(function() {
                $button.removeClass('copied');
            }, 2000);
        },
        
        /**
         * Observe new cards being added (for AJAX content)
         */
        observeNewCards: function() {
            // Re-initialize animations when sidebar content changes
            $(document).on('saw:sidebar:content-loaded', function() {
                SAWBento.animateCards();
            });
        },
        
        /**
         * Trigger card entry animations
         */
        animateCards: function() {
            const $cards = $('.bento-card');
            
            $cards.each(function(index) {
                const $card = $(this);
                
                // Reset animation
                $card.css({
                    opacity: 0,
                    animation: 'none'
                });
                
                // Trigger reflow
                $card[0].offsetHeight;
                
                // Apply animation with delay
                $card.css({
                    animation: 'bento-fade-in 0.4s ease-out forwards',
                    animationDelay: (index * 50) + 'ms'
                });
            });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        SAWBento.init();
    });
    
    // Expose globally
    window.SAWBento = SAWBento;
    
})(jQuery);

