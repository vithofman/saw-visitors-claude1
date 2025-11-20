/**
 * SAW State Manager
 * 
 * Centralized state management for scroll position, table state, and form data.
 * Uses sessionStorage for persistence across page reloads.
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

(function($) {
    'use strict';

    /**
     * SAW State Manager Class
     * 
     * Manages application state persistence using sessionStorage.
     */
    class SAWStateManager {
        constructor() {
            // Timeout for scroll/table state (5 minutes)
            this.STATE_TIMEOUT = 5 * 60 * 1000; // 5 minutes in milliseconds
        }

        /**
         * Save scroll position for a given identifier (URL or table)
         * 
         * @param {string} identifier - URL pathname or table identifier
         * @param {number} scrollTop - Scroll position
         * @return {void}
         */
        saveScrollPosition(identifier, scrollTop) {
            if (!this.supportsSessionStorage()) {
                return;
            }

            const scrollData = {
                scrollTop: scrollTop,
                url: window.location.pathname,
                timestamp: Date.now()
            };

            try {
                sessionStorage.setItem(
                    'saw_scroll_' + identifier,
                    JSON.stringify(scrollData)
                );
            } catch (e) {
                console.warn('Failed to save scroll position:', e);
            }
        }

        /**
         * Restore scroll position for a given identifier
         * 
         * @param {string} identifier - URL pathname or table identifier
         * @return {number|null} Scroll position or null if not found/expired
         */
        restoreScrollPosition(identifier) {
            if (!this.supportsSessionStorage()) {
                return null;
            }

            try {
                const data = sessionStorage.getItem('saw_scroll_' + identifier);
                if (!data) {
                    return null;
                }

                const parsed = JSON.parse(data);

                // Check if data is expired (older than 5 minutes)
                if (Date.now() - parsed.timestamp > this.STATE_TIMEOUT) {
                    sessionStorage.removeItem('saw_scroll_' + identifier);
                    return null;
                }

                return parsed.scrollTop;
            } catch (e) {
                console.warn('Failed to restore scroll position:', e);
                return null;
            }
        }

        /**
         * Save table state (scroll position + active row ID)
         * 
         * @param {string} entity - Entity name (e.g., 'companies', 'customers')
         * @param {object} state - State object with scrollTop and activeRowId
         * @return {void}
         */
        saveTableState(entity, state) {
            if (!this.supportsSessionStorage()) {
                return;
            }

            const tableData = {
                scrollTop: state.scrollTop || 0,
                activeRowId: state.activeRowId || null,
                url: window.location.pathname,
                timestamp: Date.now()
            };

            try {
                sessionStorage.setItem(
                    'saw_table_' + entity,
                    JSON.stringify(tableData)
                );
            } catch (e) {
                console.warn('Failed to save table state:', e);
            }
        }

        /**
         * Restore table state (scroll position + active row ID)
         * 
         * @param {string} entity - Entity name
         * @return {object|null} State object or null if not found/expired
         */
        restoreTableState(entity) {
            if (!this.supportsSessionStorage()) {
                return null;
            }

            try {
                const data = sessionStorage.getItem('saw_table_' + entity);
                if (!data) {
                    return null;
                }

                const parsed = JSON.parse(data);

                // Check if data is expired (older than 5 minutes)
                if (Date.now() - parsed.timestamp > this.STATE_TIMEOUT) {
                    sessionStorage.removeItem('saw_table_' + entity);
                    return null;
                }

                return {
                    scrollTop: parsed.scrollTop,
                    activeRowId: parsed.activeRowId
                };
            } catch (e) {
                console.warn('Failed to restore table state:', e);
                return null;
            }
        }

        /**
         * Save form data to sessionStorage
         * 
         * @param {string} formId - Form ID or identifier
         * @param {array|object} data - Form data (serialized array or object)
         * @return {void}
         */
        saveFormData(formId, data) {
            if (!this.supportsSessionStorage()) {
                return;
            }

            const formData = {
                data: data,
                url: window.location.pathname,
                timestamp: Date.now()
            };

            try {
                sessionStorage.setItem(
                    'saw_form_' + formId,
                    JSON.stringify(formData)
                );
            } catch (e) {
                console.warn('Failed to save form data:', e);
            }
        }

        /**
         * Restore form data from sessionStorage
         * 
         * @param {string} formId - Form ID or identifier
         * @return {array|object|null} Form data or null if not found
         */
        restoreFormData(formId) {
            if (!this.supportsSessionStorage()) {
                return null;
            }

            try {
                const data = sessionStorage.getItem('saw_form_' + formId);
                if (!data) {
                    return null;
                }

                const parsed = JSON.parse(data);
                return parsed.data;
            } catch (e) {
                console.warn('Failed to restore form data:', e);
                return null;
            }
        }

        /**
         * Check if form data exists for given form ID
         * 
         * @param {string} formId - Form ID or identifier
         * @return {boolean} True if form data exists
         */
        hasFormData(formId) {
            if (!this.supportsSessionStorage()) {
                return false;
            }

            try {
                return sessionStorage.getItem('saw_form_' + formId) !== null;
            } catch (e) {
                return false;
            }
        }

        /**
         * Clear form data from sessionStorage
         * 
         * @param {string} formId - Form ID or identifier
         * @return {void}
         */
        clearFormData(formId) {
            if (!this.supportsSessionStorage()) {
                return;
            }

            try {
                sessionStorage.removeItem('saw_form_' + formId);
            } catch (e) {
                console.warn('Failed to clear form data:', e);
            }
        }

        /**
         * Check if sessionStorage is supported
         * 
         * @return {boolean} True if sessionStorage is available
         */
        supportsSessionStorage() {
            try {
                const test = '__saw_test__';
                sessionStorage.setItem(test, test);
                sessionStorage.removeItem(test);
                return true;
            } catch (e) {
                return false;
            }
        }

        /**
         * Clear all SAW state data (for debugging/testing)
         * 
         * @return {void}
         */
        clearAll() {
            if (!this.supportsSessionStorage()) {
                return;
            }

            try {
                const keys = Object.keys(sessionStorage);
                keys.forEach(function(key) {
                    if (key.indexOf('saw_') === 0) {
                        sessionStorage.removeItem(key);
                    }
                });
            } catch (e) {
                console.warn('Failed to clear all state:', e);
            }
        }
    }

    // Create global instance
    window.stateManager = new SAWStateManager();

    // Export for use in other scripts
    if (typeof window.SAW === 'undefined') {
        window.SAW = {};
    }
    window.SAW.StateManager = SAWStateManager;

})(jQuery);

