/**
 * Departments Module Scripts
 * 
 * Handles client-side validation and interactions for the Departments module.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Departments
 * @since       1.0.0
 * @author      SAW Visitors Dev Team
 * @version     1.0.0
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // ================================================
        // TRAINING VERSION VALIDATION
        // ================================================
        
        /**
         * Validates training version input field
         * 
         * Ensures the training version is a valid integer between 1 and 999.
         * If invalid, resets to minimum value of 1.
         * 
         * @since 1.0.0
         * @listens blur - Triggered when input loses focus
         */
        $('#training_version').on('blur', function() {
            const $input = $(this);
            const value = parseInt($input.val());
            
            // Validate: must be number between 1 and 999
            if (isNaN(value) || value < 1) {
                // TODO: Replace alert with custom notification system (sawNotification.error)
                alert('Verze školení musí být alespoň 1');
                $input.val(1);
                $input.focus();
                return;
            }
            
            // Cap at reasonable maximum
            if (value > 999) {
                // TODO: Replace alert with custom notification system
                alert('Verze školení nemůže být vyšší než 999');
                $input.val(999);
                $input.focus();
                return;
            }
            
            // Valid value - ensure it's formatted as integer
            $input.val(value);
        });
        
        /**
         * Prevent non-numeric input in training version field
         * 
         * @since 1.0.0
         * @listens keypress - Triggered on key press
         */
        $('#training_version').on('keypress', function(e) {
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
        
    });
    
})(jQuery);