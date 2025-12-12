/**
 * SAW View Transition
 * 
 * Smooth page transitions using View Transition API (modern browsers)
 * or fallback overlay (older browsers).
 * 
 * @package SAW_Visitors
 * @version 3.0.0 - CRITICAL FIX: Disable startViewTransition on Mobile/PWA
 * 
 * CHANGELOG:
 * - 3.0.0: Mobile/PWA uses direct navigation (no startViewTransition) to prevent freeze
 * - 2.0.0: Simplified - no snapshot, just dark overlay
 */

(function($) {
    'use strict';

    /**
     * Detect if running on mobile device or PWA standalone mode
     * 
     * @return {boolean}
     */
    function isMobileOrPWA() {
        var isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        var isPWA = window.matchMedia('(display-mode: standalone)').matches;
        return isMobile || isPWA;
    }

    /**
     * SAW View Transition Class
     * 
     * Handles smooth page transitions with state preservation.
     */
    class SAWViewTransition {
        constructor() {
            // CRITICAL: On mobile/PWA, disable View Transition API completely
            // Pixel devices freeze when startViewTransition is called after resume from background
            this.isMobileOrPWA = isMobileOrPWA();
            this.supportsViewTransition = !this.isMobileOrPWA && ('startViewTransition' in document);
            this.overlay = null;
            this.loadingSpinner = null;
        }

        /**
         * Main navigation function
         * 
         * @param {string} url - URL to navigate to
         * @param {object} options - Optional configuration
         * @return {void}
         */
        navigateTo(url, options = {}) {
            // Validate URL
            if (!url || typeof url !== 'string') {
                console.warn('Invalid URL for navigation:', url);
                return;
            }

            // Save current state before navigation
            this.saveCurrentState();

            // CRITICAL FIX v3.0.0:
            // On Mobile/PWA - use direct navigation WITHOUT any View Transition API
            // This prevents the "white screen freeze" on Pixel devices after resume
            if (this.isMobileOrPWA) {
                window.location.href = url;
                return;
            }

            // Desktop: Use View Transition API if supported, otherwise fallback
            if (this.supportsViewTransition) {
                this.navigateWithViewTransition(url);
            } else {
                this.navigateWithSimpleOverlay(url);
            }
        }

        /**
         * Navigate with View Transition API (Desktop only)
         * 
         * @param {string} url - URL to navigate to
         * @return {void}
         */
        navigateWithViewTransition(url) {
            try {
                document.startViewTransition(() => {
                    window.location.href = url;
                });
            } catch (e) {
                console.warn('View Transition API failed, falling back:', e);
                this.navigateWithSimpleOverlay(url);
            }
        }

        /**
         * Navigate with simple dark overlay (Desktop fallback)
         * 
         * @param {string} url - URL to navigate to
         * @return {void}
         */
        navigateWithSimpleOverlay(url) {
            this.showSimpleOverlay();
            this.showLoadingSpinner();

            // Set timeout for error handling (10 seconds)
            const timeout = setTimeout(() => {
                this.showErrorMessage();
            }, 10000);

            // Clear timeout on page load
            window.addEventListener('load', function() {
                clearTimeout(timeout);
            }, { once: true });

            // Navigate
            window.location.href = url;
        }

        /**
         * Show simple dark overlay (respects dark mode)
         * 
         * @return {void}
         */
        showSimpleOverlay() {
            if (this.overlay) {
                this.overlay.remove();
            }

            const isDark = document.body.getAttribute('data-theme') === 'dark';
            
            const overlay = document.createElement('div');
            overlay.id = 'saw-transition-overlay';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 999999;
                background: ${isDark ? '#1a1d2e' : '#ffffff'};
                pointer-events: none;
                user-select: none;
            `;

            document.body.appendChild(overlay);
            this.overlay = overlay;
        }

        /**
         * Show loading spinner
         * 
         * @return {void}
         */
        showLoadingSpinner() {
            if (this.loadingSpinner) {
                this.loadingSpinner.remove();
            }

            const isDark = document.body.getAttribute('data-theme') === 'dark';
            const borderColor = isDark ? 'rgba(156, 163, 175, 0.3)' : '#e5e7eb';
            const borderTopColor = isDark ? '#60a5fa' : '#3b82f6';
            const textColor = isDark ? '#9ca3af' : '#6b7280';

            const spinner = document.createElement('div');
            spinner.className = 'saw-transition-spinner';
            spinner.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                z-index: 9999999;
                text-align: center;
            `;
            
            spinner.innerHTML = `
                <div class="spinner-icon" style="
                    width: 40px;
                    height: 40px;
                    border: 4px solid ${borderColor};
                    border-top-color: ${borderTopColor};
                    border-radius: 50%;
                    animation: saw-spin 0.8s linear infinite;
                    margin: 0 auto;
                    ${isDark ? 'filter: drop-shadow(0 0 8px rgba(96, 165, 250, 0.3));' : ''}
                "></div>
                <p style="margin-top: 12px; font-size: 14px; color: ${textColor}; font-weight: 500;">Načítání...</p>
            `;

            // Add spin animation if not already present
            if (!$('#saw-transition-spin-style').length) {
                $('<style id="saw-transition-spin-style">@keyframes saw-spin{to{transform:rotate(360deg);}}</style>').appendTo('head');
            }

            document.body.appendChild(spinner);
            this.loadingSpinner = spinner;
        }

        /**
         * Show error message if page load takes too long
         * 
         * @return {void}
         */
        showErrorMessage() {
            if (!this.loadingSpinner) {
                return;
            }

            const isDark = document.body.getAttribute('data-theme') === 'dark';
            const bgColor = isDark ? '#22253a' : '#ffffff';
            const borderColor = isDark ? 'rgba(156, 163, 175, 0.15)' : '#e5e7eb';
            const dangerColor = isDark ? '#f87171' : '#dc2626';
            const primaryColor = isDark ? '#60a5fa' : '#3b82f6';
            const primaryHover = isDark ? '#3b82f6' : '#2563eb';

            const error = document.createElement('div');
            error.className = 'saw-loading-error';
            
            error.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                z-index: 9999999;
                text-align: center;
                background: ${bgColor};
                padding: 2rem;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, ${isDark ? '0.5' : '0.15'});
                border: 1px solid ${borderColor};
            `;
            
            error.innerHTML = `
                <p style="margin-bottom: 1rem; color: ${dangerColor}; font-weight: 500;">⚠️ Načítání trvá déle než obvykle...</p>
                <button onclick="location.reload()" style="
                    padding: 0.75rem 1.5rem;
                    background: ${primaryColor};
                    color: white;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 1rem;
                    font-weight: 600;
                    transition: background-color 0.2s;
                " onmouseover="this.style.background='${primaryHover}'" onmouseout="this.style.background='${primaryColor}'">Zkusit znovu</button>
            `;

            if (this.loadingSpinner) {
                this.loadingSpinner.replaceWith(error);
            }
        }

        /**
         * Save current state before navigation
         * 
         * @return {void}
         */
        saveCurrentState() {
            // Save global scroll position
            if (window.stateManager) {
                window.stateManager.saveScrollPosition(
                    window.location.pathname,
                    window.scrollY
                );
            }

            // Save table state if we're on a table page
            const $table = $('.saw-admin-table');
            if ($table.length) {
                const entity = $table.data('entity');
                if (entity) {
                    const $scrollArea = $('.saw-table-scroll-area');
                    const scrollTop = $scrollArea.length ? $scrollArea.scrollTop() : 0;
                    
                    const $activeRow = $table.find('tbody tr.saw-row-active');
                    const activeRowId = $activeRow.length ? $activeRow.data('id') : null;

                    if (window.stateManager) {
                        window.stateManager.saveTableState(entity, {
                            scrollTop: scrollTop,
                            activeRowId: activeRowId
                        });
                    }
                }
            }
        }
    }

    // Create global instance
    window.viewTransition = new SAWViewTransition();

    // Export for use in other scripts
    if (typeof window.SAW === 'undefined') {
        window.SAW = {};
    }
    window.SAW.ViewTransition = SAWViewTransition;

})(jQuery);