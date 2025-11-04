/**
 * Admin Table Component JavaScript
 * Row click handler + modal integration
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

(function($) {
    'use strict';
    
    /**
     * Initialize admin table component
     */
    function initAdminTable() {
        // Row click handler (delegated)
        $(document).on('click', '[data-clickable-row]', function(e) {
            // Ignore clicks on buttons, links, and action cells
            if ($(e.target).closest('.saw-action-buttons, button, a').length) {
                return;
            }
            
            const modalId = $(this).data('modal');
            const itemId = $(this).data('id');
            
            if (!modalId || !itemId) {
                return;
            }
            
            // Open modal using SAWModal system
            if (typeof SAWModal !== 'undefined') {
                SAWModal.open(modalId, {
                    id: itemId,
                    nonce: window.sawAjaxNonce || sawGlobal.nonce
                });
            } else {
                console.warn('[Admin Table] SAWModal not defined');
            }
        });
        
        if (sawGlobal?.debug) {
            console.log('✅ Admin Table component initialized');
        }
    }
    
    // Initialize on DOM ready
    $(document).ready(function() {
        initAdminTable();
    });
    
})(jQuery);