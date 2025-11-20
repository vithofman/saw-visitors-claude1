/**
 * SAW App JavaScript - HOTFIX EDITION
 * 
 * HOTFIX v5.4.2:
 * - ODSTRANĚN: Delete button handler (přesunut výhradně do sidebar.js)
 * - Opraveno: Duplicitní delete handlers
 * - Opraveno: Dvakrát confirm dialog
 * 
 * @package SAW_Visitors
 * @version 5.4.2 - HOTFIX: Removed duplicate delete handler
 */

(function($) {
    'use strict';
    
    // ========================================
    // MOBILE MENU TOGGLE
    // ========================================
    
    function initMobileMenu() {
        $('#sawMobileMenuToggle').on('click', function() {
            $('#sawAppSidebar').toggleClass('open');
            $('#sawSidebarOverlay').toggleClass('active');
        });
        
        $('#sawSidebarClose, #sawSidebarOverlay').on('click', function() {
            $('#sawAppSidebar').removeClass('open');
            $('#sawSidebarOverlay').removeClass('active');
        });
    }
    
    // ========================================
    // SIDEBAR ACCORDION NAVIGATION
    // ========================================
    
    function initSidebarAccordion() {
        $(document).on('click', '.saw-nav-heading', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $section = $(this).closest('.saw-nav-section');
            $section.toggleClass('collapsed');
        });
    }
    
    // ========================================
    // DELETE CONFIRMATION
    // ✅ HOTFIX: REMOVED - Delete handler je nyní pouze v sidebar.js
    // Důvod: Duplicitní handlers způsobovaly 2x confirm dialog a 2x AJAX request
    // ========================================
    
    // DELETE HANDLER JE NYNÍ POUZE V: includes/components/admin-table/sidebar.js
    // Řádek ~280-380 v sidebar.js: initDeleteButton()
    
    // ========================================
    // TOAST NOTIFICATIONS
    // ========================================
    
    window.sawShowToast = function(message, type = 'success') {
        $('.saw-toast').remove();
        
        const $toast = $('<div class="saw-toast saw-toast-' + type + '">' + sawEscapeHtml(message) + '</div>');
        $('body').append($toast);
        
        $toast[0].offsetHeight;
        
        setTimeout(function() {
            $toast.addClass('saw-toast-show');
        }, 10);
        
        setTimeout(function() {
            $toast.removeClass('saw-toast-show');
            setTimeout(function() {
                $toast.remove();
            }, 300);
        }, 3000);
    };
    
    window.sawEscapeHtml = function(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
    };
    
    // ========================================
    // USER MENU DROPDOWN
    // ========================================
    
    function initUserMenu() {
        const toggle = document.getElementById('sawUserMenuToggle');
        const dropdown = document.getElementById('sawUserDropdown');
        
        if (!toggle || !dropdown) {
            return;
        }
        
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('active');
        });
        
        document.addEventListener('click', function() {
            dropdown.classList.remove('active');
        });
        
        dropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
    
    // ========================================
    // FORM VALIDATION HELPERS
    // ========================================
    
    window.sawValidateEmail = function(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(String(email).toLowerCase());
    };
    
    window.sawValidatePhone = function(phone) {
        const re = /^(\+420)?[0-9]{9}$/;
        return re.test(String(phone).replace(/\s/g, ''));
    };
    
    // ========================================
    // LOADING STATE HELPERS
    // ========================================
    
    window.sawButtonLoading = function($button, text = 'Načítám...') {
        if (!$button.data('original-text')) {
            $button.data('original-text', $button.html());
        }
        $button.prop('disabled', true).html(
            '<span class="dashicons dashicons-update saw-spin"></span> ' + text
        );
    };
    
    window.sawButtonReset = function($button) {
        const originalText = $button.data('original-text');
        if (originalText) {
            $button.prop('disabled', false).html(originalText);
        }
    };
    
    // ========================================
    // CONFIRMATION DIALOGS
    // ========================================
    
    window.sawConfirm = function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    };
    
    // ========================================
    // TABLE HELPERS
    // ========================================
    
    function initTableRowHover() {
        $('.saw-admin-table tbody tr').hover(
            function() {
                $(this).addClass('saw-row-hover');
            },
            function() {
                $(this).removeClass('saw-row-hover');
            }
        );
    }
    
    // ========================================
    // INITIALIZE ON DOM READY
    // ========================================
    
    /**
     * Cleanup WordPress editor CSS/JS if not on content page
     * 
     * This ensures that WordPress editor assets from previous navigation
     * are removed when loading a non-content page (e.g., after F5 reload).
     * 
     * @since 5.1.0
     */
    function cleanupWordPressEditorAssets() {
        // Check if we're on content page
        const isContentPage = window.location.pathname.indexOf('/admin/content') !== -1 || 
                              window.location.pathname.indexOf('/content') !== -1;
        
        if (isContentPage) {
            // On content page, keep WordPress editor assets
            return;
        }
        
        // Remove WordPress editor CSS/JS
        const wordpressEditorPatterns = [
            '/wp-includes/css/tinymce',
            '/wp-includes/css/editor',
            '/wp-includes/css/media',
            '/wp-includes/js/tinymce',
            '/wp-includes/js/editor'
        ];
        
        let removedCount = 0;
        wordpressEditorPatterns.forEach(function(pattern) {
            $('link[href*="' + pattern + '"], script[src*="' + pattern + '"]').each(function() {
                const $el = $(this);
                const href = $el.attr('href') || $el.attr('src') || '';
                if (href.indexOf(pattern) !== -1) {
                    console.log('🧹 Removing WordPress editor asset on page load:', href);
                    $el.remove();
                    removedCount++;
                }
            });
        });
        
        if (removedCount > 0) {
            console.log('✅ Cleaned up ' + removedCount + ' WordPress editor asset(s)');
        }
    }

    // ========================================
    // LINK INTERCEPTOR FOR VIEW TRANSITION
    // ========================================
    
    /**
     * Initialize link interceptor for view transition navigation
     * 
     * Intercepts all internal links and uses view transition instead of normal navigation.
     * 
     * @since 6.0.0
     */
    function initLinkInterceptor() {
        // Only initialize if viewTransition is available
        if (typeof window.viewTransition === 'undefined') {
            console.warn('⚠️ View Transition not available, link interceptor disabled');
            return;
        }

        document.addEventListener('click', function(e) {
            // Find closest link element
            const link = e.target.closest('a[href]');
            
            if (!link) {
                return;
            }

            const href = link.getAttribute('href');
            
            // Ignore external links
            if (href.startsWith('http') && !href.includes(window.location.host)) {
                return;
            }
            
            // Ignore links with target="_blank"
            if (link.target === '_blank') {
                return;
            }
            
            // Ignore download links
            if (link.hasAttribute('download')) {
                return;
            }
            
            // Ignore hash links (#)
            if (href.startsWith('#')) {
                return;
            }
            
            // Ignore WordPress admin links
            if (href.includes('wp-admin')) {
                return;
            }
            
            // Ignore mailto: and tel: links
            if (href.startsWith('mailto:') || href.startsWith('tel:')) {
                return;
            }
            
            // Ignore links with data-no-transition attribute
            if (link.hasAttribute('data-no-transition')) {
                return;
            }
            
            // Ignore links inside admin table (handled by admin-table.js)
            if ($(link).closest('.saw-admin-table').length) {
                return;
            }
            
            // Ignore links inside action buttons
            if ($(link).closest('.saw-action-buttons').length) {
                return;
            }
            
            // Ignore form submit buttons/links
            if ($(link).closest('form').length && link.type === 'submit') {
                return;
            }
            
            // Intercept and use view transition
            e.preventDefault();
            e.stopPropagation();
            
            // Use view transition for navigation
            window.viewTransition.navigateTo(href);
        }, false); // Use bubble phase (not capture)
    }

    // ========================================
    // SCROLL RESTORATION
    // ========================================
    
    /**
     * Initialize scroll restoration
     * 
     * Saves scroll position before page unload and restores it after page load.
     * 
     * @since 6.0.0
     */
    function initScrollRestoration() {
        // Only initialize if stateManager is available
        if (typeof window.stateManager === 'undefined') {
            console.warn('⚠️ State Manager not available, scroll restoration disabled');
            return;
        }

        // Save scroll position before page unload
        window.addEventListener('beforeunload', function() {
            if (window.stateManager) {
                window.stateManager.saveScrollPosition(
                    window.location.pathname,
                    window.scrollY
                );
            }
        });

        // Restore scroll position after page load
        window.addEventListener('load', function() {
            if (window.stateManager) {
                const scrollY = window.stateManager.restoreScrollPosition(
                    window.location.pathname
                );
                
                if (scrollY !== null && scrollY > 0) {
                    setTimeout(function() {
                        window.scrollTo(0, scrollY);
                        console.log('📜 Scroll position restored:', scrollY);
                    }, 100);
                }
            }
        });
    }

    $(document).ready(function() {
        initMobileMenu();
        initSidebarAccordion();
        initUserMenu();
        initTableRowHover();
        
        // Initialize link interceptor for view transition
        initLinkInterceptor();
        
        // Initialize scroll restoration
        initScrollRestoration();
        
        // Cleanup WordPress editor assets if not on content page
        cleanupWordPressEditorAssets();
        
        document.body.classList.add('loaded');
        
        if (sawGlobal.debug) {
            console.log('🚀 SAW App initialized v6.0.0 - View Transition', {
                sawGlobal: typeof sawGlobal !== 'undefined',
                jQuery: !!$,
                modalSystem: typeof SAWModal !== 'undefined',
                viewTransition: typeof window.viewTransition !== 'undefined',
                stateManager: typeof window.stateManager !== 'undefined'
            });
        }
    });
    
})(jQuery);