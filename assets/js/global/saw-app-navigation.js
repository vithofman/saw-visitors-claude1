/**
 * SAW App Navigation - SPA (Single Page Application) Support
 * 
 * Enables seamless navigation without full page reloads
 * Header, sidebar, and footer remain static while content updates
 * 
 * @package SAW_Visitors
 * @since   4.6.2
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
     * Initialize SPA navigation interceptor
     */
    function initSPANavigation() {
        $(document).on('click', '.saw-app-sidebar a, .saw-page-wrapper a[href^="/admin"], .saw-page-wrapper a[href^="/manager"]', function(e) {
            const $link = $(this);
            const href = $link.attr('href');
            
            if (!href || href.startsWith('http') || href.startsWith('//')) {
                return;
            }
            
            if (href.startsWith('#')) {
                return;
            }
            
            if ($link.attr('target') === '_blank') {
                return;
            }
            
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
        
        showLoading();
        
        $('html, body').animate({ scrollTop: 0 }, 300);
        
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
                    updatePageContent(response.data);
                    updateBrowserURL(url, response.data.title);
                    updateActiveMenuItem(response.data.active_menu);
                } else {
                    console.error('❌ Invalid response format:', response);
                    fallbackToFullPageLoad(url);
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ AJAX Error:', error);
                console.error('Response:', xhr.responseText);
                fallbackToFullPageLoad(url);
            },
            complete: function() {
                isNavigating = false;
                hideLoading();
            }
        });
    }
    
    /**
     * Update page content with fade transition
     */
    function updatePageContent(data) {
        const $content = $('#saw-app-content');
        
        if (!$content.length) {
            console.error('❌ Content container #saw-app-content not found');
            return;
        }
        
        $content.css('opacity', '0');
        
        setTimeout(function() {
            $content.html(data.content);
            
            if (data.title) {
                document.title = data.title + ' - SAW Visitors';
            }
            
            $content.css('opacity', '1');
            
            $(document).trigger('saw:page-loaded', [data]);
            
            console.log('✅ Content updated');
        }, 150);
    }
    
    /**
     * Update browser URL using History API
     */
    function updateBrowserURL(url, title) {
        const fullTitle = title ? title + ' - SAW Visitors' : 'SAW Visitors';
        
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
     * Update active menu item in sidebar
     */
    function updateActiveMenuItem(activeMenu) {
        $('.saw-sidebar-nav-item').removeClass('active');
        
        if (activeMenu) {
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
     * Fallback to traditional full page load
     */
    function fallbackToFullPageLoad(url) {
        console.log('⚠️ Falling back to full page load');
        window.location.href = url;
    }
    
    /**
     * Handle browser back/forward navigation
     */
    function initBrowserBackButton() {
        window.addEventListener('popstate', function(event) {
            console.log('⬅️ Browser back/forward button pressed');
            
            if (event.state && event.state.url) {
                navigateToPage(event.state.url);
            } else {
                window.location.reload();
            }
        });
        
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
     * Public API for manual navigation
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