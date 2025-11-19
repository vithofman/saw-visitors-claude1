/**
 * SAW App Navigation - Enhanced Features
 * 
 * Extends the base navigation with:
 * - Retry mechanism for failed requests
 * - Dynamic module asset loading
 * - Enhanced cleanup
 * - Better error handling
 * 
 * @package SAW_Visitors
 * @version 6.0.0 - ENHANCED NAVIGATION
 */

(function ($) {
    'use strict';

    // Wait for original navigation to initialize
    $(document).ready(function () {
        console.log('🚀 SAW Enhanced Navigation: Loaded');

        // Store reference to original navigateToPage
        const originalNavigateToPage = window.SAW_Navigation ? window.SAW_Navigation.navigateTo : null;

        if (!originalNavigateToPage) {
            console.warn('⚠️ Original navigation not found, enhanced features disabled');
            return;
        }

        // Override navigateToPage with enhanced version
        window.SAW_Navigation.navigateTo = function (url) {
            enhancedNavigateToPage(url, 0);
        };

        console.log('✅ Enhanced navigation features activated');
    });

    /**
     * Enhanced navigateToPage with retry and module loading
     */
    function enhancedNavigateToPage(url, retryCount) {
        // Get isNavigating from parent scope or create our own
        if (window.SAW_isNavigating) {
            return;
        }

        retryCount = retryCount || 0;
        window.SAW_isNavigating = true;

        showLoading();
        $('html, body').animate({ scrollTop: 0 }, 300);

        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function (response) {
                if (response && response.success && response.data) {
                    console.log('📦 Enhanced Navigation: Success');

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
                        loadAssetsFromResponse(response.data.assets, function () {
                            reinitializePageScripts();
                            const moduleName = detectModuleFromURL(url);
                            if (moduleName) {
                                reinitializeModuleScripts(moduleName);
                            }
                        });
                    } else {
                        // Fallback: try to detect and load module assets
                        setTimeout(function () {
                            const moduleName = detectModuleFromURL(url);
                            if (moduleName) {
                                console.log('🔧 Loading module (fallback):', moduleName);
                                loadModuleAssets(moduleName, function () {
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
                    fallbackToFullPageLoad(url);
                }
            },
            error: function (xhr, status, error) {
                console.error('❌ Navigation Error:', status);

                // Retry mechanism
                if (retryCount < 2) {
                    console.log('🔄 Retrying... (' + (retryCount + 1) + '/2)');
                    window.SAW_isNavigating = false;
                    hideLoading();
                    setTimeout(function () {
                        enhancedNavigateToPage(url, retryCount + 1);
                    }, 1000);
                    return;
                }

                // Show error UI
                showNavigationError(url);
                window.SAW_isNavigating = false;
                hideLoading();
            },
            complete: function () {
                if (retryCount < 2) {
                    window.SAW_isNavigating = false;
                    hideLoading();
                }
            }
        });
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

        // Remove modals
        $content.find('[id*="saw-modal-"], .saw-modal').remove();
        $('.saw-modal-overlay, .modal-backdrop').not('#sawSidebarOverlay').remove();

        // Remove inline styles (module-specific only)
        $('style[id*="saw-module-css-"]').remove();

        // Destroy TinyMCE editors
        if (typeof tinymce !== 'undefined') {
            tinymce.editors.forEach(function (editor) {
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

        const cssUrl = sawGlobal.pluginUrl + 'assets/css/modules/saw-' + moduleName + '.css';
        const jsUrl = sawGlobal.pluginUrl + 'assets/js/modules/saw-' + moduleName + '.js';

        let cssLoaded = false;
        let jsLoaded = false;
        let adminTableLoaded = false;

        // Load module CSS if not already loaded
        if (!$('link[href*="saw-' + moduleName + '.css"]').length) {
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
        if (!$('script[src*="saw-' + moduleName + '.js"]').length) {
            $.getScript(jsUrl + '?v=' + sawGlobal.version)
                .done(function () {
                    console.log('  ✅ Loaded JS:', moduleName);
                    jsLoaded = true;
                    $(document).trigger('saw:module-loaded', [moduleName]);
                    checkAndCallback();
                })
                .fail(function () {
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
            
            sortedCss.forEach(function (asset) {
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
            }, 500);
        }

        // Load JS files
        if (assets.js && assets.js.length > 0) {
            assets.js.forEach(function (asset) {
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
                    .done(function () {
                        console.log('  ✅ Loaded JS:', asset.handle, asset.src);
                        jsLoaded++;
                        checkComplete();
                    })
                    .fail(function () {
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
     * Ensure admin-table CSS/JS are loaded (global component needed by many modules)
     */
    function ensureAdminTableAssets(callback) {
        if (typeof sawGlobal === 'undefined') {
            if (callback) callback();
            return;
        }

        let cssLoaded = false;
        let jsLoaded = false;
        let sidebarCssLoaded = false;
        let sidebarJsLoaded = false;
        let callbackFired = false;

        function checkAndCallback() {
            if (cssLoaded && jsLoaded && sidebarCssLoaded && sidebarJsLoaded && !callbackFired) {
                callbackFired = true;
                if (callback) callback();
            }
        }

        // Check and load admin-table.css
        const adminTableCssExists = $('link[href*="admin-table.css"][href*="components"]').length > 0 ||
                                    $('link[href*="admin-table-component"]').length > 0;
        if (!adminTableCssExists) {
            const adminTableCssUrl = sawGlobal.pluginUrl + 'includes/components/admin-table/admin-table.css';
            $('<link>')
                .attr('rel', 'stylesheet')
                .attr('href', adminTableCssUrl + '?v=' + sawGlobal.version)
                .attr('data-saw-global', 'admin-table')
                .appendTo('head');
            console.log('  ✅ Loaded admin-table CSS');
        }
        cssLoaded = true;
        checkAndCallback();

        // Check and load admin-table.js
        const adminTableJsExists = $('script[src*="admin-table.js"][src*="components"]').length > 0 ||
                                   $('script[src*="admin-table-component"]').length > 0;
        if (!adminTableJsExists) {
            const adminTableJsUrl = sawGlobal.pluginUrl + 'includes/components/admin-table/admin-table.js';
            $.getScript(adminTableJsUrl + '?v=' + sawGlobal.version)
                .done(function () {
                    console.log('  ✅ Loaded admin-table JS');
                    jsLoaded = true;
                    checkAndCallback();
                })
                .fail(function () {
                    console.warn('  ⚠️ Failed to load admin-table JS');
                    jsLoaded = true;
                    checkAndCallback();
                });
        } else {
            jsLoaded = true;
            checkAndCallback();
        }

        // Check and load sidebar.css (more specific check)
        const sidebarCssUrl = sawGlobal.pluginUrl + 'includes/components/admin-table/sidebar.css';
        const sidebarCssExists = $('link[href*="admin-table/sidebar.css"]').length > 0 || 
                                 $('link[href*="admin-table-sidebar"]').length > 0;
        if (!sidebarCssExists) {
            $('<link>')
                .attr('rel', 'stylesheet')
                .attr('href', sidebarCssUrl + '?v=' + sawGlobal.version)
                .attr('data-saw-global', 'admin-table-sidebar')
                .appendTo('head');
            console.log('  ✅ Loaded admin-table sidebar CSS');
        }
        sidebarCssLoaded = true;
        checkAndCallback();

        // Check and load sidebar.js (more specific check)
        const sidebarJsUrl = sawGlobal.pluginUrl + 'includes/components/admin-table/sidebar.js';
        const sidebarJsExists = $('script[src*="admin-table/sidebar.js"]').length > 0 || 
                               $('script[src*="admin-table-sidebar"]').length > 0;
        if (!sidebarJsExists) {
            $.getScript(sidebarJsUrl + '?v=' + sawGlobal.version)
                .done(function () {
                    console.log('  ✅ Loaded admin-table sidebar JS');
                    sidebarJsLoaded = true;
                    checkAndCallback();
                })
                .fail(function () {
                    console.warn('  ⚠️ Failed to load admin-table sidebar JS');
                    sidebarJsLoaded = true;
                    checkAndCallback();
                });
        } else {
            sidebarJsLoaded = true;
            checkAndCallback();
        }
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

    // Helper functions that might not exist in original
    function showLoading() {
        $('#saw-page-loading').addClass('active');
    }

    function hideLoading() {
        setTimeout(function () {
            $('#saw-page-loading').removeClass('active');
        }, 200);
    }

    function cleanupPageScopedAssets() {
        $('link[data-saw-scope="page"], style[data-saw-scope="page"]').each(function () {
            try { this.parentNode.removeChild(this); } catch (e) { }
        });
    }

    function updatePageContent(data) {
        const $content = $('#saw-app-content');
        if (!$content.length) return;

        $content.css('opacity', '0');

        setTimeout(function () {
            $content.html(data.content || '');

            if (data.title) {
                document.title = data.title + ' - SAW Visitors';
            }

            $content.css('opacity', '1');
            $(document).trigger('saw:page-loaded', [data]);
        }, 150);
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

        // Fallback logic would go here if needed
    }

    function reinitializePageScripts() {
        $('#saw-app-content').find('script').each(function () {
            const scriptContent = $(this).html();
            if (!scriptContent || !scriptContent.trim()) return;

            try {
                const script = document.createElement('script');
                script.text = scriptContent;
                document.head.appendChild(script).parentNode.removeChild(script);
            } catch (e) {
                console.error('Error executing script:', e);
            }
        });

        $(document).trigger('saw:scripts-reinitialized');
    }

    function fallbackToFullPageLoad(url) {
        window.location.href = url;
    }

})(jQuery);
