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
            // Timeout for form data (5 minutes)
            this.FORM_DATA_TIMEOUT = 5 * 60 * 1000; // 5 minutes in milliseconds
            
            // Cleanup expired form data on initialization
            this.cleanupExpiredFormData();
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

            // Get full URL including query string
            const fullUrl = window.location.pathname + window.location.search;

            const formData = {
                data: data,
                url: fullUrl,
                pathname: window.location.pathname, // For backward compatibility
                timestamp: Date.now()
            };

            try {
                sessionStorage.setItem(
                    'saw_form_' + formId,
                    JSON.stringify(formData)
                );
            } catch (e) {
                console.warn('[StateManager] Failed to save form data:', e);
            }
        }

        /**
         * Restore form data from sessionStorage
         * 
         * @param {string} formId - Form ID or identifier
         * @return {array|object|null} Form data or null if not found/expired/invalid
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
                
                // Validate timestamp
                if (!parsed.timestamp || (Date.now() - parsed.timestamp > this.FORM_DATA_TIMEOUT)) {
                    console.log('[StateManager] Form data expired for:', formId);
                    sessionStorage.removeItem('saw_form_' + formId);
                    return null;
                }
                
                // Validate URL match (pathname + search)
                const currentUrl = window.location.pathname + window.location.search;
                if (parsed.url !== currentUrl) {
                    console.log('[StateManager] URL mismatch for form data:', formId, 'Expected:', parsed.url, 'Got:', currentUrl);
                    sessionStorage.removeItem('saw_form_' + formId);
                    return null;
                }
                
                // Validate data is not empty
                if (!parsed.data || (Array.isArray(parsed.data) && parsed.data.length === 0)) {
                    console.log('[StateManager] Form data is empty for:', formId);
                    sessionStorage.removeItem('saw_form_' + formId);
                    return null;
                }
                
                return parsed.data;
            } catch (e) {
                console.warn('[StateManager] Failed to restore form data:', e);
                // Remove corrupted data
                try {
                    sessionStorage.removeItem('saw_form_' + formId);
                } catch (e2) {
                    // Ignore
                }
                return null;
            }
        }

        /**
         * Check if form data exists for given form ID
         * 
         * Validates:
         * - Data exists
         * - Timestamp is valid (< 5 minutes)
         * - URL matches (pathname + search)
         * - Data is not empty
         * 
         * @param {string} formId - Form ID or identifier
         * @return {boolean} True if form data exists and is valid
         */
        hasFormData(formId) {
            if (!this.supportsSessionStorage()) {
                return false;
            }

            try {
                const data = sessionStorage.getItem('saw_form_' + formId);
                if (!data) {
                    return false;
                }

                const parsed = JSON.parse(data);
                
                // Check timestamp
                if (!parsed.timestamp || (Date.now() - parsed.timestamp > this.FORM_DATA_TIMEOUT)) {
                    console.log('[StateManager] Form data expired for:', formId);
                    sessionStorage.removeItem('saw_form_' + formId);
                    return false;
                }
                
                // Check URL match
                const currentUrl = window.location.pathname + window.location.search;
                if (parsed.url !== currentUrl) {
                    console.log('[StateManager] URL mismatch for form data:', formId);
                    sessionStorage.removeItem('saw_form_' + formId);
                    return false;
                }
                
                // Check data is not empty
                if (!parsed.data || (Array.isArray(parsed.data) && parsed.data.length === 0)) {
                    console.log('[StateManager] Form data is empty for:', formId);
                    sessionStorage.removeItem('saw_form_' + formId);
                    return false;
                }
                
                return true;
            } catch (e) {
                console.warn('[StateManager] Failed to check form data:', e);
                // Remove corrupted data
                try {
                    sessionStorage.removeItem('saw_form_' + formId);
                } catch (e2) {
                    // Ignore
                }
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
         * Cleanup expired form data
         * 
         * Removes all expired form data entries from sessionStorage
         * 
         * @return {void}
         */
        cleanupExpiredFormData() {
            if (!this.supportsSessionStorage()) {
                return;
            }

            try {
                const keys = Object.keys(sessionStorage);
                const now = Date.now();
                
                keys.forEach((key) => {
                    if (key.indexOf('saw_form_') === 0) {
                        try {
                            const data = sessionStorage.getItem(key);
                            if (data) {
                                const parsed = JSON.parse(data);
                                
                                // Remove if expired
                                if (!parsed.timestamp || (now - parsed.timestamp > this.FORM_DATA_TIMEOUT)) {
                                    sessionStorage.removeItem(key);
                                    console.log('[StateManager] Cleaned up expired form data:', key);
                                }
                            }
                        } catch (e) {
                            // Remove corrupted data
                            sessionStorage.removeItem(key);
                            console.log('[StateManager] Cleaned up corrupted form data:', key);
                        }
                    }
                });
            } catch (e) {
                console.warn('[StateManager] Failed to cleanup expired form data:', e);
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
                console.warn('[StateManager] Failed to clear all state:', e);
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

