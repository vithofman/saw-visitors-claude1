/**
 * SAW App Navigation - SPA (Single Page Application) Support
 * 
 * OPRAVENÁ VERZE s brutálním modal cleanupem
 *
 * @package SAW_Visitors
 * @version 5.2.0 - BRUTAL CLEANUP
 */

(function($) {
    'use strict';

    let isNavigating = false;

    $(document).ready(function() {
        console.log('🎯 SAW SPA Navigation: Initialized');
        initSPANavigation();
        initBrowserBackButton();
        updateActiveMenuItemOnLoad();
        initMobileSidebar();
    });

    function initSPANavigation() {
        // 🔴 SPA NAVIGACE VYPNUTA - použij klasické page reloady
        console.log('⚠️ SPA Navigation DISABLED - using full page reloads');
        return;
        
        // Původní SPA kód zakomentován
        /*
        $(document).on('click', '.saw-app-sidebar a, .saw-page-wrapper a[href^="/admin"], .saw-page-wrapper a[href^="/manager"]', function(e) {
            const $link = $(this);
            const href = $link.attr('href');

            if (!href || href.startsWith('http') || href.startsWith('//')) return;
            if (href.startsWith('#')) return;
            if ($link.attr('target') === '_blank') return;
            if ($link.data('no-ajax') === true) return;

            console.log('🔗 SPA Navigation: Intercepted link:', href);
            e.preventDefault();
            
            if (window.innerWidth <= 1024) {
                closeMobileSidebar();
            }
            
            navigateToPage(href);
        });
        */
    }

    function initMobileSidebar() {
        const $hamburger = $('#sawHamburgerMenu');
        const $sidebar = $('#sawAppSidebar');
        const $overlay = $('#sawSidebarOverlay');
        const $close = $('#sawSidebarClose');

        $hamburger.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openMobileSidebar();
        });

        $overlay.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeMobileSidebar();
        });

        $close.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeMobileSidebar();
        });

        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $sidebar.hasClass('open')) {
                closeMobileSidebar();
            }
        });
    }

    function openMobileSidebar() {
        const $sidebar = $('#sawAppSidebar');
        const $overlay = $('#sawSidebarOverlay');
        
        $sidebar.addClass('open');
        $overlay.addClass('active');
        $('body').css('overflow', 'hidden');
        
        console.log('📱 Mobile sidebar opened');
    }

    function closeMobileSidebar() {
        const $sidebar = $('#sawAppSidebar');
        const $overlay = $('#sawSidebarOverlay');
        
        $sidebar.removeClass('open');
        $overlay.removeClass('active');
        $('body').css('overflow', '');
        
        console.log('📱 Mobile sidebar closed');
    }

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
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function(response) {
                console.log('✅ Page loaded successfully');

                if (response && response.success && response.data) {
                    // 🔥 BRUTAL CLEANUP PŘED update contentu
                    brutalCleanupBeforeNavigate();
                    
                    cleanupPageScopedAssets();
                    updatePageContent(response.data);
                    updateBrowserURL(url, response.data.title);
                    updateActiveMenuItem(response.data.active_menu, url);

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
                console.error('Response:', xhr && xhr.responseText);
                fallbackToFullPageLoad(url);
            },
            complete: function() {
                isNavigating = false;
                hideLoading();
            }
        });
    }

    /**
     * 🔥 BRUTAL CLEANUP BEFORE NAVIGATE
     * 
     * Odstraní VŠECHNY modaly a content z #saw-app-content
     * PŘED tím než se vloží nový content
     * 
     * KRITICKY DŮLEŽITÉ: Cleanup běží na STARÉM contentu PŘED jeho nahrazením!
     */
    function brutalCleanupBeforeNavigate() {
        console.log('[BRUTAL-CLEANUP] Starting pre-navigation cleanup...');
        
        const $content = $('#saw-app-content');
        if (!$content.length) {
            console.log('[BRUTAL-CLEANUP] Content container not found, skipping');
            return;
        }
        
        // 1. NAJDI všechny modaly UVNITŘ starého contentu
        const $oldModals = $content.find('[id*="saw-modal-"], .saw-modal');
        if ($oldModals.length > 0) {
            console.log('[BRUTAL-CLEANUP] Removing ' + $oldModals.length + ' old modals from content');
            $oldModals.remove();
        }
        
        // 2. ODSTRAŇ všechny overlays (ty jsou obvykle mimo content)
        const $overlays = $('.saw-modal-overlay, .modal-backdrop').not('#sawSidebarOverlay');
        if ($overlays.length > 0) {
            console.log('[BRUTAL-CLEANUP] Removing ' + $overlays.length + ' overlays');
            $overlays.remove();
        }
        
        // 3. VYČISTI body classes a styles
        $('body').removeClass('modal-open saw-modal-open saw-modal-active');
        $('body').css({
            'overflow': '',
            'padding-right': '',
            'height': '',
            'position': ''
        });
        console.log('[BRUTAL-CLEANUP] Cleaned body');
        
        // 4. ODSTRAŇ staré module wrappery
        const $oldWrappers = $content.find('[class*="saw-module-"]');
        if ($oldWrappers.length > 0) {
            console.log('[BRUTAL-CLEANUP] Removing ' + $oldWrappers.length + ' old module wrappers');
            $oldWrappers.remove();
        }
        
        // 5. ODSTRAŇ staré module styles
        const $oldStyles = $('style[id*="saw-module-css-"]');
        if ($oldStyles.length > 0) {
            console.log('[BRUTAL-CLEANUP] Removing ' + $oldStyles.length + ' old module styles');
            $oldStyles.remove();
        }
        
        console.log('[BRUTAL-CLEANUP] ✅ Cleanup complete!');
    }

    function updatePageContent(data) {
        const $content = $('#saw-app-content');
        if (!$content.length) {
            console.error('❌ Content container #saw-app-content not found');
            return;
        }

        $content.css('opacity', '0');

        setTimeout(function() {
            // POUŽIJ .html() AŽ PO BRUTAL CLEANUP
            $content.html(data.content || '');

            if (data.title) {
                document.title = data.title + ' - SAW Visitors';
            }

            $content.css('opacity', '1');
            $(document).trigger('saw:page-loaded', [data]);

            console.log('✅ Content updated');
        }, 150);
    }

    function reinitializePageScripts() {
        console.log('🔄 Reinitializing page scripts...');
        let scriptsExecuted = 0;

        $('#saw-app-content').find('script').each(function() {
            const scriptContent = $(this).html();
            if (!scriptContent || !scriptContent.trim()) return;

            try {
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

        $(document).trigger('saw:scripts-reinitialized');
        console.log('📢 Event triggered: saw:scripts-reinitialized');
        
        initTableInteractions();
    }

    function initTableInteractions() {
        $('.saw-search-form').off('submit').on('submit', function(e) {
            e.preventDefault();
            const searchValue = $(this).find('input[name="s"]').val();
            const currentUrl = window.location.pathname;
            const newUrl = currentUrl + '?s=' + encodeURIComponent(searchValue);
            navigateToPage(newUrl);
        });

        $('.saw-filter-select').off('change').on('change', function() {
            const filterValue = $(this).val();
            const filterName = $(this).attr('name');
            const currentUrl = window.location.pathname;
            const newUrl = currentUrl + '?' + filterName + '=' + encodeURIComponent(filterValue);
            navigateToPage(newUrl);
        });

        $('.saw-delete-btn').off('click').on('click', function(e) {
            e.stopPropagation();
            const itemId = $(this).data('id');
            const itemName = $(this).data('name');
            
            if (confirm('Opravdu chcete smazat: ' + itemName + '?')) {
                deleteItem(itemId, $(this).data('entity'));
            }
        });

        console.log('✅ Table interactions reinitialized');
    }

    function deleteItem(itemId, entity) {
        $.ajax({
            url: '/wp-admin/admin-ajax.php',
            method: 'POST',
            data: {
                action: 'saw_delete_' + entity,
                id: itemId,
                nonce: $('#saw_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    alert('Chyba při mazání');
                }
            }
        });
    }

    function updateBrowserURL(url, title) {
        const fullTitle = title ? title + ' - SAW Visitors' : 'SAW Visitors';
        window.history.pushState(
            { url: url, title: fullTitle, timestamp: Date.now() },
            fullTitle,
            url
        );
        console.log('🔗 URL updated:', url);
    }

    function updateActiveMenuItem(activeMenu, urlFallback) {
        const $items = $('.saw-nav-item, .saw-sidebar-nav-item');
        $items.removeClass('active');

        if (activeMenu) {
            let $target = $items.filter('[data-menu="' + activeMenu + '"]');
            if ($target.length) {
                $target.first().addClass('active');
                console.log('📍 Active menu updated by active_menu:', activeMenu);
                return;
            }
        }

        const candidate = deriveMenuFromURL(urlFallback || window.location.pathname);
        if (candidate) {
            let $target = $items.filter('[data-menu="' + candidate + '"]');
            if ($target.length) {
                $target.first().addClass('active');
                console.log('📍 Active menu updated by URL fallback:', candidate);
                return;
            }
            
            $target = $items.filter(function() {
                const href = ($(this).attr('href') || '').replace(/\/+$/, '');
                return href.indexOf('/' + candidate) !== -1;
            });
            if ($target.length) {
                $target.first().addClass('active');
                console.log('📍 Active menu updated by URL match:', candidate);
            }
        }
    }

    function updateActiveMenuItemOnLoad() {
        const pathname = window.location.pathname;
        const candidate = deriveMenuFromURL(pathname);
        
        if (candidate) {
            const $items = $('.saw-nav-item, .saw-sidebar-nav-item');
            let $target = $items.filter('[data-menu="' + candidate + '"]');
            
            if ($target.length) {
                $target.first().addClass('active');
                console.log('📍 Initial active menu set:', candidate);
            }
        }
        
        initTableInteractions();
    }

    function deriveMenuFromURL(pathname) {
        try {
            const parts = (pathname || '').replace(/\/+$/, '').split('/').filter(Boolean);
            
            if (parts.length === 1 && parts[0] === 'admin') {
                return 'dashboard';
            }
            
            if (parts[0] === 'admin' && parts[1] === 'settings' && parts[2]) {
                return parts[2];
            }
            
            if (parts[0] === 'admin' && parts[1]) {
                return parts[1];
            }
            
            if (parts[0] === 'manager' && parts[1]) {
                return parts[1];
            }
            
            return parts[parts.length - 1] || null;
        } catch (e) {
            return null;
        }
    }

    function showLoading() {
        $('#saw-page-loading').addClass('active');
    }

    function hideLoading() {
        setTimeout(function() {
            $('#saw-page-loading').removeClass('active');
        }, 200);
    }

    function fallbackToFullPageLoad(url) {
        console.log('⚠️ Falling back to full page load');
        window.location.href = url;
    }

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
            { url: currentURL, title: currentTitle, timestamp: Date.now() },
            currentTitle,
            currentURL
        );
    }

    function cleanupPageScopedAssets() {
        $('link[data-saw-scope="page"], style[data-saw-scope="page"]').each(function() {
            try { this.parentNode.removeChild(this); } catch(e) {}
        });
    }

    window.SAW_Navigation = {
        navigateTo: function(url) { navigateToPage(url); },
        reload: function() { navigateToPage(window.location.pathname); },
        reinitializeScripts: function() { reinitializePageScripts(); },
        setActiveByMenuId: function(menuId) { updateActiveMenuItem(menuId); },
        openSidebar: function() { openMobileSidebar(); },
        closeSidebar: function() { closeMobileSidebar(); }
    };

})(jQuery);