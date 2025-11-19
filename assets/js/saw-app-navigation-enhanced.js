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

                    // Load module-specific assets
                    setTimeout(function () {
                        const moduleName = detectModuleFromURL(url);
                        if (moduleName) {
                            console.log('🔧 Loading module:', moduleName);
                            loadModuleAssets(moduleName, function () {
                                reinitializePageScripts();
                                reinitializeModuleScripts(moduleName);
                            });
                        } else {
                            reinitializePageScripts();
                        }
                    }, 200);
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
     */
    function enhancedCleanup() {
        const $content = $('#saw-app-content');
        if (!$content.length) return;

        console.log('🧹 Enhanced cleanup...');

        // Remove modals
        $content.find('[id*="saw-modal-"], .saw-modal').remove();
        $('.saw-modal-overlay, .modal-backdrop').not('#sawSidebarOverlay').remove();

        // Remove inline styles
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

        // Remove old module assets
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
     */
    function loadModuleAssets(moduleName, callback) {
        if (!moduleName || typeof sawGlobal === 'undefined') {
            if (callback) callback();
            return;
        }

        const cssUrl = sawGlobal.pluginUrl + 'assets/css/modules/saw-' + moduleName + '.css';
        const jsUrl = sawGlobal.pluginUrl + 'assets/js/modules/saw-' + moduleName + '.js';

        // Load CSS if not already loaded
        if (!$('link[href*="saw-' + moduleName + '.css"]').length) {
            $('<link>')
                .attr('rel', 'stylesheet')
                .attr('href', cssUrl + '?v=' + sawGlobal.version)
                .attr('data-saw-module', moduleName)
                .appendTo('head');
            console.log('  ✅ Loaded CSS:', moduleName);
        }

        // Load JS if not already loaded
        if (!$('script[src*="saw-' + moduleName + '.js"]').length) {
            $.getScript(jsUrl + '?v=' + sawGlobal.version)
                .done(function () {
                    console.log('  ✅ Loaded JS:', moduleName);
                    $(document).trigger('saw:module-loaded', [moduleName]);
                    if (callback) callback();
                })
                .fail(function () {
                    console.warn('  ⚠️ Failed to load JS for:', moduleName);
                    if (callback) callback();
                });
        } else {
            if (callback) callback();
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
