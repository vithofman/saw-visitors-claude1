/**
 * SAW View Transition
 * 
 * Smooth page transitions using View Transition API (modern browsers)
 * or fallback overlay (older browsers).
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

(function($) {
    'use strict';

    /**
     * SAW View Transition Class
     * 
     * Handles smooth page transitions with state preservation.
     */
    class SAWViewTransition {
        constructor() {
            this.supportsViewTransition = 'startViewTransition' in document;
            this.snapshotOverlay = null;
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

            // Use View Transition API if supported, otherwise fallback
            if (this.supportsViewTransition) {
                this.navigateWithViewTransition(url);
            } else {
                this.navigateWithOverlay(url);
            }
        }

        /**
         * Navigate with View Transition API (modern browsers)
         * 
         * @param {string} url - URL to navigate to
         * @return {void}
         */
        navigateWithViewTransition(url) {
            try {
                document.startViewTransition(() => {
                    // Browser automatically creates screenshot and handles transition
                    window.location.href = url;
                });
            } catch (e) {
                console.warn('View Transition API failed, falling back:', e);
                this.navigateWithOverlay(url);
            }
        }

        /**
         * Navigate with frozen page overlay (fallback for older browsers)
         * 
         * @param {string} url - URL to navigate to
         * @return {void}
         */
        navigateWithOverlay(url) {
            // Create page snapshot
            const snapshot = this.createPageSnapshot();
            
            // Show snapshot as overlay
            this.showSnapshot(snapshot);
            
            // Show loading spinner
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
         * Create a "frozen" snapshot of the current page
         * 
         * @return {HTMLElement} Cloned body element
         */
        createPageSnapshot() {
            // Clone the entire body
            const body = document.body.cloneNode(true);
            
            // Disable interactions on clone
            body.style.pointerEvents = 'none';
            body.style.userSelect = 'none';
            body.style.cursor = 'wait';
            
            // Remove any existing overlays/modals from snapshot
            $(body).find('.saw-modal-overlay, .modal-backdrop, #saw-loading-overlay').remove();
            
            return body;
        }

        /**
         * Show snapshot as overlay
         * 
         * @param {HTMLElement} snapshot - Cloned body element
         * @return {void}
         */
        showSnapshot(snapshot) {
            // Remove existing overlay if any
            if (this.snapshotOverlay) {
                this.snapshotOverlay.remove();
            }

            // Create overlay container
            const overlay = document.createElement('div');
            overlay.id = 'saw-page-snapshot';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 999999;
                background: var(--saw-body-bg);
                overflow: hidden;
            `;

            overlay.appendChild(snapshot);
            document.body.appendChild(overlay);
            
            this.snapshotOverlay = overlay;

            // Fade out after a delay (when new page loads)
            setTimeout(() => {
                if (this.snapshotOverlay) {
                    this.snapshotOverlay.style.opacity = '0';
                    this.snapshotOverlay.style.transition = 'opacity 0.3s ease-out';
                    
                    setTimeout(() => {
                        if (this.snapshotOverlay) {
                            this.snapshotOverlay.remove();
                            this.snapshotOverlay = null;
                        }
                    }, 300);
                }
            }, 500);
        }

        /**
         * Show loading spinner
         * 
         * @return {void}
         */
        showLoadingSpinner() {
            // Remove existing spinner if any
            if (this.loadingSpinner) {
                this.loadingSpinner.remove();
            }

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
                    border: 4px solid #e5e7eb;
                    border-top-color: #3b82f6;
                    border-radius: 50%;
                    animation: saw-spin 0.8s linear infinite;
                    margin: 0 auto;
                "></div>
                <p style="margin-top: 12px; font-size: 14px; color: #6b7280;">Načítání...</p>
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

            const error = document.createElement('div');
            error.className = 'saw-loading-error';
            error.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                z-index: 9999999;
                text-align: center;
                background: white;
                padding: 2rem;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            `;
            
            error.innerHTML = `
                <p style="margin-bottom: 1rem; color: #dc2626;">⚠️ Načítání trvá déle než obvykle...</p>
                <button onclick="location.reload()" style="
                    padding: 0.75rem 1.5rem;
                    background: #3b82f6;
                    color: white;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 1rem;
                ">Zkusit znovu</button>
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
                    const $tablePanel = $('.saw-table-panel');
                    const scrollTop = $tablePanel.length ? $tablePanel.scrollTop() : 0;
                    
                    // Find active row
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

