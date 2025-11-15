/**
 * SAW Select-Create Component
 * 
 * JavaScript for inline create functionality in select dropdowns.
 * Handles nested sidebar loading, z-index management, and dropdown updates.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/SelectCreate
 * @version     1.0.0
 * @since       13.0.0
 * @author      SAW Visitors Team
 */

(function($) {
    'use strict';

    /**
     * SAW Select-Create Component Class
     * 
     * Manages inline create functionality for select dropdowns.
     * Handles AJAX requests, nested sidebar management, and UI updates.
     * 
     * @class
     * @since 13.0.0
     */
    class SAWSelectCreateComponent {
        
        /**
         * Constructor
         * 
         * Initializes component with button element and configuration.
         * 
         * @since 13.0.0
         * @param {jQuery} $button - Inline create button element
         */
        constructor($button) {
            this.$button = $button;
            this.field = $button.data('field');
            this.module = $button.data('module');
            this.prefill = $button.data('prefill') || {};
            
            this.init();
        }
        
        /**
         * Initialize component
         * 
         * Sets up event bindings.
         * 
         * @since 13.0.0
         * @return {void}
         */
        init() {
            this.$button.on('click', (e) => {
                e.preventDefault();
                this.openNestedSidebar();
            });
        }
        
        /**
         * Open nested sidebar for inline creation
         * 
         * Loads form sidebar via AJAX and displays it as a nested overlay.
         * Calculates appropriate z-index based on existing sidebars.
         * 
         * @since 13.0.0
         * @return {void}
         */
        openNestedSidebar() {
    // Add loading state to button
    this.$button.addClass('loading').prop('disabled', true);
    
    // Calculate z-index for new nested sidebar
    const zIndex = this.calculateZIndex();
    
    // Store reference to this for use in callbacks
    const self = this;
    
    // AJAX request to load nested sidebar
    $.ajax({
        url: sawGlobal.ajaxurl,
        type: 'POST',
        data: {
            action: 'saw_load_sidebar_' + this.module,
            target_module: this.module,
            prefill: this.prefill,
            nonce: sawGlobal.nonce
        },
        success: function(response) {
            // Remove loading state
            self.$button.removeClass('loading').prop('disabled', false);
            
            if (!response.success) {
                alert(response.data?.message || 'Chyba při načítání formuláře');
                return;
            }
            
            // Create nested sidebar element
            const $nested = $(response.data.html);
            
            // Configure nested sidebar
            $nested.css('z-index', zIndex);
            $nested.attr('data-is-nested', '1');
            $nested.attr('data-target-field', self.field);
            
            // Append to body
            $('body').append($nested);
            
            // Activate sidebar with slight delay for animation
            setTimeout(() => {
                $nested.addClass('saw-sidebar-active');
            }, 10);
        },
        error: function(xhr, status, error) {
            // Remove loading state
            self.$button.removeClass('loading').prop('disabled', false);
            
            console.error('AJAX error:', status, error);
            console.error('Response:', xhr.responseText);
            alert('Chyba při komunikaci se serverem\n\n' + xhr.responseText);
        }
    });
}
        
        /**
         * Calculate z-index for nested sidebar
         * 
         * Determines appropriate z-index based on number of existing sidebars.
         * Each nested level gets +100 z-index to ensure proper stacking.
         * 
         * @since 13.0.0
         * @return {number} Calculated z-index value
         */
        calculateZIndex() {
            const sidebarCount = $('.saw-sidebar').length;
            const baseZIndex = 1000;
            const increment = 100;
            
            return baseZIndex + (sidebarCount * increment);
        }
    }
    
    /**
     * Global namespace for Select-Create functionality
     * 
     * Provides public methods for handling inline create success
     * and closing nested sidebars.
     * 
     * @namespace
     * @since 13.0.0
     */
    window.SAWSelectCreate = {
        
        /**
         * Handle successful inline create
         * 
         * Updates the target select dropdown with newly created option
         * and closes the nested sidebar.
         * 
         * Called from nested form's AJAX success handler.
         * 
         * @since 13.0.0
         * @param {Object} data         Response data from server
         * @param {number} data.id      ID of newly created record
         * @param {string} data.name    Display name of newly created record
         * @param {string} targetField  Field name of target select
         * @return {void}
         */
        handleInlineSuccess: function(data, targetField) {
            console.log('SAWSelectCreate: Handling success', data, targetField);
            
            // Find target select element
            const $select = $(`select[name="${targetField}"]`);
            
            if (!$select.length) {
                console.error('SAWSelectCreate: Target select not found:', targetField);
                return;
            }
            
            // Create new option element
            const $option = $('<option>', {
                value: data.id,
                text: data.name,
                selected: true
            });
            
            // Add to select dropdown
            $select.append($option);
            
            // Trigger change event for any listeners
            $select.trigger('change');
            
            // Visual feedback - highlight the updated field
            $select.addClass('saw-field-updated');
            setTimeout(() => {
                $select.removeClass('saw-field-updated');
            }, 2000);
            
            // Close nested sidebar
            const $nested = $('.saw-sidebar[data-is-nested="1"]').last();
            this.closeNested($nested);
            
            console.log('SAWSelectCreate: Option added successfully');
        },
        
        /**
         * Close nested sidebar
         * 
         * Removes nested sidebar with smooth animation.
         * 
         * @since 13.0.0
         * @param {jQuery} $nested Nested sidebar element
         * @return {void}
         */
        closeNested: function($nested) {
            if (!$nested || !$nested.length) {
                return;
            }
            
            // Deactivate sidebar (triggers CSS transition)
            $nested.removeClass('saw-sidebar-active');
            
            // Remove from DOM after animation completes
            setTimeout(() => {
                $nested.remove();
            }, 300); // Match CSS transition duration
        }
    };
    
    /**
     * Initialize all inline create buttons on page load
     * 
     * Automatically initializes any inline create buttons present in the DOM.
     * Also handles dynamically added buttons via event delegation.
     * 
     * @since 13.0.0
     */
    $(document).ready(function() {
        
        // Initialize existing buttons
        $('.saw-inline-create-btn').each(function() {
            new SAWSelectCreateComponent($(this));
        });
        
        // Handle dynamically added buttons (via event delegation)
        // Note: Using delegated events on document instead of per-button handlers
        // This is more efficient for dynamic content
        
    });
    
    /**
     * Close nested sidebar when clicking close button
     * 
     * Handles clicks on close buttons within nested sidebars.
     * Uses event delegation to work with dynamically added sidebars.
     * 
     * @since 13.0.0
     */
    $(document).on('click', '.saw-sidebar[data-is-nested="1"] .saw-sidebar-close', function(e) {
        e.preventDefault();
        
        const $nested = $(this).closest('.saw-sidebar[data-is-nested="1"]');
        window.SAWSelectCreate.closeNested($nested);
    });
    
    /**
     * Close nested sidebar on Escape key
     * 
     * Provides keyboard accessibility for closing nested sidebars.
     * 
     * @since 13.0.0
     */
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            const $nested = $('.saw-sidebar[data-is-nested="1"]').last();
            if ($nested.length) {
                window.SAWSelectCreate.closeNested($nested);
            }
        }
    });
    
})(jQuery);
