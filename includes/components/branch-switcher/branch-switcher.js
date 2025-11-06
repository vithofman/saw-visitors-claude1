/**
 * SAW Branch Switcher - JavaScript
 *
 * Handles branch selection dropdown with AJAX loading and context switching.
 * Manages dropdown state, event handlers, and branch switching logic.
 *
 * @package SAW_Visitors
 * @since   4.7.0
 * @version 3.0.1
 */

(function($) {
    'use strict';
    
    /**
     * Branch Switcher Class
     *
     * Manages the branch selection dropdown component in the admin sidebar.
     *
     * @since 4.7.0
     */
    window.BranchSwitcher = class BranchSwitcher {
        
        /**
         * Constructor
         *
         * Initializes the branch switcher component and sets up properties.
         *
         * @since 4.7.0
         */
        constructor() {
            this.container = $('#sawBranchSwitcher');
            this.button = $('#sawBranchSwitcherButton');
            this.dropdown = $('#sawBranchSwitcherDropdown');
            this.list = $('#sawBranchSwitcherList');
            this.branches = [];
            this.customerId = null;
            this.currentBranchId = null;
            this.isOpen = false;
            this.isLoading = false;
            
            this.init();
        }
        
        /**
         * Initialize component
         *
         * Sets up event listeners and validates configuration.
         *
         * @since 4.7.0
         * @return {void}
         */
        init() {
            if (!this.button.length || !this.dropdown.length) {
                return;
            }
            
            if (typeof sawBranchSwitcher === 'undefined') {
                return;
            }
            
            // Get customer ID from data attribute
            this.customerId = parseInt(this.container.data('customer-id'));
            this.currentBranchId = parseInt(this.button.data('current-branch-id')) || null;
            
            // Validate customer ID
            if (!this.customerId || this.customerId === 0 || isNaN(this.customerId)) {
                this.showError('Neplatné ID zákazníka');
                return;
            }
            
            // Event listeners
            this.button.on('click', (e) => {
                e.stopPropagation();
                this.toggle();
            });
            
            $(document).on('click', (e) => {
                if (!$(e.target).closest('#sawBranchSwitcher').length) {
                    this.close();
                }
            });
            
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.close();
                }
            });
        }
        
        /**
         * Toggle dropdown
         *
         * Opens or closes the dropdown based on current state.
         *
         * @since 4.7.0
         * @return {void}
         */
        toggle() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        }
        
        /**
         * Open dropdown
         *
         * Shows the dropdown and loads branches if not already loaded.
         *
         * @since 4.7.0
         * @return {void}
         */
        open() {
            this.isOpen = true;
            this.dropdown.addClass('active');
            
            if (this.branches.length === 0) {
                this.loadBranches();
            }
        }
        
        /**
         * Close dropdown
         *
         * Hides the dropdown.
         *
         * @since 4.7.0
         * @return {void}
         */
        close() {
            this.isOpen = false;
            this.dropdown.removeClass('active');
        }
        
        /**
         * Load branches via AJAX
         *
         * Fetches branches for current customer from server.
         *
         * @since 4.7.0
         * @return {void}
         */
        loadBranches() {
            if (this.isLoading) {
                return;
            }
            
            if (!this.customerId) {
                this.showError('Chybí ID zákazníka');
                return;
            }
            
            this.isLoading = true;
            this.showLoading();
            
            $.ajax({
                url: sawBranchSwitcher.ajaxurl,
                type: 'POST',
                data: {
                    action: 'saw_get_branches_for_switcher',
                    customer_id: this.customerId,
                    nonce: sawBranchSwitcher.nonce
                },
                success: (response) => {
                    this.isLoading = false;
                    
                    // Validate response structure
                    if (!response || !response.success) {
                        const message = (response && response.data && response.data.message) 
                            ? response.data.message 
                            : 'Chyba načítání poboček';
                        this.showError(message);
                        return;
                    }
                    
                    if (!response.data) {
                        this.showError('Chybí data v odpovědi');
                        return;
                    }
                    
                    if (!response.data.branches) {
                        this.showError('Chybí seznam poboček');
                        return;
                    }
                    
                    // Ensure branches is an array
                    if (!Array.isArray(response.data.branches)) {
                        if (typeof response.data.branches === 'object') {
                            response.data.branches = Object.values(response.data.branches);
                        } else {
                            this.showError('Neplatný formát dat');
                            return;
                        }
                    }
                    
                    this.branches = response.data.branches;
                    
                    if (response.data.current_branch_id) {
                        this.currentBranchId = parseInt(response.data.current_branch_id);
                    }
                    
                    this.renderBranches();
                },
                error: (xhr, status, error) => {
                    this.isLoading = false;
                    
                    let message = 'Chyba serveru';
                    
                    if (xhr.status === 400) {
                        message = 'Chybný požadavek';
                    } else if (xhr.status === 403) {
                        message = 'Nedostatečná oprávnění';
                    } else if (xhr.status === 404) {
                        message = 'Endpoint nenalezen';
                    } else if (xhr.status === 0) {
                        message = 'Nelze se připojit k serveru';
                    }
                    
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response && response.data && response.data.message) {
                            message = response.data.message;
                        }
                    } catch (e) {
                        // Cannot parse response
                    }
                    
                    this.showError(message);
                }
            });
        }
        
        /**
         * Render branches list
         *
         * Generates HTML for branches dropdown and attaches event handlers.
         *
         * @since 4.7.0
         * @return {void}
         */
        renderBranches() {
            if (this.branches.length === 0) {
                this.list.html(`
                    <div class="saw-branch-empty">
                        <p>Zákazník nemá žádné pobočky</p>
                    </div>
                `);
                return;
            }
            
            let html = '';
            
            this.branches.forEach(branch => {
                const isActive = branch.id === this.currentBranchId;
                const activeClass = isActive ? 'active' : '';
                
                html += `
                    <div class="saw-branch-item ${activeClass}" data-branch-id="${branch.id}">
                        <span class="saw-branch-item-icon">🏢</span>
                        <div class="saw-branch-item-info">
                            <div class="saw-branch-item-name">${this.escapeHtml(branch.name)}</div>
                            ${branch.address ? `<div class="saw-branch-item-address">${this.escapeHtml(branch.address)}</div>` : ''}
                        </div>
                        ${isActive ? '<span class="saw-branch-item-check">✓</span>' : ''}
                    </div>
                `;
            });
            
            this.list.html(html);
            
            // Attach click handlers
            this.list.find('.saw-branch-item').on('click', (e) => {
                const branchId = parseInt($(e.currentTarget).data('branch-id'));
                this.switchBranch(branchId);
            });
        }
        
        /**
         * Switch to different branch
         *
         * Changes active branch via AJAX and reloads page.
         *
         * @since 4.7.0
         * @param {number} branchId Branch ID to switch to
         * @return {void}
         */
        switchBranch(branchId) {
            $.ajax({
                url: sawBranchSwitcher.ajaxurl,
                type: 'POST',
                data: {
                    action: 'saw_switch_branch',
                    branch_id: branchId,
                    nonce: sawBranchSwitcher.nonce
                },
                success: (response) => {
                    if (response && response.success) {
                        window.location.reload();
                    } else {
                        const message = (response && response.data && response.data.message) 
                            ? response.data.message 
                            : 'Chyba přepnutí pobočky';
                        this.showNotification(message, 'error');
                    }
                },
                error: (xhr) => {
                    this.showNotification('Chyba serveru při přepínání pobočky', 'error');
                }
            });
        }
        
        /**
         * Show loading state
         *
         * Displays loading spinner in dropdown.
         *
         * @since 4.7.0
         * @return {void}
         */
        showLoading() {
            this.list.html(`
                <div class="saw-branch-loading">
                    <div class="saw-spinner"></div>
                    <span>Načítání...</span>
                </div>
            `);
        }
        
        /**
         * Show error state
         *
         * Displays error message in dropdown.
         *
         * @since 4.7.0
         * @param {string} message Error message to display
         * @return {void}
         */
        showError(message) {
            this.list.html(`
                <div class="saw-branch-error">
                    <p>✕ ${this.escapeHtml(message)}</p>
                </div>
            `);
        }
        
        /**
         * Show notification
         *
         * Displays notification message (uses alert as fallback).
         * Override this method to use custom notification system.
         *
         * @since 4.7.0
         * @param {string} message Notification message
         * @param {string} type    Notification type (success, error, warning)
         * @return {void}
         */
        showNotification(message, type) {
            // TODO: Replace with custom notification system
            alert(message);
        }
        
        /**
         * Escape HTML
         *
         * Prevents XSS by escaping HTML special characters.
         *
         * @since 4.7.0
         * @param {string} text Text to escape
         * @return {string} Escaped text
         */
        escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    };
    
    /**
     * Initialize on document ready
     *
     * @since 4.7.0
     */
    $(document).ready(function() {
        if ($('#sawBranchSwitcher').length) {
            window.branchSwitcher = new BranchSwitcher();
        }
    });
    
})(jQuery);