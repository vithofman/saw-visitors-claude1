/**
 * SAW UI Components - Consolidated
 * 
 * All UI-related JavaScript components including:
 * - Modal (modal system with AJAX content loading)
 * - Modal Triggers (universal handler for opening modals)
 * - Search (search input with AJAX support)
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

(function($) {
    'use strict';

    /* ============================================
       MODAL COMPONENT
       ============================================ */

    /**
     * SAW Modal Object
     * 
     * Main modal management object with methods for opening, closing,
     * and loading content.
     * 
     * @since 1.0.0
     */
    const SAWModal = {
        
        /**
         * Open modal
         * 
         * Opens the specified modal and optionally loads AJAX content.
         * 
         * @since 1.0.0
         * @param {string} modalId - Modal identifier
         * @param {Object} data - Optional data for AJAX loading and action buttons
         * @return {void}
         */
        open: function(modalId, data = {}) {
            const $modal = $('#saw-modal-' + modalId);
            
            if (!$modal.length) {
                return;
            }
            
            // Add active class
            $modal.addClass('active');
            
            // Lock body scroll
            $('body').addClass('saw-modal-active');
            
            // Load AJAX content if enabled
            if ($modal.data('ajax-enabled')) {
                this.loadAjaxContent($modal, data);
            }
            
            // Store item ID for action buttons
            $modal.data('current-item-id', data.id);
            $modal.data('current-item-data', data);
        },
        
        /**
         * Close modal
         * 
         * Closes the specified modal or the currently active modal.
         * 
         * @since 1.0.0
         * @param {string} modalId - Optional modal identifier
         * @return {void}
         */
        close: function(modalId) {
            const $modal = modalId ? $('#saw-modal-' + modalId) : $('.saw-modal.active');
            
            if (!$modal.length) return;
            
            $modal.removeClass('active');
            $('body').removeClass('saw-modal-active');
            
            // Clear content after animation
            setTimeout(() => {
                $modal.find('.saw-modal-body').html('<div class="saw-modal-loading"><div class="saw-spinner"></div><p>Načítám...</p></div>');
            }, 300);
        },
        
        /**
         * Load AJAX content
         * 
         * Loads content via AJAX and displays it in the modal body.
         * 
         * @since 1.0.0
         * @param {jQuery} $modal - Modal element
         * @param {Object} data - Data to send with AJAX request
         * @return {void}
         */
        loadAjaxContent: function($modal, data) {
            const ajaxAction = $modal.data('ajax-action');
            const $body = $modal.find('.saw-modal-body');
            
            if (!ajaxAction) {
                return;
            }
            
            // Show loading
            $body.html('<div class="saw-modal-loading"><div class="saw-spinner"></div><p>Načítám...</p></div>');
            
            // Prepare data
            const ajaxData = {
                action: ajaxAction,
                nonce: data.nonce || (typeof sawModalGlobal !== 'undefined' ? sawModalGlobal.nonce : ''),
                id: data.id,
                ...data
            };
            
            const ajaxurl = (typeof sawModalGlobal !== 'undefined' && sawModalGlobal.ajaxurl) 
                ? sawModalGlobal.ajaxurl 
                : (typeof sawGlobal !== 'undefined' ? sawGlobal.ajaxurl : '/wp-admin/admin-ajax.php');
            
            // AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: ajaxData,
                success: (response) => {
                    if (response.success) {
                        $body.html(response.data.html || response.data);
                    } else {
                        $body.html('<div class="saw-alert saw-alert-danger">' + (response.data.message || 'Chyba při načítání') + '</div>');
                    }
                },
                error: () => {
                    $body.html('<div class="saw-alert saw-alert-danger">Chyba spojení se serverem</div>');
                }
            });
        },
        
        /**
         * Show toast notification
         * 
         * Displays a temporary toast message at the bottom of the screen.
         * 
         * @since 1.0.0
         * @param {string} message - Message to display
         * @param {string} type - Toast type (success, danger, warning)
         * @return {void}
         */
        toast: function(message, type = 'success') {
            const $toast = $('<div class="saw-toast saw-toast-' + type + '">' + message + '</div>');
            $('body').append($toast);
            
            setTimeout(() => $toast.addClass('saw-toast-show'), 10);
            setTimeout(() => {
                $toast.removeClass('saw-toast-show');
                setTimeout(() => $toast.remove(), 350);
            }, 3000);
        }
    };
    
    // Make globally available
    window.SAWModal = SAWModal;

    /* ============================================
       MODAL TRIGGERS
       ============================================ */

    /**
     * Initialize modal triggers
     * 
     * Sets up event delegation for modal trigger elements. Handles click events
     * on elements with data-modal-trigger attribute and opens the corresponding
     * modal with data from data attributes.
     * 
     * @since 4.6.1
     * @return {void}
     */
    function initModalTriggers() {
        // Event delegation - works for dynamically added elements
        $(document).on('click', '[data-modal-trigger]', function(e) {
            // Don't open modal if clicking on action buttons
            if ($(e.target).closest('button, a, .saw-action-buttons').length > 0) {
                return;
            }
            
            const $trigger = $(this);
            const modalId = $trigger.data('modal-trigger');
            const itemId = $trigger.data('id');
            const nonce = $trigger.data('modal-nonce') || (typeof sawGlobal !== 'undefined' ? sawGlobal.customerModalNonce : '');
            
            // Validation
            if (!modalId) {
                return;
            }
            
            if (!itemId) {
                return;
            }
            
            // Check if SAWModal is available
            if (typeof SAWModal === 'undefined') {
                return;
            }
            
            // Prepare data
            const modalData = {
                id: itemId,
                nonce: nonce
            };
            
            // Add any additional data attributes
            $.each($trigger.data(), function(key, value) {
                if (key !== 'modalTrigger' && key !== 'id' && key !== 'modalNonce') {
                    modalData[key] = value;
                }
            });
            
            // Open modal
            SAWModal.open(modalId, modalData);
        });
    }

    /* ============================================
       SEARCH COMPONENT
       ============================================ */

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
            
            const ajaxurl = (typeof sawGlobal !== 'undefined' && sawGlobal.ajaxurl) 
                ? sawGlobal.ajaxurl 
                : '/wp-admin/admin-ajax.php';
            
            $.ajax({
                url: ajaxurl,
                type: 'GET',
                data: {
                    action: this.ajaxAction,
                    entity: this.entity,
                    s: query,
                    nonce: (typeof sawGlobal !== 'undefined' ? sawGlobal.nonce : '')
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

    /* ============================================
       INITIALIZATION
       ============================================ */

    /**
     * Initialize all UI components on document ready
     * 
     * @since 1.0.0
     */
    $(document).ready(function() {
        
        // Initialize modal triggers
        initModalTriggers();
        
        // Initialize modal event handlers
        // Close modal on X button
        $(document).on('click', '.saw-modal-close', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $modal = $(this).closest('.saw-modal');
            const modalId = $modal.attr('id').replace('saw-modal-', '');
            SAWModal.close(modalId);
        });
        
        // Close modal on backdrop click
        $(document).on('click', '.saw-modal', function(e) {
            if (e.target === this) {
                const modalId = $(this).attr('id').replace('saw-modal-', '');
                SAWModal.close(modalId);
            }
        });
        
        // Close modal on ESC key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                const $activeModal = $('.saw-modal.active');
                if ($activeModal.length && $activeModal.data('close-escape') !== '0') {
                    const modalId = $activeModal.attr('id').replace('saw-modal-', '');
                    SAWModal.close(modalId);
                }
            }
        });
        
        // Handle header action buttons
        $(document).on('click', '.saw-modal-action-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $btn = $(this);
            const $modal = $btn.closest('.saw-modal');
            const actionType = $btn.data('action-type');
            const itemId = $modal.data('current-item-id');
            const itemData = $modal.data('current-item-data');
            
            // Edit action
            if (actionType === 'edit') {
                let url = $btn.data('action-url');
                if (url && itemId) {
                    url = url.replace('{id}', itemId);
                    window.location.href = url;
                }
            }
            
            // Delete action
            else if (actionType === 'delete') {
                const confirmMsg = $btn.data('action-confirm-message') || 'Opravdu chcete smazat tento záznam?';
                
                if (!confirm(confirmMsg)) return;
                
                const ajaxAction = $btn.data('action-ajax');
                if (!ajaxAction || !itemId) {
                    return;
                }
                
                const ajaxurl = (typeof sawModalGlobal !== 'undefined' && sawModalGlobal.ajaxurl) 
                    ? sawModalGlobal.ajaxurl 
                    : (typeof sawGlobal !== 'undefined' ? sawGlobal.ajaxurl : '/wp-admin/admin-ajax.php');
                
                const nonce = (itemData && itemData.nonce) 
                    ? itemData.nonce 
                    : (typeof sawModalGlobal !== 'undefined' ? sawModalGlobal.nonce : '');
                
                // Delete via AJAX
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: ajaxAction,
                        nonce: nonce,
                        id: itemId
                    },
                    success: (response) => {
                        if (response.success) {
                            SAWModal.toast('Záznam byl smazán', 'success');
                            SAWModal.close();
                            
                            // Reload page after delay
                            setTimeout(() => {
                                window.location.reload();
                            }, 500);
                        } else {
                            SAWModal.toast(response.data.message || 'Chyba při mazání', 'danger');
                        }
                    },
                    error: () => {
                        SAWModal.toast('Chyba spojení se serverem', 'danger');
                    }
                });
            }
            
            // Custom callback
            else if (actionType === 'custom') {
                const callback = $btn.data('action-callback');
                if (callback && typeof window[callback] === 'function') {
                    window[callback](itemId, itemData);
                }
            }
        });
        
        // Initialize search components
        $('.saw-search-input').each(function() {
            new SAWSearchComponent($(this));
        });
    });

})(jQuery);

