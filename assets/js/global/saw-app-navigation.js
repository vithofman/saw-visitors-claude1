/**
 * SAW App Navigation - SPA (Single Page Application) Support
 *
 * @package SAW_Visitors
 * @since   4.6.3
 */

(function($) {
    'use strict';

    let isNavigating = false;

    $(document).ready(function() {
        console.log('🎯 SAW SPA Navigation: Initialized');
        initSPANavigation();
        initBrowserBackButton();
    });

    function initSPANavigation() {
        $(document).on('click', '.saw-app-sidebar a, .saw-page-wrapper a[href^="/admin"], .saw-page-wrapper a[href^="/manager"]', function(e) {
            const $link = $(this);
            const href = $link.attr('href');

            if (!href || href.startsWith('http') || href.startsWith('//')) return;
            if (href.startsWith('#')) return;
            if ($link.attr('target') === '_blank') return;
            if ($link.data('no-ajax') === true) return;

            console.log('🔗 SPA Navigation: Intercepted link:', href);
            e.preventDefault();
            navigateToPage(href);
        });
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
                    // 1) Odlinkovat předchozí page-scoped styly (pokud existují)
                    cleanupPageScopedAssets();

                    // 2) Aktualizovat obsah
                    updatePageContent(response.data);

                    // 3) URL + aktivní menu
                    updateBrowserURL(url, response.data.title);
                    updateActiveMenuItem(response.data.active_menu, url);

                    // 4) Reinit skriptů
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

    function updatePageContent(data) {
        const $content = $('#saw-app-content');
        if (!$content.length) {
            console.error('❌ Content container #saw-app-content not found');
            return;
        }

        $content.css('opacity', '0');

        setTimeout(function() {
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

        // Spustit inline skripty vrácené uvnitř contentu
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

        // Událost pro reinit externích skriptů (page-specific moduly na to můžou reagovat)
        $(document).trigger('saw:scripts-reinitialized');
        console.log('📢 Event triggered: saw:scripts-reinitialized');
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

    /**
     * Označení aktivní položky menu.
     * - Preferuje data-menu, jinak páruje podle URL.
     * - Podporuje .saw-nav-item i .saw-sidebar-nav-item.
     */
    function updateActiveMenuItem(activeMenu, urlFallback) {
        const $items = $('.saw-nav-item, .saw-sidebar-nav-item');
        $items.removeClass('active');

        // 1) Pokud přišlo active_menu ze serveru
        if (activeMenu) {
            let $target = $items.filter('[data-menu="' + activeMenu + '"]');
            if ($target.length) {
                $target.first().addClass('active');
                console.log('📍 Active menu updated by active_menu:', activeMenu);
                return;
            }
        }

        // 2) Fallback: odvodit z URL
        const candidate = deriveMenuFromURL(urlFallback || window.location.pathname);
        if (candidate) {
            let $target = $items.filter('[data-menu="' + candidate + '"]');
            if (!$target.length) {
                // poslední segment URL bez trailing slash
                $target = $items.filter(function() {
                    const href = ($(this).attr('href') || '').replace(/\/+$/, '');
                    return href.endsWith('/' + candidate);
                });
            }
            if ($target.length) {
                $target.first().addClass('active');
                console.log('📍 Active menu updated by URL fallback:', candidate);
            }
        }
    }

    function deriveMenuFromURL(pathname) {
        try {
            const parts = (pathname || '').replace(/\/+$/, '').split('/').filter(Boolean);
            // očekáváme /admin/<section>/... => vrátíme <section>
            if (parts[0] === 'admin' && parts[1]) return parts[1];
            if (parts[0] === 'manager' && parts[1]) return parts[1];
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

    /**
     * Odstraní page-scoped styly, které si stránka mohla připojit při zobrazení formuláře apod.
     * Tyto <link>/<style> přidávejte s data-saw-scope="page", aby šly bezpečně odstranit.
     */
    function cleanupPageScopedAssets() {
        $('link[data-saw-scope="page"], style[data-saw-scope="page"]').each(function() {
            try { this.parentNode.removeChild(this); } catch(e) {}
        });
    }

    // Public API
    window.SAW_Navigation = {
        navigateTo: function(url) { navigateToPage(url); },
        reload: function() { navigateToPage(window.location.pathname); },
        reinitializeScripts: function() { reinitializePageScripts(); },
        setActiveByMenuId: function(menuId) { updateActiveMenuItem(menuId); }
    };

})(jQuery);
