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
     * Scroll active tab into view
     * Ensures active tab is visible after page load
     */
    function scrollActiveTabIntoView() {
        const $tabs = $('.sa-table-tabs');
        const $activeTab = $('.sa-table-tab.sa-table-tab--active');
        
        if (!$tabs.length || !$activeTab.length) {
            return;
        }
        
        const tabsElement = $tabs[0];
        const activeTabElement = $activeTab[0];
        
        // Check if active tab is visible
        const tabsRect = tabsElement.getBoundingClientRect();
        const activeTabRect = activeTabElement.getBoundingClientRect();
        
        // Tab is visible if it's within tabs container bounds
        const isVisible = (
            activeTabRect.left >= tabsRect.left &&
            activeTabRect.right <= tabsRect.right
        );
        
        if (!isVisible) {
            // Scroll active tab into view
            activeTabElement.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest',
                inline: 'center'
            });
            
            // Also update arrow visibility after scroll
            setTimeout(function() {
                if (typeof window.updateArrowVisibility === 'function') {
                    window.updateArrowVisibility();
                }
            }, 300);
        }
    }
    
    /**
     * Initialize tabs navigation
     */
    function initTabsNavigation() {
        // Click handler for tabs
        $(document).on('click', '.sa-table-tab', function(e) {
            e.preventDefault();
            
            const $tab = $(this);
            const tabKey = $tab.data('tab');
            const url = $tab.attr('href');
            
            // Skip if already active
            if ($tab.hasClass('active')) {
                return;
            }
            
            // Show loading state
            $('.sa-table-tabs').addClass('loading');
            $('.sa-table-tab').removeClass('sa-table-tab--active');
            $tab.addClass('sa-table-tab--active');
            
            // Navigate to tab URL
            window.location.href = url;
        });
    }

    /**
     * Initialize tabs arrow navigation
     */
    function initTabsArrowNavigation() {
        const $wrapper = $('.sa-table-tabs-wrapper');
        const $tabs = $('.sa-table-tabs');
        const $arrowLeft = $('.sa-tabs-nav-arrow-left');
        const $arrowRight = $('.sa-tabs-nav-arrow-right');
        
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
         * Exposed globally for use in scrollActiveTabIntoView
         */
        window.updateArrowVisibility = function() {
            const tabsElement = $tabs[0];
            if (!tabsElement) return;
            
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
        };
        
        // Local reference for internal use
        const updateArrowVisibility = window.updateArrowVisibility;
        
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
        if ($('.sa-table-tabs').length) {
            initTabsNavigation();
            initTabsArrowNavigation();
            
            // Scroll active tab into view after page load
            setTimeout(function() {
                scrollActiveTabIntoView();
                // Update arrow visibility after scrolling
                if (typeof window.updateArrowVisibility === 'function') {
                    window.updateArrowVisibility();
                }
            }, 200);
            
            console.log('✅ Tabs navigation initialized with arrow controls');
        }
    });
    
    // Re-initialize on dynamic content load
    $(document).on('saw:page-loaded', function() {
        if ($('.sa-table-tabs').length) {
            initTabsArrowNavigation();
        }
    });

})(jQuery);

