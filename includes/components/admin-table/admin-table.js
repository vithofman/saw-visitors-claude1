/**
 * Admin Table Component JavaScript
 *
 * Handles clickable table rows for both modal and sidebar modes.
 * Uses event delegation for dynamic content support.
 *
 * @package    SAW_Visitors
 * @subpackage Components
 * @version    2.0.0 - SIDEBAR SUPPORT
 * @since      1.0.0
 */

(function($) {
    'use strict';
    
    /**
     * Initialize admin table component
     *
     * Sets up event delegation for clickable table rows.
     * Integrates with both SAWModal and sidebar navigation.
     *
     * @since 1.0.0
     * @return {void}
     */
    function initAdminTable() {
        // Row click handler (delegated for dynamic content)
        $(document).on('click', '.saw-admin-table tbody tr', function(e) {
            // Ignore clicks on interactive elements
            if ($(e.target).closest('.saw-action-buttons, button, a, input, select').length) {
                return;
            }
            
            const $row = $(this);
            const itemId = $row.data('id');
            
            if (!itemId) {
                return;
            }
            
            // Check if we have data-clickable-row (modal mode)
            if ($row.attr('data-clickable-row')) {
                const modalId = $row.data('modal');
                
                if (modalId && typeof SAWModal !== 'undefined') {
                    SAWModal.open(modalId, {
                        id: itemId,
                        nonce: window.sawAjaxNonce || (window.sawGlobal && window.sawGlobal.nonce)
                    });
                }
                return;
            }
            
            // Check if we have data-detail-url (sidebar mode)
            const detailUrl = $row.data('detail-url');
            if (detailUrl) {
                window.location.href = detailUrl;
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