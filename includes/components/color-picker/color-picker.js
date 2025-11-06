/**
 * Color Picker Component Scripts
 *
 * Handles color picker input synchronization with text input and preview elements.
 * Updates color values in real-time and supports external target elements.
 *
 * @package SAW_Visitors
 * @since   1.0.0
 * @version 1.0.0
 */

(function($) {
    'use strict';
    
    /**
     * SAW Color Picker Class
     *
     * Manages color picker component with live preview and external target sync.
     *
     * @since 1.0.0
     */
    class SAWColorPicker {
        
        /**
         * Constructor
         *
         * Initializes color picker component with DOM references.
         *
         * @since 1.0.0
         * @param {jQuery} $component Color picker component wrapper element
         */
        constructor($component) {
            this.$component = $component;
            this.$colorInput = $component.find('.saw-color-picker');
            this.$valueInput = $component.find('.saw-color-value');
            this.$previewBadge = $component.find('.saw-badge');
            
            this.targetId = this.$colorInput.data('target-id');
            this.$externalTarget = this.targetId ? $('#' + this.targetId) : null;
            
            this.init();
        }
        
        /**
         * Initialize component
         *
         * Sets up event listeners for color input changes.
         *
         * @since 1.0.0
         * @return {void}
         */
        init() {
            this.bindEvents();
        }
        
        /**
         * Bind event listeners
         *
         * Attaches input event handler to color picker element.
         *
         * @since 1.0.0
         * @return {void}
         */
        bindEvents() {
            // Color picker change
            this.$colorInput.on('input', (e) => {
                this.handleColorChange(e.target.value);
            });
        }
        
        /**
         * Handle color change
         *
         * Updates all related elements when color is changed.
         * Note: Uses inline styles for dynamic color values (legitimate use case).
         *
         * @since 1.0.0
         * @param {string} color Hex color value from color picker
         * @return {void}
         */
        handleColorChange(color) {
            const upperColor = color.toUpperCase();
            
            // Update value input
            this.$valueInput.val(upperColor);
            
            // Update preview badge if exists
            if (this.$previewBadge.length) {
                this.$previewBadge.css('background-color', color);
            }
            
            // Update external target if specified
            if (this.$externalTarget && this.$externalTarget.length) {
                this.$externalTarget.css('background-color', color);
            }
        }
    }
    
    /**
     * Initialize all color picker components on page
     *
     * @since 1.0.0
     */
    $(document).ready(function() {
        $('.saw-color-picker-component').each(function() {
            new SAWColorPicker($(this));
        });
    });
    
})(jQuery);