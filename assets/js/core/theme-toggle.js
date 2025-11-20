/**
 * SAW Theme Toggle - Dark Mode
 * 
 * Handles dark mode toggle with localStorage and user meta synchronization
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

(function($) {
    'use strict';
    
    /**
     * Initialize theme toggle
     */
    function initThemeToggle() {
        // Check localStorage first (for instant application)
        const localStorageDark = localStorage.getItem('saw_dark_mode') === '1';
        
        // Check if body already has data-theme (from server-side)
        const bodyTheme = document.body.getAttribute('data-theme');
        const serverDark = bodyTheme === 'dark';
        
        // Apply dark mode if enabled in localStorage or server
        if (localStorageDark || serverDark) {
            applyDarkMode(true);
        }
        
        // Toggle handler
        $('#saw-dark-mode-toggle').on('change', function() {
            const enabled = $(this).is(':checked');
            toggleDarkMode(enabled);
        });
    }
    
    /**
     * Toggle dark mode
     * 
     * @param {boolean} enabled - Whether dark mode should be enabled
     */
    function toggleDarkMode(enabled) {
        // Apply immediately via localStorage
        if (enabled) {
            localStorage.setItem('saw_dark_mode', '1');
        } else {
            localStorage.removeItem('saw_dark_mode');
        }
        
        applyDarkMode(enabled);
        
        // Save to server (user meta)
        if (typeof sawAjaxNonce !== 'undefined') {
            $.ajax({
                url: sawAjaxUrl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'saw_toggle_dark_mode',
                    nonce: sawAjaxNonce,
                    enabled: enabled ? '1' : '0'
                },
                success: function(response) {
                    if (response.success) {
                        console.log('✅ Dark mode preference saved');
                    }
                },
                error: function() {
                    console.warn('⚠️ Failed to save dark mode preference');
                }
            });
        }
    }
    
    /**
     * Apply dark mode to page
     * 
     * @param {boolean} enabled - Whether dark mode should be enabled
     */
    function applyDarkMode(enabled) {
        if (enabled) {
            document.body.setAttribute('data-theme', 'dark');
            // Reload dark mode CSS if needed
            loadDarkModeCSS();
        } else {
            document.body.removeAttribute('data-theme');
        }
    }
    
    /**
     * Load dark mode CSS dynamically if not already loaded
     */
    function loadDarkModeCSS() {
        // Check if dark mode CSS is already loaded
        if ($('#saw-app-dark-css').length) {
            return;
        }
        
        // Load dark mode CSS files
        const darkCssFiles = [
            { id: 'saw-app-dark-css', href: sawPluginUrl + 'assets/css/app/app-dark.css' },
            { id: 'saw-sidebar-dark-css', href: sawPluginUrl + 'assets/css/components/sidebar-dark.css' }
        ];
        
        darkCssFiles.forEach(function(file) {
            if (!$('#' + file.id).length) {
                $('<link>')
                    .attr({
                        id: file.id,
                        rel: 'stylesheet',
                        href: file.href + '?v=' + (sawVersion || Date.now())
                    })
                    .appendTo('head');
            }
        });
        
        // Load module-specific dark CSS if on module page
        const activeModule = $('body').data('module');
        if (activeModule) {
            const moduleDarkCss = sawPluginUrl + 'assets/css/modules/' + activeModule + '/' + activeModule + '-dark.css';
            const moduleCssId = 'saw-' + activeModule + '-dark-css';
            
            if (!$('#' + moduleCssId).length) {
                $('<link>')
                    .attr({
                        id: moduleCssId,
                        rel: 'stylesheet',
                        href: moduleDarkCss + '?v=' + (sawVersion || Date.now())
                    })
                    .appendTo('head');
            }
        }
    }
    
    /**
     * Initialize on DOM ready
     */
    $(document).ready(function() {
        initThemeToggle();
    });
    
})(jQuery);

