/**
 * SAW App JavaScript
 * 
 * Core application functionality including navigation, UI interactions,
 * and view transition support.
 * 
 * @package SAW_Visitors
 * @version 6.1.0
 * 
 * CHANGELOG:
 * - 6.1.0: View Transitions disabled on Mobile/PWA (fixes Android resume freeze)
 * - 6.0.1: Added .saw-sidebar-close exception
 * - 6.0.0: Added View Transition support with fallback
 */

(function($) {
    'use strict';
    
    // ========================================
    // MOBILE MENU TOGGLE
    // ========================================
    
    function initMobileMenu() {
        const $toggle = $('#sawMobileMenuToggle');
        const $sidebar = $('#sawAppSidebar');
        const $overlay = $('#sawSidebarOverlay');
        
        if (!$toggle.length || !$sidebar.length) {
            return;
        }
        
        $toggle.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            $sidebar.toggleClass('sa-app-sidebar--open');
            $overlay.toggleClass('sa-sidebar-overlay--active');
            $('body').toggleClass('sa-sidebar-open');
        });
        
        $overlay.on('click', function(e) {
            // Don't close if clicking inside sidebar
            if ($(e.target).closest('#sawAppSidebar').length) {
                return;
            }
            $sidebar.removeClass('sa-app-sidebar--open');
            $overlay.removeClass('sa-sidebar-overlay--active');
            $('body').removeClass('sa-sidebar-open');
        });
        
        // Prevent sidebar clicks from closing menu
        $sidebar.on('click', function(e) {
            e.stopPropagation();
        });
    }
    
    // ========================================
    // SIDEBAR ACCORDION NAVIGATION
    // ========================================
    
    // Default open sections (matches PHP config)
    var DEFAULT_OPEN_SECTIONS = ['visits'];
    
    function initSidebarAccordion() {
        // Use vanilla JS with capture phase to ensure we catch the event
        // before any stopPropagation can block it
        
        // Remove old handlers if exist
        if (window._sawSidebarAccordionHandler) {
            document.removeEventListener('click', window._sawSidebarAccordionHandler, true);
        }
        if (window._sawSidebarNavHandler) {
            document.removeEventListener('click', window._sawSidebarNavHandler, true);
        }
        
        // Get current active section from DOM
        var activeSection = getActiveSectionFromDOM();
        
        // Create handler for section toggle
        window._sawSidebarAccordionHandler = function(e) {
            var heading = e.target.closest('.sa-nav-heading[data-section-toggle]');
            
            if (heading) {
                e.preventDefault();
                e.stopPropagation();
                
                var section = heading.closest('.sa-nav-section');
                if (section) {
                    var sectionId = section.getAttribute('data-section-id');
                    var isCollapsed = section.classList.toggle('sa-nav-section--collapsed');
                    
                    // Save state to sessionStorage
                    saveSectionState(sectionId, !isCollapsed);
                    
                    console.log('[SAW Sidebar] Section toggled:', sectionId, isCollapsed ? 'collapsed' : 'expanded');
                }
            }
        };
        
        // Create handler for nav item clicks - collapse other sections
        window._sawSidebarNavHandler = function(e) {
            var navItem = e.target.closest('.sa-nav-item[data-section]');
            
            if (navItem) {
                var clickedSection = navItem.getAttribute('data-section');
                
                // Collapse all OTHER sections (except clicked one and default open)
                document.querySelectorAll('.sa-nav-section[data-section-id]').forEach(function(section) {
                    var sectionId = section.getAttribute('data-section-id');
                    
                    if (sectionId !== clickedSection) {
                        // Collapse if not in default open list
                        if (DEFAULT_OPEN_SECTIONS.indexOf(sectionId) === -1) {
                            section.classList.add('sa-nav-section--collapsed');
                            saveSectionState(sectionId, false);
                        }
                    } else {
                        // Keep clicked section open
                        section.classList.remove('sa-nav-section--collapsed');
                        saveSectionState(sectionId, true);
                    }
                });
                
                console.log('[SAW Sidebar] Navigated to section:', clickedSection);
            }
        };
        
        // Add handlers with capture phase (true)
        document.addEventListener('click', window._sawSidebarAccordionHandler, true);
        document.addEventListener('click', window._sawSidebarNavHandler, true);
        
        // Restore section states from sessionStorage (overrides PHP defaults)
        restoreSectionStates();
        
        console.log('[SAW Sidebar] Accordion initialized, active section:', activeSection);
    }
    
    // Get the section that contains the active menu item
    function getActiveSectionFromDOM() {
        var activeItem = document.querySelector('.sa-nav-item--active[data-section]');
        return activeItem ? activeItem.getAttribute('data-section') : null;
    }
    
    // Save section state to sessionStorage
    function saveSectionState(sectionId, isOpen) {
        if (!sectionId) return;
        try {
            var states = JSON.parse(sessionStorage.getItem('sawSidebarSections') || '{}');
            states[sectionId] = isOpen;
            sessionStorage.setItem('sawSidebarSections', JSON.stringify(states));
        } catch (e) {
            console.warn('[SAW Sidebar] Could not save section state:', e);
        }
    }
    
    // Restore section states from sessionStorage
    function restoreSectionStates() {
        try {
            var states = JSON.parse(sessionStorage.getItem('sawSidebarSections') || '{}');
            var activeSection = getActiveSectionFromDOM();
            
            document.querySelectorAll('.sa-nav-section[data-section-id]').forEach(function(section) {
                var sectionId = section.getAttribute('data-section-id');
                var hasActive = section.hasAttribute('data-has-active');
                
                // Priority:
                // 1. Section with active item is ALWAYS open
                // 2. Saved state from sessionStorage
                // 3. Default (from PHP)
                
                if (hasActive) {
                    section.classList.remove('sa-nav-section--collapsed');
                } else if (states.hasOwnProperty(sectionId)) {
                    if (states[sectionId]) {
                        section.classList.remove('sa-nav-section--collapsed');
                    } else {
                        section.classList.add('sa-nav-section--collapsed');
                    }
                }
                // Otherwise keep PHP default
            });
        } catch (e) {
            console.warn('[SAW Sidebar] Could not restore section states:', e);
        }
    }
    
    // ========================================
    // TOAST NOTIFICATIONS
    // ========================================
    
    window.sawShowToast = function(message, type) {
        type = type || 'success';
        $('.saw-toast').remove();
        
        var $toast = $('<div class="saw-toast saw-toast-' + type + '">' + sawEscapeHtml(message) + '</div>');
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
        var map = {
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
        var toggle = document.getElementById('sawUserMenuToggle');
        var dropdown = document.getElementById('sawUserDropdown');
        
        if (!toggle || !dropdown) {
            return;
        }
        
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('sa-user-dropdown--active');
        });
        
        document.addEventListener('click', function() {
            dropdown.classList.remove('sa-user-dropdown--active');
        });
        
        dropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
    
    // ========================================
    // FORM VALIDATION HELPERS
    // ========================================
    
    window.sawValidateEmail = function(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(String(email).toLowerCase());
    };
    
    window.sawValidatePhone = function(phone) {
        var re = /^(\+420)?[0-9]{9}$/;
        return re.test(String(phone).replace(/\s/g, ''));
    };
    
    // ========================================
    // LOADING STATE HELPERS
    // ========================================
    
    window.sawButtonLoading = function($button, text) {
        text = text || 'Načítám...';
        if (!$button.data('original-text')) {
            $button.data('original-text', $button.html());
        }
        $button.prop('disabled', true).html(
            '<span class="dashicons dashicons-update saw-spin"></span> ' + text
        );
    };
    
    window.sawButtonReset = function($button) {
        var originalText = $button.data('original-text');
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
    // LINK INTERCEPTOR FOR VIEW TRANSITION
    // ========================================
    
    /**
     * Check if View Transition API is available and functional
     */
    function isViewTransitionAvailable() {
        return typeof window.viewTransition !== 'undefined' 
            && window.viewTransition !== null
            && typeof window.viewTransition.navigateTo === 'function';
    }
    
    /**
     * Check if running on mobile device or PWA standalone mode
     * 
     * View Transitions are disabled on these platforms because:
     * - Android aggressively freezes WebView when app goes to background
     * - After resume, the graphics context for View Transitions may be invalid
     * - This causes the "white screen of death" when clicking any link
     */
    function shouldDisableViewTransitions() {
        // Check for mobile device
        var isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        
        // Check for PWA standalone mode
        var isPWA = window.matchMedia('(display-mode: standalone)').matches;
        
        return isMobile || isPWA;
    }
    
    /**
     * Link click handler for view transition navigation
     */
    function linkClickHandler(e) {
        var link = e.target.closest('a[href]');
        
        if (!link) {
            return;
        }

        var href = link.getAttribute('href');
        var $link = $(link);
        
        if (!href) {
            return;
        }
        
        // =============================================
        // EXCLUDED LINKS - Let browser handle normally
        // =============================================
        
        // External links
        if (href.indexOf('http') === 0 && href.indexOf(window.location.host) === -1) {
            return;
        }
        
        // Links with target="_blank"
        if (link.target === '_blank') {
            return;
        }
        
        // Download links
        if (link.hasAttribute('download')) {
            return;
        }
        
        // Hash links (#)
        if (href.indexOf('#') === 0) {
            return;
        }
        
        // WordPress admin links
        if (href.indexOf('wp-admin') !== -1) {
            return;
        }
        
        // mailto: and tel: links
        if (href.indexOf('mailto:') === 0 || href.indexOf('tel:') === 0) {
            return;
        }
        
        // Links with data-no-transition attribute
        if (link.hasAttribute('data-no-transition')) {
            return;
        }
        
        // Links inside admin table (handled by admin-table.js)
        if ($link.closest('.saw-admin-table').length) {
            return;
        }
        
        // Links inside action buttons
        if ($link.closest('.saw-action-buttons').length) {
            return;
        }
        
        // Form submit buttons/links
        if ($link.closest('form').length && link.type === 'submit') {
            return;
        }
        
        // Sidebar close buttons
        if ($link.hasClass('saw-sidebar-close')) {
            return;
        }
        
        // Links with no-ajax data attribute
        if ($link.data('no-ajax') === true) {
            return;
        }
        
        // =============================================
        // VIEW TRANSITION NAVIGATION
        // =============================================
        
        // Check if View Transition is available BEFORE preventing default
        if (!isViewTransitionAvailable()) {
            return;
        }
        
        // Intercept navigation
        e.preventDefault();
        e.stopPropagation();
        
        // Try view transition with fallback to normal navigation
        try {
            window.viewTransition.navigateTo(href);
        } catch (error) {
            window.location.href = href;
        }
    }
    
    /**
     * Initialize link interceptor for view transition navigation
     * 
     * CRITICAL: Disabled on Mobile/PWA to prevent "resume freeze" issue
     * See: https://issues.chromium.org/issues/... (View Transition API instability on Android)
     */
    function initLinkInterceptor() {
        // Check if View Transition API is available
        if (!isViewTransitionAvailable()) {
            return;
        }
        
        // CRITICAL FIX: Disable View Transitions on Mobile/PWA
        // Android aggressively freezes WebView when in background
        // After resume, View Transition callbacks may never resolve -> white screen
        if (shouldDisableViewTransitions()) {
            return;
        }

        // Check if already initialized to prevent duplicates
        if (document._sawLinkInterceptorInitialized) {
            return;
        }
        
        document._sawLinkInterceptorInitialized = true;
        document.addEventListener('click', linkClickHandler, false);
        document._sawLinkClickHandler = linkClickHandler;
    }
    
    /**
     * Remove link interceptor
     */
    function removeLinkInterceptor() {
        if (document._sawLinkClickHandler) {
            document.removeEventListener('click', document._sawLinkClickHandler, false);
            document._sawLinkClickHandler = null;
        }
        document._sawLinkInterceptorInitialized = false;
    }

    // ========================================
    // SCROLL RESTORATION
    // ========================================
    
    function initScrollRestoration() {
        if (typeof window.stateManager === 'undefined') {
            return;
        }

        window.addEventListener('beforeunload', function() {
            if (window.stateManager) {
                window.stateManager.saveScrollPosition(
                    window.location.pathname,
                    window.scrollY
                );
            }
        });

        window.addEventListener('load', function() {
            if (window.stateManager) {
                var scrollY = window.stateManager.restoreScrollPosition(
                    window.location.pathname
                );
                
                if (scrollY !== null && scrollY > 0) {
                    setTimeout(function() {
                        window.scrollTo(0, scrollY);
                    }, 100);
                }
            }
        });
    }

    // ========================================
    // BFCACHE / VISIBILITY HANDLING
    // ========================================
    
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            if (!document.body.classList.contains('loaded')) {
                document.body.classList.add('loaded');
            }
            
            // On desktop only: manage View Transition interceptor
            if (!shouldDisableViewTransitions()) {
                if (!isViewTransitionAvailable()) {
                    removeLinkInterceptor();
                } else if (!document._sawLinkInterceptorInitialized) {
                    initLinkInterceptor();
                }
            }
        }
    });
    
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            // On desktop only: check View Transition availability
            if (!shouldDisableViewTransitions()) {
                if (!isViewTransitionAvailable() && document._sawLinkInterceptorInitialized) {
                    removeLinkInterceptor();
                }
            }
        }
    });

    // ========================================
    // INITIALIZE ON DOM READY
    // ========================================

    $(document).ready(function() {
        initMobileMenu();
        initSidebarAccordion();
        initUserMenu();
        initTableRowHover();
        initLinkInterceptor();
        initScrollRestoration();
        
        document.body.classList.add('loaded');
    });
    
})(jQuery);