/**
 * Tabs Navigation Component
 * 
 * Handles tab switching with AJAX reload and state management
 * 
 * @package SAW_Visitors
 * @since 7.1.0
 */

(function($) {
    'use strict';

    /**
     * Initialize tabs navigation
     */
    function initTabsNavigation() {
        // Click handler for tabs
        $(document).on('click', '.saw-table-tab', function(e) {
            e.preventDefault();
            
            const $tab = $(this);
            const tabKey = $tab.data('tab');
            const url = $tab.attr('href');
            
            // Skip if already active
            if ($tab.hasClass('active')) {
                return;
            }
            
            // Show loading state
            $('.saw-table-tabs').addClass('loading');
            $('.saw-table-tab').removeClass('active');
            $tab.addClass('active');
            
            // Navigate to tab URL
            window.location.href = url;
        });
    }

    // Initialize on DOM ready
    $(document).ready(function() {
        if ($('.saw-table-tabs').length) {
            initTabsNavigation();
            console.log('✅ Tabs navigation initialized');
        }
    });

})(jQuery);

