/**
 * Account Types Module Scripts
 * 
 * Handles client-side functionality for account types:
 * - Auto-generation of slug from display name (create mode only)
 * - Name (slug) validation (lowercase, numbers, dashes)
 * - Price formatting (2 decimal places)
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @version     4.0.0
 */

(function ($) {
    'use strict';

    $(document).ready(function () {

        // ================================================
        // DISPLAY NAME → SLUG AUTO-GENERATION
        // ================================================
        const $nameField = $('#name');
        const $displayNameField = $('#display_name');
        const isEditMode = $nameField.prop('readonly');

        /**
         * Auto-generate slug only in create mode (not edit)
         */
        if (!isEditMode && $nameField.length && $displayNameField.length) {
            $displayNameField.on('input', function () {
                const displayName = $(this).val();
                const slug = generateSlug(displayName);
                $nameField.val(slug);
            });
        }

        /**
         * Generate slug from display name
         * 
         * Converts display name to valid slug format:
         * - Lowercase only
         * - Removes diacritics (á → a, č → c)
         * - Removes special characters
         * - Spaces to dashes
         * - Multiple dashes to single dash
         */
        function generateSlug(text) {
            return text
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '') // Remove diacritics
                .replace(/[^a-z0-9\s\-]/g, '') // Only letters, numbers, spaces, dashes
                .trim()
                .replace(/\s+/g, '-') // Spaces to dashes
                .replace(/-+/g, '-'); // Multiple dashes to single
        }

        // ================================================
        // NAME (SLUG) VALIDATION
        // ================================================
        $('#name').on('blur', function () {
            const name = $(this).val();

            if (!name) {
                return;
            }

            // Check pattern: lowercase, numbers, dashes only
            if (!/^[a-z0-9\-]+$/.test(name)) {
                alert('Interní název může obsahovat jen malá písmena, číslice a pomlčky!');
                $(this).focus();
            }
        });

        // ================================================
        // PRICE FORMATTING
        // ================================================
        $('#price').on('blur', function () {
            const value = parseFloat($(this).val());

            if (!isNaN(value)) {
                $(this).val(value.toFixed(2));
            }
        });

    });

})(jQuery);
