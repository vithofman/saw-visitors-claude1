/**
 * Account Types Module Scripts
 * 
 * Handles client-side functionality for account types:
 * - Auto-generation of slug from display name (create mode only)
 * - Name (slug) validation (lowercase, numbers, dashes)
 * - Price formatting (2 decimal places)
 * 
 * Features:
 * - Minimalized: Only essential validation and auto-slug
 * - Clean: Removed unnecessary emoji shortcuts
 * - Smart: Slug generation disabled in edit mode
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @since       1.0.0
 * @version     2.0.0
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // ================================================
        // DISPLAY NAME → SLUG AUTO-GENERATION
        // ================================================
        const $nameField = $('#name');
        const $displayNameField = $('#display_name');
        const isEditMode = $nameField.prop('readonly');
        
        /**
         * Auto-generate slug only in create mode (not edit)
         * 
         * Watches display_name field and automatically generates
         * a valid slug for the internal name field.
         * 
         * @since 1.0.0
         */
        if (!isEditMode && $nameField.length && $displayNameField.length) {
            $displayNameField.on('input', function() {
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
         * 
         * @param {string} text Display name to convert
         * @return {string} Generated slug
         * @since 1.0.0
         * 
         * @example
         * generateSlug('Premium účet') // returns 'premium-ucet'
         * generateSlug('VIP Tarif 2024') // returns 'vip-tarif-2024'
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
        /**
         * Validate slug format on blur
         * 
         * Ensures internal name contains only:
         * - Lowercase letters (a-z)
         * - Numbers (0-9)
         * - Dashes (-)
         * 
         * @since 1.0.0
         */
        $('#name').on('blur', function() {
            const name = $(this).val();
            
            if (!name) {
                return;
            }
            
            // Check pattern: lowercase, numbers, dashes only
            if (!/^[a-z0-9\-]+$/.test(name)) {
                // TODO: Replace alert() with showNotification() when available
                // i18n: This message should be localized via wp_localize_script()
                alert('Interní název může obsahovat jen malá písmena, číslice a pomlčky!');
                $(this).focus();
            }
        });
        
        // ================================================
        // PRICE FORMATTING
        // ================================================
        /**
         * Format price to 2 decimal places
         * 
         * Automatically formats price field to show
         * exactly 2 decimal places (e.g. 1500 → 1500.00)
         * 
         * @since 1.0.0
         */
        $('#price').on('blur', function() {
            const value = parseFloat($(this).val());
            
            if (!isNaN(value)) {
                $(this).val(value.toFixed(2));
            }
        });
        
    });
    
})(jQuery);