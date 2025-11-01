/**
 * Color Picker Component Scripts
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

(function($) {
    'use strict';
    
    class SAWColorPicker {
        constructor($component) {
            this.$component = $component;
            this.$colorInput = $component.find('.saw-color-picker');
            this.$valueInput = $component.find('.saw-color-value');
            this.$previewBadge = $component.find('.saw-badge');
            
            this.targetId = this.$colorInput.data('target-id');
            this.$externalTarget = this.targetId ? $('#' + this.targetId) : null;
            
            this.init();
        }
        
        init() {
            this.bindEvents();
        }
        
        bindEvents() {
            // Color picker change
            this.$colorInput.on('input', (e) => {
                this.handleColorChange(e.target.value);
            });
        }
        
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
    
    // Initialize
    $(document).ready(function() {
        $('.saw-color-picker-component').each(function() {
            new SAWColorPicker($(this));
        });
    });
    
})(jQuery);
