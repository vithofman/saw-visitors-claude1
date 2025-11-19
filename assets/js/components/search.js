/**
 * SAW Search Component
 * 
 * Handles search input interactions including AJAX search with debouncing,
 * keyboard shortcuts (Enter, Escape), and form submission fallback.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/Search
 * @version     1.1.0
 * @since       1.0.0
 * @author      SAW Visitors Team
 */

(function($) {
    'use strict';

    /**
     * SAW Search Component Class
     * 
     * Manages search input functionality with optional AJAX support,
     * debounced queries, and keyboard navigation.
     * 
     * @class
     * @since 1.0.0
     */
    class SAWSearchComponent {
        /**
         * Constructor
         * 
         * Initializes the search component with DOM elements and configuration.
         * 
         * @since 1.0.0
         * @param {jQuery} $input - Search input element
         */
        constructor($input) {
            this.$input = $input;
            this.$wrapper = $input.closest('.saw-search-wrapper');
            this.$clearBtn = this.$wrapper.find('.saw-search-clear');
            this.$submitBtn = this.$wrapper.find('.saw-search-submit');
            this.entity = $input.data('entity');
            this.ajaxAction = $input.data('ajax-action');
            this.ajaxEnabled = $input.data('ajax-enabled') === 1;
            this.searchTimeout = null;
            
            this.init();
        }
        
        /**
         * Initialize component
         * 
         * Sets up event bindings.
         * 
         * @since 1.0.0
         * @return {void}
         */
        init() {
            this.bindEvents();
        }
        
        /**
         * Bind event handlers
         * 
         * Attaches event listeners for input, keyboard, and button interactions.
         * 
         * @since 1.0.0
         * @return {void}
         */
        bindEvents() {
            this.$input.on('input', (e) => this.handleInput(e));
            this.$input.on('keydown', (e) => this.handleKeydown(e));
            this.$clearBtn.on('click', () => this.handleClear());
            this.$submitBtn.on('click', () => this.handleSubmit());
        }
        
        /**
         * Handle input changes
         * 
         * Toggles clear button and triggers debounced search if AJAX enabled.
         * 
         * @since 1.0.0
         * @param {Event} e - Input event
         * @return {void}
         */
        handleInput(e) {
            const value = this.$input.val().trim();
            
            this.toggleClearButton(value);
            
            if (this.ajaxEnabled) {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.performSearch(value);
                }, 300);
            }
        }
        
        /**
         * Handle keyboard events
         * 
         * Handles Enter (submit) and Escape (clear) key presses.
         * 
         * @since 1.0.0
         * @param {Event} e - Keyboard event
         * @return {void}
         */
        handleKeydown(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.handleSubmit();
            }
            
            if (e.key === 'Escape') {
                this.handleClear();
            }
        }
        
        /**
         * Handle submit action
         * 
         * Performs AJAX search or form submission based on configuration.
         * 
         * @since 1.0.0
         * @return {void}
         */
        handleSubmit() {
            const value = this.$input.val().trim();
            
            if (this.ajaxEnabled) {
                this.performSearch(value);
            } else {
                this.submitForm();
            }
        }
        
        /**
         * Handle clear button click
         * 
         * Clears input and performs empty search or form submission.
         * 
         * @since 1.0.0
         * @return {void}
         */
        handleClear() {
            this.$input.val('').trigger('input').focus();
            this.toggleClearButton('');
            
            if (this.ajaxEnabled) {
                this.performSearch('');
            } else {
                this.submitForm();
            }
        }
        
        /**
         * Toggle clear button visibility
         * 
         * Shows or hides the clear button based on input value.
         * 
         * @since 1.0.0
         * @param {string} value - Current input value
         * @return {void}
         */
        toggleClearButton(value) {
            if (value) {
                this.$clearBtn.fadeIn(150);
            } else {
                this.$clearBtn.fadeOut(150);
            }
        }
        
        /**
         * Perform AJAX search
         * 
         * Sends search query via AJAX and triggers custom events for results.
         * 
         * @since 1.0.0
         * @param {string} query - Search query string
         * @return {void}
         */
        performSearch(query) {
            $(document).trigger('saw:search:start', {
                entity: this.entity,
                query: query
            });
            
            $.ajax({
                url: sawGlobal.ajaxurl,
                type: 'GET',
                data: {
                    action: this.ajaxAction,
                    entity: this.entity,
                    s: query,
                    nonce: sawGlobal.nonce
                },
                beforeSend: () => {
                    this.$input.addClass('saw-search-loading');
                    this.$submitBtn.prop('disabled', true);
                },
                success: (response) => {
                    if (response.success) {
                        $(document).trigger('saw:search:success', {
                            entity: this.entity,
                            query: query,
                            data: response.data
                        });
                    } else {
                        $(document).trigger('saw:search:error', {
                            entity: this.entity,
                            query: query,
                            message: response.data?.message || 'Chyba při vyhledávání'
                        });
                    }
                },
                error: (xhr) => {
                    $(document).trigger('saw:search:error', {
                        entity: this.entity,
                        query: query,
                        message: 'Chyba serveru'
                    });
                },
                complete: () => {
                    this.$input.removeClass('saw-search-loading');
                    this.$submitBtn.prop('disabled', false);
                }
            });
        }
        
        /**
         * Submit search form
         * 
         * Submits parent form or updates URL query parameters.
         * 
         * @since 1.0.0
         * @return {void}
         */
        submitForm() {
            const $form = this.$input.closest('form');
            if ($form.length) {
                $form.submit();
            } else {
                const query = this.$input.val().trim();
                const url = new URL(window.location.href);
                
                if (query) {
                    url.searchParams.set('s', query);
                } else {
                    url.searchParams.delete('s');
                }
                
                url.searchParams.delete('paged');
                window.location.href = url.toString();
            }
        }
    }

    /**
     * Initialize all search components on document ready
     * 
     * @since 1.0.0
     */
    $(document).ready(function() {
        $('.saw-search-input').each(function() {
            new SAWSearchComponent($(this));
        });
    });

})(jQuery);