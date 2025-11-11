/**
 * Branches Module Scripts
 * 
 * Module-specific JavaScript for the Branches module.
 * Handles client-side validation and auto-formatting.
 * 
 * Features:
 * - PSČ (Postal Code) auto-formatting (adds space after 3rd digit)
 * - Phone number validation
 * - Real-time input validation
 * 
 * @package SAW_Visitors
 * @version 13.4.0
 * @since   13.0.0
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        initZipFormatting();
        initPhoneValidation();
    });
    
    /**
     * Initialize PSČ (Postal Code) auto-formatting
     * 
     * Automatically formats 5-digit postal codes by adding space after 3rd digit.
     * Example: "12345" becomes "123 45"
     * 
     * @since 13.0.0
     * @return {void}
     */
    function initZipFormatting() {
        const $zipInputs = $('input[name="postal_code"]');
        
        $zipInputs.on('blur', function() {
            const $input = $(this);
            let zip = $input.val().replace(/\s/g, ''); // Remove all spaces
            
            // Format if exactly 5 digits
            if (zip.length === 5 && /^\d{5}$/.test(zip)) {
                $input.val(zip.slice(0, 3) + ' ' + zip.slice(3));
            }
        });
    }
    
    /**
     * Initialize phone number validation
     * 
     * Basic validation for phone numbers.
     * 
     * @since 13.0.0
     * @return {void}
     */
    function initPhoneValidation() {
        $('#phone').on('blur', function() {
            const $input = $(this);
            const phone = $input.val().trim();
            
            // Skip validation if empty
            if (!phone) {
                clearInputError($input);
                return;
            }
            
            // Basic validation: at least 9 digits
            const digitsOnly = phone.replace(/\D/g, '');
            if (digitsOnly.length < 9) {
                showInputError($input, 'Telefon musí obsahovat minimálně 9 číslic!');
                return;
            }
            
            // Valid
            clearInputError($input);
        });
        
        // Clear error on input
        $('#phone').on('input', function() {
            clearInputError($(this));
        });
    }
    
    /**
     * Show input error message
     * 
     * Displays error message below input field and adds error styling.
     * Creates error element if it doesn't exist.
     * 
     * @since 13.0.0
     * @param {jQuery} $input Input element
     * @param {string} message Error message
     * @return {void}
     */
    function showInputError($input, message) {
        // Add error class to input
        $input.addClass('saw-input-error');
        
        // Remove existing error message
        $input.siblings('.saw-input-error-message').remove();
        
        // Create and insert error message
        const $error = $('<div class="saw-input-error-message"></div>').text(message);
        $input.after($error);
        
        // Focus input
        $input.focus();
    }
    
    /**
     * Clear input error message
     * 
     * Removes error styling and error message from input field.
     * 
     * @since 13.0.0
     * @param {jQuery} $input Input element
     * @return {void}
     */
    function clearInputError($input) {
        $input.removeClass('saw-input-error');
        $input.siblings('.saw-input-error-message').remove();
    }
    
})(jQuery);