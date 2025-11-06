/**
 * Admin Table Component JavaScript
 *
 * Handles clickable table rows and modal integration.
 * Uses event delegation for dynamic content support.
 *
 * @package    SAW_Visitors
 * @subpackage Components
 * @version    1.1.0
 * @since      1.0.0
 */

(function($) {
    'use strict';
    
    /**
     * Initialize admin table component
     *
     * Sets up event delegation for clickable table rows.
     * Integrates with SAWModal system for detail views.
     *
     * @since 1.0.0
     * @return {void}
     */
    function initAdminTable() {
        // Row click handler (delegated for dynamic content)
        $(document).on('click', '[data-clickable-row]', function(e) {
            // Ignore clicks on interactive elements
            if ($(e.target).closest('.saw-action-buttons, button, a, input, select').length) {
                return;
            }
            
            const modalId = $(this).data('modal');
            const itemId = $(this).data('id');
            
            // Validate required data attributes
            if (!modalId || !itemId) {
                return;
            }
            
            // Open modal using SAWModal system
            if (typeof SAWModal !== 'undefined') {
                SAWModal.open(modalId, {
                    id: itemId,
                    nonce: window.sawAjaxNonce || (window.sawGlobal && window.sawGlobal.nonce)
                });
            }
        });
    }
    
    /**
     * Initialize on DOM ready
     *
     * @since 1.0.0
     */
    $(document).ready(function() {
        initAdminTable();
    });
    
})(jQuery);