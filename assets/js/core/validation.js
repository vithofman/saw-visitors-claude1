/**
 * SAW Validation & Formatting
 * 
 * Global validation logic for SAW Visitors plugin.
 * Handles email, phone, IČO, and other common field validations.
 * 
 * @package SAW_Visitors
 * @since 1.0.0
 */

(function($) {
    'use strict';

    const SAW_Validation = {
        
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Auto-bind to data attributes
            $(document).on('blur', '[data-validate="email"]', this.validateEmail);
            $(document).on('blur', '[data-validate="ico"]', this.validateICO);
            $(document).on('blur', '[data-format="phone"]', this.formatPhone);
            $(document).on('blur', '[data-format="zip"]', this.formatZip);
            $(document).on('blur', '[data-validate="url"]', this.validateURL);
            
            // Input restrictions
            $(document).on('input', '[data-restrict="digits"]', this.restrictDigits);
        },

        validateEmail: function() {
            const $input = $(this);
            const value = $input.val().trim();
            
            if (!value) return;

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                alert('Zadejte prosím platný email ve formátu: email@domena.cz');
                $input.focus();
            }
        },

        validateICO: function() {
            const $input = $(this);
            const value = $input.val().trim();
            
            if (!value) return;

            const cleanedValue = value.replace(/\s/g, '');
            
            if (!/^\d+$/.test(cleanedValue)) {
                alert('IČO musí obsahovat pouze číslice');
                $input.focus();
                return;
            }
            
            if (cleanedValue.length !== 8) {
                if (!confirm('IČO v ČR má obvykle 8 číslic. Chcete pokračovat s tímto IČO?')) {
                    $input.focus();
                }
            }
            
            $input.val(cleanedValue);
        },

        formatPhone: function() {
            const $input = $(this);
            let value = $input.val().trim();
            
            if (!value) return;

            value = value.replace(/[^\d+]/g, '');
            
            if (/^\d{9}$/.test(value)) {
                value = '+420' + value;
            }
            
            if (value.startsWith('+420') && value.length === 13) {
                value = '+420 ' + value.substring(4, 7) + ' ' + value.substring(7, 10) + ' ' + value.substring(10);
            }
            
            $input.val(value);
        },

        formatZip: function() {
            const $input = $(this);
            let value = $input.val().trim();
            
            if (!value) return;

            value = value.replace(/\s/g, '');
            
            if (/^\d{5}$/.test(value)) {
                value = value.substring(0, 3) + ' ' + value.substring(3);
                $input.val(value);
            }
        },

        validateURL: function() {
            const $input = $(this);
            let value = $input.val().trim();
            
            if (!value) return;
            
            if (!value.match(/^https?:\/\//i)) {
                value = 'https://' + value;
                $input.val(value);
            }
            
            try {
                new URL(value);
            } catch (e) {
                alert('Zadejte prosím platnou webovou adresu (např. https://www.firma.cz)');
                $input.focus();
            }
        },

        restrictDigits: function() {
            this.value = this.value.replace(/[^\d]/g, '');
        }
    };

    $(document).ready(function() {
        SAW_Validation.init();
    });

    // Expose globally
    window.SAW_Validation = SAW_Validation;

})(jQuery);
