/**
 * Companies Module Scripts
 * 
 * Handles client-side validation and interactions for the Companies module.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Companies
 * @since       1.0.0
 * @version     1.0.0
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // ================================================
        // EMAIL VALIDATION
        // ================================================
        
        /**
         * Validates email input field on blur
         * 
         * Ensures the email is in correct format if provided.
         * Empty email is allowed (optional field).
         * 
         * @since 1.0.0
         * @listens blur - Triggered when input loses focus
         */
        $('#email').on('blur', function() {
            const $input = $(this);
            const value = $input.val().trim();
            
            // Skip validation if empty (optional field)
            if (!value) {
                return;
            }
            
            // Email regex validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                alert('Zadejte prosím platný email ve formátu: email@domena.cz');
                $input.focus();
            }
        });
        
        // ================================================
        // WEBSITE URL VALIDATION
        // ================================================
        
        /**
         * Validates website URL input field on blur
         * 
         * Ensures the URL starts with http:// or https://.
         * Automatically adds https:// if missing.
         * 
         * @since 1.0.0
         * @listens blur - Triggered when input loses focus
         */
        $('#website').on('blur', function() {
            const $input = $(this);
            let value = $input.val().trim();
            
            // Skip validation if empty (optional field)
            if (!value) {
                return;
            }
            
            // Auto-prepend https:// if missing protocol
            if (!value.match(/^https?:\/\//i)) {
                value = 'https://' + value;
                $input.val(value);
            }
            
            // Validate URL format
            try {
                new URL(value);
            } catch (e) {
                alert('Zadejte prosím platnou webovou adresu (např. https://www.firma.cz)');
                $input.focus();
            }
        });
        
        // ================================================
        // IČO VALIDATION
        // ================================================
        
        /**
         * Validates IČO input field
         * 
         * Ensures IČO contains only digits and is 8 digits long (CZ standard).
         * Empty IČO is allowed (optional field).
         * 
         * @since 1.0.0
         * @listens blur - Triggered when input loses focus
         */
        $('#ico').on('blur', function() {
            const $input = $(this);
            const value = $input.val().trim();
            
            // Skip validation if empty (optional field)
            if (!value) {
                return;
            }
            
            // Remove spaces and validate format
            const cleanedValue = value.replace(/\s/g, '');
            
            // Check if contains only digits
            if (!/^\d+$/.test(cleanedValue)) {
                alert('IČO musí obsahovat pouze číslice');
                $input.focus();
                return;
            }
            
            // Check length (CZ IČO is 8 digits)
            if (cleanedValue.length !== 8) {
                // Warning but don't block (some countries may have different length)
                if (!confirm('IČO v ČR má obvykle 8 číslic. Chcete pokračovat s tímto IČO?')) {
                    $input.focus();
                }
            }
            
            // Update value without spaces
            $input.val(cleanedValue);
        });
        
        /**
         * Prevent non-numeric input in IČO field
         * 
         * @since 1.0.0
         * @listens keypress - Triggered on key press
         */
        $('#ico').on('keypress', function(e) {
            // Allow: backspace, delete, tab, escape, enter
            if ($.inArray(e.keyCode, [46, 8, 9, 27, 13]) !== -1 ||
                // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                (e.keyCode === 65 && e.ctrlKey === true) ||
                (e.keyCode === 67 && e.ctrlKey === true) ||
                (e.keyCode === 86 && e.ctrlKey === true) ||
                (e.keyCode === 88 && e.ctrlKey === true)) {
                return;
            }
            
            // Ensure that it is a number and stop the keypress
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && 
                (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });
        
        // ================================================
        // ZIP CODE FORMATTING
        // ================================================
        
        /**
         * Format Czech postal code (PSČ) on blur
         * 
         * Automatically formats as "XXX XX" for Czech postal codes.
         * 
         * @since 1.0.0
         * @listens blur - Triggered when input loses focus
         */
        $('#zip').on('blur', function() {
            const $input = $(this);
            let value = $input.val().trim();
            
            // Skip if empty
            if (!value) {
                return;
            }
            
            // Remove all spaces
            value = value.replace(/\s/g, '');
            
            // Format as "XXX XX" if 5 digits
            if (/^\d{5}$/.test(value)) {
                value = value.substring(0, 3) + ' ' + value.substring(3);
                $input.val(value);
            }
        });
        
        // ================================================
        // PHONE NUMBER FORMATTING
        // ================================================
        
        /**
         * Format phone number on blur
         * 
         * Basic formatting for Czech phone numbers.
         * Adds +420 prefix if missing and formats as "+420 XXX XXX XXX".
         * 
         * @since 1.0.0
         * @listens blur - Triggered when input loses focus
         */
        $('#phone').on('blur', function() {
            const $input = $(this);
            let value = $input.val().trim();
            
            // Skip if empty
            if (!value) {
                return;
            }
            
            // Remove all spaces and non-digits except +
            value = value.replace(/[^\d+]/g, '');
            
            // Auto-add +420 for Czech numbers if not present
            if (/^\d{9}$/.test(value)) {
                value = '+420' + value;
            }
            
            // Format Czech numbers as +420 XXX XXX XXX
            if (value.startsWith('+420') && value.length === 13) {
                value = '+420 ' + value.substring(4, 7) + ' ' + value.substring(7, 10) + ' ' + value.substring(10);
            }
            
            $input.val(value);
        });
        
    });
    
})(jQuery);
