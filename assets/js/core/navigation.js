/**
 * SAW App Navigation - SPA (Single Page Application) Support
 * 
 * Consolidated navigation with enhanced features:
 * - Base SPA navigation
 * - Retry mechanism for failed requests
 * - Dynamic module asset loading
 * - Enhanced cleanup
 * - Better error handling
 * - TinyMCE initialization
 * 
 * @package SAW_Visitors
 * @version 6.0.0 - Consolidated with Enhanced Features
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
        
        // CRITICAL: Remove existing handlers first to prevent duplicates
        // Use namespace to avoid conflicts
        $(document).off('click.saw-spa-nav', '.saw-app-sidebar a, .saw-page-wrapper a[href^="/admin"], .saw-page-wrapper a[href^="/manager"]');
        
        $(document).on('click.saw-spa-nav', '.saw-app-sidebar a, .saw-page-wrapper a[href^="/admin"], .saw-page-wrapper a[href^="/manager"]', function(e) {
            const $link = $(this);
            const href = $link.attr('href');
            
            // Skip AJAX-handled sidebar edit buttons
            if ($link.hasClass('saw-edit-ajax')) {
                console.log('⏭️ SPA: Skipping - AJAX edit button');
                return;
            }
            
            // Skip sidebar close buttons (handled by admin-table.js)
            if ($link.hasClass('saw-sidebar-close')) {
                console.log('⏭️ SPA: Skipping - Sidebar close button');
                return;
            }
            
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

            console.log('🔗 SPA Navigation: Intercepted link:', href, 'Link element:', $link[0], 'isNavigating:', isNavigating);
            
            // CRITICAL: Check if navigation is already in progress
            if (isNavigating) {
                console.warn('⚠️ Navigation already in progress, ignoring click');
                return;
            }
            
            e.preventDefault();
            e.stopPropagation(); // Prevent other handlers from interfering
            
            if (window.innerWidth <= 1024) {
                closeMobileSidebar();
            }
            
            navigateToPage(href, 0);
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

    /**
     * Enhanced navigateToPage with retry and module loading
     */
    function navigateToPage(url, retryCount) {
        retryCount = retryCount || 0;
        
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
                    console.log('📦 Navigation: Success');

                    // CRITICAL FIX: Reset isNavigating IMMEDIATELY after successful response
                    // This allows menu clicks to work right away, even while assets are loading
                    isNavigating = false;
                    console.log('✅ Navigation successful, resetting isNavigating flag');

                    // Enhanced cleanup
                    enhancedCleanup();
                    cleanupPageScopedAssets();

                    // Update content
                    updatePageContent(response.data);
                    updateBrowserURL(url, response.data.title);
                    updateActiveMenuItem(response.data.active_menu, url);

                    // Load required assets from server response
                    if (response.data.assets) {
                        console.log('📦 Loading assets from server response:', response.data.assets);
                        loadAssetsFromResponse(response.data.assets, function() {
                            reinitializePageScripts();
                            const moduleName = detectModuleFromURL(url);
                            if (moduleName) {
                                reinitializeModuleScripts(moduleName);
                            }
                        });
                    } else {
                        // Fallback: try to detect and load module assets
                        setTimeout(function() {
                            const moduleName = detectModuleFromURL(url);
                            if (moduleName) {
                                console.log('🔧 Loading module (fallback):', moduleName);
                                loadModuleAssets(moduleName, function() {
                                    reinitializePageScripts();
                                    reinitializeModuleScripts(moduleName);
                                });
                            } else {
                                reinitializePageScripts();
                            }
                        }, 200);
                    }
                } else {
                    console.warn('⚠️ Invalid response, falling back');
                    isNavigating = false; // Reset even on invalid response
                    fallbackToFullPageLoad(url);
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Navigation Error:', status);

                // Retry mechanism
                if (retryCount < 2) {
                    console.log('🔄 Retrying... (' + (retryCount + 1) + '/2)');
                    isNavigating = false;
                    hideLoading();
                    setTimeout(function() {
                        navigateToPage(url, retryCount + 1);
                    }, 1000);
                    return;
                }

                // Show error UI
                showNavigationError(url);
                isNavigating = false;
                hideLoading();
            },
            complete: function() {
                // CRITICAL FIX: Always reset isNavigating in complete callback as fallback
                // This ensures flag is reset even if success/error callbacks fail
                if (retryCount >= 2) {
                    console.log('✅ Navigation complete (retry limit), resetting isNavigating flag');
                    isNavigating = false;
                    hideLoading();
                }
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

    /**
     * Enhanced cleanup - more thorough than original
     * 
     * CRITICAL: Only removes module-specific assets, NOT global components
     * like admin-table, which must remain in DOM for all modules.
     */
    function enhancedCleanup() {
        const $content = $('#saw-app-content');
        if (!$content.length) return;

        console.log('🧹 Enhanced cleanup...');

        // CRITICAL: Remove any lingering loading overlays that might block menu clicks
        $('#saw-loading-overlay').remove();

        // Remove modals
        $content.find('[id*="saw-modal-"], .saw-modal').remove();
        $('.saw-modal-overlay, .modal-backdrop').not('#sawSidebarOverlay').remove();

        // Remove inline styles (module-specific only)
        $('style[id*="saw-module-css-"]').remove();

        // Destroy TinyMCE editors
        if (typeof tinymce !== 'undefined') {
            tinymce.editors.forEach(function(editor) {
                try {
                    tinymce.remove('#' + editor.id);
                } catch (e) { }
            });
        }

        // Clear WordPress media frames
        if (typeof wp !== 'undefined' && wp.media && wp.media.frames) {
            wp.media.frames = {};
        }

        // CRITICAL: Only remove module-specific assets (with data-saw-module attribute)
        // DO NOT remove global components like admin-table, which are needed by all modules
        $('link[data-saw-module], style[data-saw-module]').remove();
        $('script[data-saw-module]').remove();

        // Remove body overflow lock
        $('body').css('overflow', '');
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
        
        // CRITICAL: Re-initialize SPA navigation handlers after AJAX content load
        // This ensures menu links continue to work after page navigation
        console.log('🔄 Re-initializing SPA navigation handlers');
        initSPANavigation();
        
        initTableInteractions();
    }

    function initTableInteractions() {
        $('.saw-search-form').off('submit').on('submit', function(e) {
            e.preventDefault();
            const searchValue = $(this).find('input[name="s"]').val();
            const currentUrl = window.location.pathname;
            const newUrl = currentUrl + '?s=' + encodeURIComponent(searchValue);
            navigateToPage(newUrl, 0);
        });

        $('.saw-filter-select').off('change').on('change', function() {
            const filterValue = $(this).val();
            const filterName = $(this).attr('name');
            const currentUrl = window.location.pathname;
            const newUrl = currentUrl + '?' + filterName + '=' + encodeURIComponent(filterValue);
            navigateToPage(newUrl, 0);
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
                navigateToPage(event.state.url, 0);
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

    /**
     * Detect module name from URL
     */
    function detectModuleFromURL(url) {
        try {
            const parts = url.split('/').filter(Boolean);
            if (parts[0] === 'admin' && parts[1]) {
                const moduleName = parts[1].split('?')[0];
                if (moduleName === 'settings' || moduleName === 'dashboard') {
                    return null;
                }
                return moduleName;
            }
            return null;
        } catch (e) {
            return null;
        }
    }

    /**
     * Load module-specific assets dynamically
     * 
     * Also ensures admin-table CSS/JS are loaded (needed by modules using admin-table)
     */
    function loadModuleAssets(moduleName, callback) {
        if (!moduleName || typeof sawGlobal === 'undefined') {
            // Even if no module, ensure admin-table assets are loaded
            ensureAdminTableAssets(function() {
                if (callback) callback();
            });
            return;
        }

        const cssUrl = sawGlobal.pluginUrl + 'assets/css/modules/' + moduleName + '/' + moduleName + '.css';
        const jsUrl = sawGlobal.pluginUrl + 'assets/js/modules/' + moduleName + '/' + moduleName + '.js';

        let cssLoaded = false;
        let jsLoaded = false;
        let adminTableLoaded = false;

        // Load module CSS if not already loaded
        if (!$('link[href*="' + moduleName + '.css"]').length) {
            $('<link>')
                .attr('rel', 'stylesheet')
                .attr('href', cssUrl + '?v=' + sawGlobal.version)
                .attr('data-saw-module', moduleName)
                .appendTo('head');
            console.log('  ✅ Loaded CSS:', moduleName);
            cssLoaded = true;
        } else {
            cssLoaded = true;
        }

        // Load module JS if not already loaded
        if (!$('script[src*="' + moduleName + '.js"]').length) {
            $.getScript(jsUrl + '?v=' + sawGlobal.version)
                .done(function() {
                    console.log('  ✅ Loaded JS:', moduleName);
                    jsLoaded = true;
                    $(document).trigger('saw:module-loaded', [moduleName]);
                    checkAndCallback();
                })
                .fail(function() {
                    console.warn('  ⚠️ Failed to load JS for:', moduleName);
                    jsLoaded = true;
                    checkAndCallback();
                });
        } else {
            jsLoaded = true;
            checkAndCallback();
        }

        // Ensure admin-table assets are loaded (needed by modules using admin-table)
        ensureAdminTableAssets(function() {
            adminTableLoaded = true;
            checkAndCallback();
        });

        function checkAndCallback() {
            if (cssLoaded && jsLoaded && adminTableLoaded) {
                if (callback) callback();
            }
        }
    }

    /**
     * Helper function to sort assets by dependencies
     * Ensures CSS/JS files are loaded in correct order
     */
    function sortAssetsByDependencies(assetsList) {
        const sorted = [];
        const processed = {};
        const processing = {};
        
        function processAsset(asset) {
            if (processed[asset.handle]) {
                return;
            }
            if (processing[asset.handle]) {
                // Circular dependency - skip and add anyway
                if (!processed[asset.handle]) {
                    sorted.push(asset);
                    processed[asset.handle] = true;
                }
                return;
            }
            
            processing[asset.handle] = true;
            
            // Process dependencies first
            if (asset.deps && asset.deps.length > 0) {
                asset.deps.forEach(function(dep) {
                    const depAsset = assetsList.find(function(a) { return a.handle === dep; });
                    if (depAsset && !processed[dep]) {
                        processAsset(depAsset);
                    }
                });
            }
            
            if (!processed[asset.handle]) {
                sorted.push(asset);
                processed[asset.handle] = true;
            }
            
            delete processing[asset.handle];
        }
        
        assetsList.forEach(processAsset);
        return sorted;
    }

    /**
     * Load assets from server response
     * 
     * Loads CSS and JS files specified in the AJAX response.
     * This ensures all required assets are loaded for the current page.
     * 
     * CRITICAL: Checks for existing assets more carefully to avoid duplicates.
     * Respects dependency order for correct loading sequence.
     */
    function loadAssetsFromResponse(assets, callback) {
        if (!assets || (typeof sawGlobal === 'undefined')) {
            console.warn('⚠️ No assets or sawGlobal undefined');
            if (callback) callback();
            return;
        }

        let cssLoaded = 0;
        let jsLoaded = 0;
        const totalCss = assets.css ? assets.css.length : 0;
        const totalJs = assets.js ? assets.js.length : 0;
        const total = totalCss + totalJs;

        console.log('📦 Loading assets:', { css: totalCss, js: totalJs });

        if (total === 0) {
            console.warn('⚠️ No assets to load');
            if (callback) callback();
            return;
        }

        function checkComplete() {
            if (cssLoaded === totalCss && jsLoaded === totalJs) {
                console.log('  ✅ All assets loaded (' + totalCss + ' CSS, ' + totalJs + ' JS)');
                if (callback) callback();
            }
        }

        // Load CSS files - respect dependencies order
        if (assets.css && assets.css.length > 0) {
            // Sort CSS by dependencies to ensure correct load order
            const sortedCss = sortAssetsByDependencies(assets.css);
            
            sortedCss.forEach(function(asset) {
                // Better check for existing CSS - check by src URL or handle in href
                const srcCheck = asset.src.replace(/\?.*$/, ''); // Remove query string for comparison
                const exists = $('link[href*="' + srcCheck + '"], link[href="' + asset.src + '"], link[href*="' + asset.handle + '"], link[data-saw-asset="' + asset.handle + '"]').length > 0;
                
                if (exists) {
                    console.log('  ⏭️ CSS already loaded:', asset.handle);
                    cssLoaded++;
                    checkComplete();
                    return;
                }

                // Load CSS
                $('<link>')
                    .attr('rel', 'stylesheet')
                    .attr('type', 'text/css')
                    .attr('href', asset.src)
                    .attr('data-saw-asset', asset.handle)
                    .appendTo('head');
                console.log('  ✅ Loaded CSS:', asset.handle, asset.src);
                cssLoaded++;
                checkComplete();
            });
        } else {
            cssLoaded = totalCss;
        }
        
        // CRITICAL: After loading assets, initialize TinyMCE for content module
        // This is needed because wp_editor() is called in PHP but TinyMCE needs JS initialization
        if (assets.css && assets.css.some(function(a) { return a.handle === 'saw-module-content'; })) {
            // Wait a bit for all assets to load, then initialize TinyMCE
            setTimeout(function() {
                initTinyMCE();
            }, 500);
        }

        // Load JS files
        if (assets.js && assets.js.length > 0) {
            assets.js.forEach(function(asset) {
                // Better check for existing JS - check by src URL or handle in src
                const srcCheck = asset.src.replace(/\?.*$/, ''); // Remove query string for comparison
                const exists = $('script[src*="' + srcCheck + '"], script[src="' + asset.src + '"], script[src*="' + asset.handle + '"]').length > 0;
                
                if (exists) {
                    console.log('  ⏭️ JS already loaded:', asset.handle);
                    jsLoaded++;
                    checkComplete();
                    return;
                }

                // Load JS
                $.getScript(asset.src)
                    .done(function() {
                        console.log('  ✅ Loaded JS:', asset.handle, asset.src);
                        jsLoaded++;
                        checkComplete();
                    })
                    .fail(function() {
                        console.warn('  ⚠️ Failed to load JS:', asset.handle, asset.src);
                        jsLoaded++;
                        checkComplete();
                    });
            });
        } else {
            jsLoaded = totalJs;
        }
    }

    /**
     * Initialize TinyMCE editors
     */
    function initTinyMCE() {
        if (typeof tinymce !== 'undefined' && typeof wp !== 'undefined' && wp.editor) {
            console.log('  🔧 Initializing TinyMCE editors for content module...');
            // Find all textareas that should be TinyMCE editors
            $('textarea.wp-editor-area').each(function() {
                var editorId = $(this).attr('id');
                if (editorId && !tinymce.get(editorId)) {
                    try {
                        // Use WordPress editor initialization
                        if (wp.editor.initialize) {
                            wp.editor.initialize(editorId, {
                                tinymce: {
                                    toolbar1: 'formatselect,bold,italic,underline,strikethrough,forecolor,backcolor,bullist,numlist,alignleft,aligncenter,alignright,link,unlink',
                                    toolbar2: 'undo,redo,removeformat,code,hr,blockquote,subscript,superscript,charmap,indent,outdent,pastetext,searchreplace,fullscreen',
                                    block_formats: 'Odstavec=p;Nadpis 1=h1;Nadpis 2=h2;Nadpis 3=h3;Nadpis 4=h4;Citace=blockquote'
                                }
                            });
                        }
                    } catch (e) {
                        console.warn('  ⚠️ Failed to initialize TinyMCE for:', editorId, e);
                    }
                }
            });
        }
    }

    /**
     * Ensure admin-table CSS/JS are loaded (global component needed by many modules)
     * NOTE: This is now handled by the server response, but kept for fallback
     */
    function ensureAdminTableAssets(callback) {
        if (typeof sawGlobal === 'undefined') {
            if (callback) callback();
            return;
        }

        // Admin-table assets are now loaded via server response
        // This function is kept for backward compatibility
        if (callback) callback();
    }

    /**
     * Re-initialize module-specific scripts
     */
    function reinitializeModuleScripts(moduleName) {
        console.log('🔄 Re-initializing module:', moduleName);
        $(document).trigger('saw:module-reinit', [moduleName]);
    }

    /**
     * Show navigation error UI
     */
    function showNavigationError(url) {
        const $content = $('#saw-app-content');
        $content.html(
            '<div class="saw-navigation-error" style="padding: 3rem; text-align: center; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">' +
            '<div style="font-size: 4rem; margin-bottom: 1rem;">⚠️</div>' +
            '<h2 style="color: #dc2626; margin-bottom: 1rem;">Chyba při načítání stránky</h2>' +
            '<p style="margin-bottom: 2rem; color: #64748b;">Nepodařilo se načíst obsah. Zkuste to prosím znovu.</p>' +
            '<div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">' +
            '<button onclick="SAW_Navigation.navigateTo(\'' + url + '\')" style="padding: 0.75rem 1.5rem; background: #1a73e8; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 1rem;">🔄 Zkusit znovu</button>' +
            '<button onclick="window.location.reload()" style="padding: 0.75rem 1.5rem; background: #64748b; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 1rem;">🔃 Obnovit stránku</button>' +
            '</div>' +
            '</div>'
        );
    }

    window.SAW_Navigation = {
        navigateTo: function(url) { navigateToPage(url, 0); },
        reload: function() { navigateToPage(window.location.pathname, 0); },
        reinitializeScripts: function() { reinitializePageScripts(); },
        setActiveByMenuId: function(menuId) { updateActiveMenuItem(menuId); },
        openSidebar: function() { openMobileSidebar(); },
        closeSidebar: function() { closeMobileSidebar(); }
    };

})(jQuery);
