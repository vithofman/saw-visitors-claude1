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

    /**
     * Initialize tabs arrow navigation
     */
    function initTabsArrowNavigation() {
        const $wrapper = $('.saw-table-tabs-wrapper');
        const $tabs = $('.saw-table-tabs');
        const $arrowLeft = $('.saw-tabs-nav-arrow-left');
        const $arrowRight = $('.saw-tabs-nav-arrow-right');
        
        if (!$wrapper.length || !$tabs.length) {
            return;
        }
        
        /**
         * Check if tabs are scrollable and update arrow visibility
         */
        function checkScrollability() {
            const tabsElement = $tabs[0];
            const isScrollable = tabsElement.scrollWidth > tabsElement.clientWidth;
            
            if (isScrollable) {
                $wrapper.addClass('has-scroll');
                updateArrowVisibility();
            } else {
                $wrapper.removeClass('has-scroll');
                $arrowLeft.hide();
                $arrowRight.hide();
            }
        }
        
        /**
         * Update arrow visibility based on scroll position
         */
        function updateArrowVisibility() {
            const tabsElement = $tabs[0];
            const scrollLeft = tabsElement.scrollLeft;
            const maxScroll = tabsElement.scrollWidth - tabsElement.clientWidth;
            
            // Show/hide left arrow
            if (scrollLeft > 0) {
                $arrowLeft.show().removeClass('disabled');
            } else {
                $arrowLeft.show().addClass('disabled');
            }
            
            // Show/hide right arrow
            if (scrollLeft < maxScroll - 1) { // -1 for rounding errors
                $arrowRight.show().removeClass('disabled');
            } else {
                $arrowRight.show().addClass('disabled');
            }
        }
        
        /**
         * Scroll tabs horizontally
         */
        function scrollTabs(direction) {
            const tabsElement = $tabs[0];
            const scrollAmount = 200; // pixels to scroll
            const currentScroll = tabsElement.scrollLeft;
            const targetScroll = direction === 'left' 
                ? currentScroll - scrollAmount 
                : currentScroll + scrollAmount;
            
            tabsElement.scrollTo({
                left: targetScroll,
                behavior: 'smooth'
            });
        }
        
        // Click handlers for arrows
        $arrowLeft.on('click', function(e) {
            e.preventDefault();
            if (!$(this).hasClass('disabled')) {
                scrollTabs('left');
            }
        });
        
        $arrowRight.on('click', function(e) {
            e.preventDefault();
            if (!$(this).hasClass('disabled')) {
                scrollTabs('right');
            }
        });
        
        // Update arrows on scroll
        $tabs.on('scroll', function() {
            updateArrowVisibility();
        });
        
        // Check scrollability on window resize
        $(window).on('resize', function() {
            checkScrollability();
        });
        
        // Initial check
        setTimeout(function() {
            checkScrollability();
        }, 100);
    }

    // Initialize on DOM ready
    $(document).ready(function() {
        if ($('.saw-table-tabs').length) {
            initTabsNavigation();
            initTabsArrowNavigation();
            console.log('✅ Tabs navigation initialized with arrow controls');
        }
    });
    
    // Re-initialize on dynamic content load
    $(document).on('saw:page-loaded', function() {
        if ($('.saw-table-tabs').length) {
            initTabsArrowNavigation();
        }
    });

})(jQuery);

