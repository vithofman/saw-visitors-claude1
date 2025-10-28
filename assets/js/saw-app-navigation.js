/**
 * SAW App Navigation - SPA (Single Page Application) Support
 * 
 * Zajišťuje, že při kliknutí na menu se nenačítá celá stránka,
 * ale jen obsah. Header, sidebar a footer zůstávají statické.
 * 
 * @package SAW_Visitors
 * @version 4.6.2
 */

(function($) {
    'use strict';
    
    let isNavigating = false;
    
    $(document).ready(function() {
        console.log('🎯 SAW SPA Navigation: Initialized');
        
        initSPANavigation();
        initBrowserBackButton();
    });
    
    /**
     * Initialize SPA navigation
     */
    function initSPANavigation() {
        // Intercept všechny linky v sidebaru
        $(document).on('click', '.saw-app-sidebar a, .saw-page-wrapper a[href^="/admin"], .saw-page-wrapper a[href^="/manager"]', function(e) {
            const $link = $(this);
            const href = $link.attr('href');
            
            // Ignore external links
            if (!href || href.startsWith('http') || href.startsWith('//')) {
                return;
            }
            
            // Ignore hash links
            if (href.startsWith('#')) {
                return;
            }
            
            // Ignore if target="_blank"
            if ($link.attr('target') === '_blank') {
                return;
            }
            
            // Ignore if data-no-ajax="true"
            if ($link.data('no-ajax') === true) {
                return;
            }
            
            console.log('🔗 SPA Navigation: Intercepted link:', href);
            
            e.preventDefault();
            navigateToPage(href);
        });
    }
    
    /**
     * Navigate to page via AJAX
     */
    function navigateToPage(url) {
        if (isNavigating) {
            console.log('⏳ Navigation already in progress, ignoring');
            return;
        }
        
        isNavigating = true;
        
        console.log('📡 Loading page:', url);
        
        // Show loading overlay
        showLoading();
        
        // Scroll to top smoothly
        $('html, body').animate({ scrollTop: 0 }, 300);
        
        // AJAX request
        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                console.log('✅ Page loaded successfully');
                
                if (response.success && response.data) {
                    // Update content
                    updatePageContent(response.data);
                    
                    // Update URL without page reload
                    updateBrowserURL(url, response.data.title);
                    
                    // Update active menu item
                    updateActiveMenuItem(response.data.active_menu);
                    
                } else {
                    console.error('❌ Invalid response format:', response);
                    fallbackToFullPageLoad(url);
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ AJAX Error:', error);
                console.error('Response:', xhr.responseText);
                
                // Fallback to full page load
                fallbackToFullPageLoad(url);
            },
            complete: function() {
                isNavigating = false;
                hideLoading();
            }
        });
    }
    
    /**
     * Update page content
     */
    function updatePageContent(data) {
        const $content = $('#saw-app-content');
        
        if (!$content.length) {
            console.error('❌ Content container #saw-app-content not found');
            return;
        }
        
        // Fade out old content
        $content.css('opacity', '0');
        
        setTimeout(function() {
            // Replace content
            $content.html(data.content);
            
            // Update page title
            if (data.title) {
                document.title = data.title + ' - SAW Visitors';
            }
            
            // Fade in new content
            $content.css('opacity', '1');
            
            // Trigger custom event pro případné re-initialization scriptu
            $(document).trigger('saw:page-loaded', [data]);
            
            console.log('✅ Content updated');
        }, 150);
    }
    
    /**
     * Update browser URL
     */
    function updateBrowserURL(url, title) {
        const fullTitle = title ? title + ' - SAW Visitors' : 'SAW Visitors';
        
        // Push new URL to history
        window.history.pushState(
            { 
                url: url,
                title: fullTitle,
                timestamp: Date.now()
            },
            fullTitle,
            url
        );
        
        console.log('🔗 URL updated:', url);
    }
    
    /**
     * Update active menu item
     */
    function updateActiveMenuItem(activeMenu) {
        // Remove all active classes
        $('.saw-sidebar-nav-item').removeClass('active');
        
        if (activeMenu) {
            // Add active class to matching item
            $('.saw-sidebar-nav-item[data-menu="' + activeMenu + '"]').addClass('active');
            console.log('📍 Active menu updated:', activeMenu);
        }
    }
    
    /**
     * Show loading overlay
     */
    function showLoading() {
        $('#saw-page-loading').addClass('active');
    }
    
    /**
     * Hide loading overlay
     */
    function hideLoading() {
        setTimeout(function() {
            $('#saw-page-loading').removeClass('active');
        }, 200);
    }
    
    /**
     * Fallback to full page load
     */
    function fallbackToFullPageLoad(url) {
        console.log('⚠️ Falling back to full page load');
        window.location.href = url;
    }
    
    /**
     * Handle browser back/forward buttons
     */
    function initBrowserBackButton() {
        window.addEventListener('popstate', function(event) {
            console.log('⬅️ Browser back/forward button pressed');
            
            if (event.state && event.state.url) {
                navigateToPage(event.state.url);
            } else {
                // Reload if no state
                window.location.reload();
            }
        });
        
        // Store initial state
        const currentURL = window.location.pathname;
        const currentTitle = document.title;
        
        window.history.replaceState(
            {
                url: currentURL,
                title: currentTitle,
                timestamp: Date.now()
            },
            currentTitle,
            currentURL
        );
    }
    
    /**
     * Public API pro manuální navigaci
     */
    window.SAW_Navigation = {
        navigateTo: function(url) {
            navigateToPage(url);
        },
        
        reload: function() {
            navigateToPage(window.location.pathname);
        }
    };
    
})(jQuery);