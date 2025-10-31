/**
 * SAW Modal Triggers Handler
 * 
 * Univerzální handler pro otevírání modálů pomocí data atributů.
 * Použití:
 * 
 * <tr data-modal-trigger="modal-id" data-id="123" data-modal-nonce="xyz">
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 * @since   4.6.1
 */

(function($) {
    'use strict';
    
    /**
     * Initialize modal triggers
     */
    function initModalTriggers() {
        // Event delegation - funguje i pro dynamicky přidané elementy
        $(document).on('click', '[data-modal-trigger]', function(e) {
            // Don't open modal if clicking on action buttons
            if ($(e.target).closest('button, a, .saw-action-buttons').length > 0) {
                return;
            }
            
            const $trigger = $(this);
            const modalId = $trigger.data('modal-trigger');
            const itemId = $trigger.data('id');
            const nonce = $trigger.data('modal-nonce') || sawGlobal.customerModalNonce;
            
            // Validation
            if (!modalId) {
                console.error('SAW Modal Trigger: Missing modal-trigger attribute');
                return;
            }
            
            if (!itemId) {
                console.error('SAW Modal Trigger: Missing id attribute');
                return;
            }
            
            // Check if SAWModal is available
            if (typeof SAWModal === 'undefined') {
                console.error('SAWModal is not defined');
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
            
            // Debug log
            if (sawGlobal.debug) {
                console.log('Opening modal:', modalId, modalData);
            }
            
            // Open modal
            SAWModal.open(modalId, modalData);
        });
    }
    
    // Initialize on DOM ready
    $(document).ready(function() {
        initModalTriggers();
        
        if (sawGlobal.debug) {
            console.log('🔔 SAW Modal Triggers initialized');
        }
    });
    
})(jQuery);
