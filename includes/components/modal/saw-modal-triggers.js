/**
 * SAW Modal Triggers Handler
 * 
 * Universal handler for opening modals using data attributes with event
 * delegation support for dynamically added elements.
 * 
 * Usage:
 * <tr data-modal-trigger="modal-id" data-id="123" data-modal-nonce="xyz">
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/Modal
 * @version     1.0.0
 * @since       4.6.1
 * @author      SAW Visitors Team
 */

(function($) {
    'use strict';
    
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
            const nonce = $trigger.data('modal-nonce') || sawGlobal.customerModalNonce;
            
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
    
    /**
     * Initialize on DOM ready
     * 
     * @since 4.6.1
     */
    $(document).ready(function() {
        initModalTriggers();
    });
    
})(jQuery);