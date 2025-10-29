/**
 * SAW App Navigation - SPA (Single Page Application) Support
 * 
 * Enables seamless navigation without full page reloads
 * Header, sidebar, and footer remain static while content updates
 * 
 * ✨ ENHANCED v4.6.2: JavaScript Reinitialization System
 * - Reinitializes inline scripts after AJAX page load
 * - Triggers custom event 'saw:scripts-reinitialized'
 * - Fixes modal and interactive elements not working after navigation
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
            
            // Skip external links
            if (!href || href.startsWith('http') || href.startsWith('//')) {
                return;
            }
            
            // Skip anchor links
            if (href.startsWith('#')) {
                return;
            }
            
            // Skip links with target="_blank"
            if ($link.attr('target') === '_blank') {
                return;
            }
            
            // Skip links with data-no-ajax attribute
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
        
        // Scroll to top smoothly
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
                    
                    // ✨ NOVÉ: Reinicializace JavaScriptu po načtení
                    // Timeout zajistí, že DOM je plně aktualizován
                    setTimeout(function() {
                        reinitializePageScripts();
                    }, 200);
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
        
        // Fade out
        $content.css('opacity', '0');
        
        setTimeout(function() {
            // Update content
            $content.html(data.content);
            
            // Update title
            if (data.title) {
                document.title = data.title + ' - SAW Visitors';
            }
            
            // Fade in
            $content.css('opacity', '1');
            
            // Trigger page loaded event
            $(document).trigger('saw:page-loaded', [data]);
            
            console.log('✅ Content updated');
        }, 150);
    }
    
    /**
     * ✨ NOVÉ: Reinicializace JavaScriptu po AJAX načtení
     * 
     * Tato funkce řeší problém, kdy se JavaScript moduly neinicializují
     * po načtení stránky přes AJAX, protože $(document).ready() se volá
     * pouze jednou při prvním načtení.
     * 
     * Funkce:
     * 1. Najde všechny inline <script> tagy v #saw-app-content
     * 2. Spustí je znovu (např. jQuery event bindings z templates)
     * 3. Vyvolá custom event 'saw:scripts-reinitialized'
     * 4. Na tento event naslouchají moduly jako CustomerModal
     */
    function reinitializePageScripts() {
        console.log('🔄 Reinitializing page scripts...');
        
        let scriptsExecuted = 0;
        
        // Najdi a spusť všechny inline <script> tagy v načteném obsahu
        $('#saw-app-content').find('script').each(function() {
            const scriptContent = $(this).html();
            
            // Přeskoč prázdné scripty
            if (!scriptContent || !scriptContent.trim()) {
                return;
            }
            
            try {
                // Vytvoř nový script element a spusť jeho obsah
                const script = document.createElement('script');
                script.text = scriptContent;
                document.head.appendChild(script).parentNode.removeChild(script);
                
                scriptsExecuted++;
                console.log('  ↳ Executed inline script #' + scriptsExecuted);
            } catch (e) {
                console.error('  ✗ Error executing script:', e);
            }
        });
        
        console.log('✅ Total inline scripts executed:', scriptsExecuted);
        
        // Vyvolej custom event pro reinicializaci modulů
        // Na tento event naslouchají např. CustomerModal, FormValidation, atd.
        $(document).trigger('saw:scripts-reinitialized');
        
        console.log('📢 Event triggered: saw:scripts-reinitialized');
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
        
        // Initialize current state
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
        /**
         * Navigate to URL programmatically
         * @param {string} url Target URL
         */
        navigateTo: function(url) {
            navigateToPage(url);
        },
        
        /**
         * Reload current page via AJAX
         */
        reload: function() {
            navigateToPage(window.location.pathname);
        },
        
        /**
         * ✨ NOVÉ: Manuální reinicializace scriptů
         * Užitečné pro moduly, které chtějí vynutit refresh
         */
        reinitializeScripts: function() {
            reinitializePageScripts();
        }
    };
    
})(jQuery);