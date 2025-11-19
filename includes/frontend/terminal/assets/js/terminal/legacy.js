/**
 * SAW Terminal JavaScript
 * 
 * Interactive behaviors for visitor terminal
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

(function($) {
    'use strict';
    
    /**
     * Terminal Object
     */
    const SAW_Terminal = {
        
        /**
         * Current PIN value
         */
        pin: '',
        
        /**
         * Maximum PIN length
         */
        maxPinLength: 6,
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initNumpad();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Prevent double-tap zoom on buttons
            $('.saw-terminal-btn').on('touchend', function(e) {
                e.preventDefault();
                $(this).trigger('click');
            });
            
            // Auto-submit forms on Enter key (except textareas)
            $('.saw-terminal-form').on('keypress', 'input:not([type="checkbox"])', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $(this).closest('form').find('button[type="submit"]').trigger('click');
                }
            });
            
            // Visitor selection checkboxes
            $('.saw-terminal-visitor-item').on('click', function() {
                const checkbox = $(this).find('.saw-terminal-visitor-checkbox');
                checkbox.prop('checked', !checkbox.prop('checked'));
                $(this).toggleClass('selected', checkbox.prop('checked'));
            });
            
            // Prevent checkbox label click from double-toggling
            $('.saw-terminal-visitor-checkbox').on('click', function(e) {
                e.stopPropagation();
            });
        },
        
        /**
         * Initialize numeric keypad
         */
        initNumpad: function() {
            const self = this;
            
            // Number buttons
            $('.saw-terminal-numpad-btn[data-value]').on('click', function() {
                const value = $(this).data('value');
                self.addPinDigit(value);
            });
            
            // Backspace button
            $('.saw-terminal-numpad-btn.backspace').on('click', function() {
                self.removePinDigit();
            });
            
            // Clear button
            $('.saw-terminal-numpad-btn.clear').on('click', function() {
                self.clearPin();
            });
        },
        
        /**
         * Add digit to PIN
         */
        addPinDigit: function(digit) {
            if (this.pin.length < this.maxPinLength) {
                this.pin += digit;
                this.updatePinDisplay();
                
                // Auto-submit when reaching max length
                if (this.pin.length === this.maxPinLength) {
                    setTimeout(() => {
                        this.submitPin();
                    }, 300);
                }
            }
        },
        
        /**
         * Remove last digit from PIN
         */
        removePinDigit: function() {
            this.pin = this.pin.slice(0, -1);
            this.updatePinDisplay();
        },
        
        /**
         * Clear entire PIN
         */
        clearPin: function() {
            this.pin = '';
            this.updatePinDisplay();
        },
        
        /**
         * Update PIN display
         */
        updatePinDisplay: function() {
            // Update hidden input
            $('#pin-input').val(this.pin);
            
            // Update visual dots
            $('.saw-terminal-pin-dot').each((index, el) => {
                $(el).toggleClass('filled', index < this.pin.length);
            });
            
            // Update text display (if exists)
            $('.saw-terminal-pin-text').text(this.pin || '______');
        },
        
        /**
         * Submit PIN form
         */
        submitPin: function() {
            if (this.pin.length === this.maxPinLength) {
                $('#pin-form').submit();
            }
        },
        
        /**
         * Show loading state
         */
        showLoading: function(button) {
            const $btn = $(button);
            $btn.prop('disabled', true);
            $btn.data('original-text', $btn.html());
            $btn.html('<span style="font-size: 1.5rem;">⏳</span> Zpracovávám...');
        },
        
        /**
         * Hide loading state
         */
        hideLoading: function(button) {
            const $btn = $(button);
            $btn.prop('disabled', false);
            $btn.html($btn.data('original-text'));
        },
        
        /**
         * Validate form before submit
         */
        validateForm: function(form) {
            let isValid = true;
            const $form = $(form);
            
            // Clear previous errors
            $form.find('.saw-terminal-form-error').remove();
            
            // Check required fields
            $form.find('input[required], select[required], textarea[required]').each(function() {
                const $field = $(this);
                const value = $field.val().trim();
                
                if (!value) {
                    isValid = false;
                    $field.addClass('error');
                    $field.after('<div class="saw-terminal-form-error">Toto pole je povinné</div>');
                } else {
                    $field.removeClass('error');
                }
            });
            
            // Check email format
            $form.find('input[type="email"]').each(function() {
                const $field = $(this);
                const value = $field.val().trim();
                
                if (value && !self.isValidEmail(value)) {
                    isValid = false;
                    $field.addClass('error');
                    $field.after('<div class="saw-terminal-form-error">Neplatný formát emailu</div>');
                }
            });
            
            return isValid;
        },
        
        /**
         * Check if email is valid
         */
        isValidEmail: function(email) {
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },
        
        /**
         * Auto-redirect after success
         */
        autoRedirect: function(url, delay) {
            delay = delay || 3000;
            setTimeout(() => {
                window.location.href = url;
            }, delay);
        }
    };
    
    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        SAW_Terminal.init();
        
        // Auto-hide success messages and redirect
        if ($('.saw-terminal-success').length) {
            SAW_Terminal.autoRedirect($('.saw-terminal-btn').first().attr('href'), 5000);
        }
    });
    
    /**
     * Expose to global scope
     */
    window.SAW_Terminal = SAW_Terminal;
    
})(jQuery);
