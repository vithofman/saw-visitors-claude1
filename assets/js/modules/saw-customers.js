/**
 * Customers Module Scripts
 * 
 * Module-specific JavaScript for the Customers module.
 * Handles client-side validation and auto-formatting for Czech business data.
 * 
 * Features:
 * - IČO (Company ID) validation (8 digits required)
 * - PSČ (Postal Code) auto-formatting (adds space after 3rd digit)
 * - Real-time input validation
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 * @since   4.6.1
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
        initIcoValidation();
        initZipFormatting();
    });

    /**
     * Initialize IČO (Company ID) validation
     * 
     * Validates that IČO is exactly 8 digits.
     * Shows error message if validation fails.
     * 
     * @since 2.0.0
     * @return {void}
     */
    function initIcoValidation() {
        $('#ico').on('blur', function () {
            const $input = $(this);
            const ico = $input.val().trim();

            // Skip validation if empty
            if (!ico) {
                clearInputError($input);
                return;
            }

            // Validate format: exactly 8 digits
            if (!/^\d{8}$/.test(ico)) {
                showInputError($input, 'IČO musí být 8 číslic!');
                return;
            }

            // Valid
            clearInputError($input);
        });

        // Clear error on input
        $('#ico').on('input', function () {
            clearInputError($(this));
        });
    }

    /**
     * Initialize PSČ (Postal Code) auto-formatting
     * 
     * Automatically formats 5-digit postal codes by adding space after 3rd digit.
     * Example: "12345" becomes "123 45"
     * 
     * @since 2.0.0
     * @return {void}
     */
    function initZipFormatting() {
        const $zipInputs = $('input[name="address_zip"], input[name="billing_address_zip"]');

        $zipInputs.on('blur', function () {
            const $input = $(this);
            let zip = $input.val().replace(/\s/g, ''); // Remove all spaces

            // Format if exactly 5 digits
            if (zip.length === 5 && /^\d{5}$/.test(zip)) {
                $input.val(zip.slice(0, 3) + ' ' + zip.slice(3));
            }
        });
    }

    /**
     * Show input error message
     * 
     * Displays error message below input field and adds error styling.
     * Creates error element if it doesn't exist.
     * 
     * @since 2.0.0
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
     * @since 2.0.0
     * @param {jQuery} $input Input element
     * @return {void}
     */
    function clearInputError($input) {
        $input.removeClass('saw-input-error');
        $input.siblings('.saw-input-error-message').remove();
    }

})(jQuery);
