/**
 * SAW Modal Component
 * 
 * @package SAW_Visitors
 * @version 4.0.0
 */

(function($) {
    'use strict';
    
    const SAWModal = {
        
        /**
         * Open modal
         */
        open: function(modalId, data = {}) {
            const $modal = $('#saw-modal-' + modalId);
            
            if (!$modal.length) {
                console.error('Modal not found:', modalId);
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
         */
        loadAjaxContent: function($modal, data) {
            const ajaxAction = $modal.data('ajax-action');
            const $body = $modal.find('.saw-modal-body');
            
            if (!ajaxAction) {
                console.error('No AJAX action defined');
                return;
            }
            
            // Show loading
            $body.html('<div class="saw-modal-loading"><div class="saw-spinner"></div><p>Načítám...</p></div>');
            
            // Prepare data
            const ajaxData = {
                action: ajaxAction,
                nonce: data.nonce || sawModalGlobal.nonce,
                id: data.id,
                ...data
            };
            
            // AJAX request
            $.ajax({
                url: sawModalGlobal.ajaxurl,
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
    
    // Initialize on document ready
    $(document).ready(function() {
        
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
                    console.error('Missing AJAX action or item ID');
                    return;
                }
                
                // Delete via AJAX
                $.ajax({
                    url: sawModalGlobal.ajaxurl,
                    type: 'POST',
                    data: {
                        action: ajaxAction,
                        nonce: itemData.nonce || sawModalGlobal.nonce,
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
        
        console.log('✅ SAWModal initialized');
    });
    
})(jQuery);