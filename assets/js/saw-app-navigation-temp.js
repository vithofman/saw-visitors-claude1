/**
 * SAW App Navigation - SPA (Single Page Application) Support
 * 
 * @package SAW_Visitors
 * @version 5.6.0 - RELATED ITEMS SUPPORT ADDED
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
        console.log('✅ SPA Navigation ENABLED');
        
        $(document).on('click', '.saw-app-sidebar a, .saw-page-wrapper a[href^="/admin"], .saw-page-wrapper a[href^="/manager"]', function(e) {
            const $link = $(this);
            const href = $link.attr('href');
            
            // Skip related item links (handled by sidebar.js)
if ($link.hasClass('saw-related-item-link') && !$link.hasClass('saw-spa-link')) {
    console.log('⏭️ SPA: Skipping - related item link');
                return;
            }
            
            const $parentRow = $link.closest('tr[data-detail-url]');
            if ($parentRow.length > 0) {
                console.log('⏭️ SPA: Skipping - inside table row with sidebar');
                return;
            }
            
            const $parentTable = $link.closest('.saw-admin-table');
            if ($parentTable.length > 0) {
                console.log('⏭️ SPA: Skipping - inside admin table');
                return;
            }
            
            const $actionButtons = $link.closest('.saw-action-buttons');
            if ($actionButtons.length > 0) {
                console.log('⏭️ SPA: Skipping - inside action buttons');
                return;
            }

            if (!href || href.startsWith('http') || href.startsWith('//')) return;
            if (href.startsWith('#')) return;
            if ($link.attr('target') === '_blank') return;
            if ($link.data('no-ajax') === true) return;
            if ($link.hasClass('saw-sidebar-close')) return;

            console.log('🔗 SPA Navigation: Intercepted link:', href);
            e.preventDefault();
            
            if (window.innerWidth <= 1024) {
                closeMobileSidebar();
            }
            
            navigateToPage(href);
        });
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
    }

    function closeMobileSidebar() {
        const $sidebar = $('#sawAppSidebar');
        const $overlay = $('#sawSidebarOverlay');
        
        $sidebar.removeClass('open');
        $overlay.removeClass('active');
        $('body').css('overflow', '');
    }

    function navigateToPage(url) {
        if (isNavigating) {
            return;
        }

        isNavigating = true;

        showLoading();
        $('html, body').animate({ scrollTop: 0 }, 300);

        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function(response) {
                if (response && response.success && response.data) {
                    brutalCleanupBeforeNavigate();
                    
                    cleanupPageScopedAssets();
                    updatePageContent(response.data);
                    updateBrowserURL(url, response.data.title);
                    updateActiveMenuItem(response.data.active_menu, url);

                    setTimeout(function() {
                        reinitializePageScripts();
                    }, 200);
                } else {
                    fallbackToFullPageLoad(url);
                }
            },
            error: function() {
                fallbackToFullPageLoad(url);
            },
            complete: function() {
                isNavigating = false;
                hideLoading();
            }
        });
    }

    function brutalCleanupBeforeNavigate() {
        const $content = $('#saw-app-content');
        if (!$content.length) {
            return;
        }
        
        const $oldModals = $content.find('[id*="saw-modal-"], .saw-modal');
        if ($oldModals.length > 0) {
            $oldModals.remove();
        }
        
        const $overlays = $('.saw-modal-overlay, .modal-backdrop').not('#sawSidebarOverlay');
        if ($overlays.length > 0) {
            $overlays.remove();
        }
        
        const $oldStyles = $('style[id*="saw-module-css-"]');
        if ($oldStyles.length > 0) {
            $oldStyles.remove();
        }
    }

    function updatePageContent(data) {
        const $content = $('#saw-app-content');
        if (!$content.length) {
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
        }, 150);
    }

    function reinitializePageScripts() {
        let scriptsExecuted = 0;

        $('#saw-app-content').find('script').each(function() {
            const scriptContent = $(this).html();
            if (!scriptContent || !scriptContent.trim()) return;

            try {
                const script = document.createElement('script');
                script.text = scriptContent;
                document.head.appendChild(script).parentNode.removeChild(script);
                scriptsExecuted++;
            } catch (e) {
                console.error('Error executing script:', e);
            }
        });

        $(document).trigger('saw:scripts-reinitialized');
        
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
    }

    function deleteItem(itemId, entity) {
        if (typeof sawGlobal === 'undefined') {
            alert('Chyba: sawGlobal není definován.');
            return;
        }
        
        $.ajax({
            url: sawGlobal.ajaxurl || '/wp-admin/admin-ajax.php',
            method: 'POST',
            data: {
                action: 'saw_delete_' + entity,
                id: itemId,
                nonce: sawGlobal.nonce
            },
            success: function(response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    alert('Chyba při mazání: ' + (response.data?.message || 'Neznámá chyba'));
                }
            },
            error: function() {
                alert('Chyba při mazání');
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
    }

    function updateActiveMenuItem(activeMenu, urlFallback) {
        const $items = $('.saw-nav-item, .saw-sidebar-nav-item');
        $items.removeClass('active');

        if (activeMenu) {
            let $target = $items.filter('[data-menu="' + activeMenu + '"]');
            if ($target.length) {
                $target.first().addClass('active');
                return;
            }
        }

        const candidate = deriveMenuFromURL(urlFallback || window.location.pathname);
        if (candidate) {
            let $target = $items.filter('[data-menu="' + candidate + '"]');
            if ($target.length) {
                $target.first().addClass('active');
                return;
            }
            
            $target = $items.filter(function() {
                const href = ($(this).attr('href') || '').replace(/\/+$/, '');
                return href.indexOf('/' + candidate) !== -1;
            });
            if ($target.length) {
                $target.first().addClass('active');
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
        window.location.href = url;
    }

    function initBrowserBackButton() {
        window.addEventListener('popstate', function(event) {
            // CRITICAL FIX: Ignore admin table pushState events
            if (event.state && event.state.sawAdminTable) {
                console.log('⏭️ SPA: Ignoring admin table popstate event');
                return;
            }
            
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
