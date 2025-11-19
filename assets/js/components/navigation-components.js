/**
 * SAW Navigation Components - Consolidated
 * 
 * All navigation-related JavaScript components including:
 * - Customer Switcher (customer selection dropdown)
 * - Branch Switcher (branch selection dropdown)
 * - Language Switcher (language selection dropdown)
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

(function($) {
    'use strict';

    /* ============================================
       CUSTOMER SWITCHER
       ============================================ */

    /**
     * Customer Switcher Class
     *
     * Manages customer selection dropdown component for super admins.
     *
     * @since 4.7.0
     */
    class CustomerSwitcher {
        
        /**
         * Constructor
         *
         * Initializes customer switcher component and sets up properties.
         *
         * @since 4.7.0
         */
        constructor() {
            this.button = $('#sawCustomerSwitcherButton');
            this.dropdown = $('#sawCustomerSwitcherDropdown');
            this.searchInput = $('#sawCustomerSwitcherSearch');
            this.list = $('#sawCustomerSwitcherList');
            this.customers = [];
            this.filteredCustomers = [];
            this.currentCustomerId = null;
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
            
            if (typeof sawCustomerSwitcher === 'undefined') {
                return;
            }
            
            this.currentCustomerId = parseInt(this.button.data('current-customer-id'));
            
            // Button click
            this.button.on('click', (e) => {
                e.stopPropagation();
                this.toggle();
            });
            
            // Search input
            this.searchInput.on('input', () => {
                this.filterCustomers(this.searchInput.val());
            });
            
            // Close on outside click
            $(document).on('click', (e) => {
                if (!$(e.target).closest('#sawCustomerSwitcher').length) {
                    this.close();
                }
            });
            
            // Close on Escape key
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.close();
                }
            });
        }
        
        /**
         * Toggle dropdown
         *
         * Opens or closes dropdown based on current state.
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
         * Shows dropdown and loads customers if not already loaded.
         *
         * @since 4.7.0
         * @return {void}
         */
        open() {
            this.isOpen = true;
            this.dropdown.addClass('active');
            this.searchInput.focus();
            
            if (this.customers.length === 0) {
                this.loadCustomers();
            }
        }
        
        /**
         * Close dropdown
         *
         * Hides dropdown and resets search.
         *
         * @since 4.7.0
         * @return {void}
         */
        close() {
            this.isOpen = false;
            this.dropdown.removeClass('active');
            this.searchInput.val('');
            this.filteredCustomers = this.customers;
        }
        
        /**
         * Load customers via AJAX
         *
         * Fetches list of customers from server.
         *
         * @since 4.7.0
         * @return {void}
         */
        loadCustomers() {
            if (this.isLoading) return;
            
            this.isLoading = true;
            this.showLoading();
            
            $.ajax({
                url: sawCustomerSwitcher.ajaxurl,
                type: 'POST',
                data: {
                    action: 'saw_get_customers_for_switcher',
                    nonce: sawCustomerSwitcher.nonce
                },
                success: (response) => {
                    this.isLoading = false;
                    
                    if (response.success && response.data && response.data.customers) {
                        this.customers = response.data.customers;
                        this.filteredCustomers = this.customers;
                        
                        if (response.data.current_customer_id) {
                            this.currentCustomerId = parseInt(response.data.current_customer_id);
                        }
                        
                        this.renderCustomers();
                    } else {
                        this.showError(response.data?.message || 'Nepodařilo se načíst zákazníky');
                    }
                },
                error: (xhr, status, error) => {
                    this.isLoading = false;
                    this.showError('Chyba serveru při načítání zákazníků');
                }
            });
        }
        
        /**
         * Filter customers by search query
         *
         * Filters customers by name or IČO.
         *
         * @since 4.7.0
         * @param {string} query Search query
         * @return {void}
         */
        filterCustomers(query) {
            if (!query) {
                this.filteredCustomers = this.customers;
            } else {
                const q = query.toLowerCase();
                this.filteredCustomers = this.customers.filter(customer => {
                    return customer.name.toLowerCase().includes(q) ||
                           (customer.ico && customer.ico.includes(q));
                });
            }
            
            this.renderCustomers();
        }
        
        /**
         * Render customers list
         *
         * Generates HTML for customer items and attaches event handlers.
         *
         * @since 4.7.0
         * @return {void}
         */
        renderCustomers() {
            if (this.filteredCustomers.length === 0) {
                this.list.html('<div class="saw-switcher-empty">Žádní zákazníci nenalezeni</div>');
                return;
            }
            
            let html = '';
            
            this.filteredCustomers.forEach(customer => {
                const isActive = customer.id === this.currentCustomerId;
                const activeClass = isActive ? 'active' : '';
                
                html += `
                    <div class="saw-switcher-item ${activeClass}" data-customer-id="${customer.id}">
                        <div class="saw-switcher-item-logo">
                            ${customer.logo_url ? 
                                `<img src="${this.escapeHtml(customer.logo_url)}" alt="${this.escapeHtml(customer.name)}">` : 
                                `<svg width="36" height="36" viewBox="0 0 40 40" fill="none" class="saw-switcher-item-logo-fallback">
                                    <rect width="40" height="40" rx="8" fill="${customer.primary_color || '#2563eb'}"/>
                                    <text x="20" y="28" font-size="20" font-weight="bold" fill="white" text-anchor="middle">
                                        ${this.escapeHtml(customer.name.charAt(0).toUpperCase())}
                                    </text>
                                </svg>`
                            }
                        </div>
                        <div class="saw-switcher-item-info">
                            <div class="saw-switcher-item-name">${this.escapeHtml(customer.name)}</div>
                            ${customer.ico ? `<div class="saw-switcher-item-ico">IČO: ${this.escapeHtml(customer.ico)}</div>` : ''}
                        </div>
                        ${isActive ? '<div class="saw-switcher-item-check">✓</div>' : ''}
                    </div>
                `;
            });
            
            this.list.html(html);
            
            // Attach click handlers
            this.list.find('.saw-switcher-item').on('click', (e) => {
                const customerId = parseInt($(e.currentTarget).data('customer-id'));
                this.switchCustomer(customerId);
            });
        }
        
        /**
         * Switch to different customer
         *
         * Changes active customer via AJAX and reloads page.
         *
         * @since 4.7.0
         * @param {number} customerId Customer ID to switch to
         * @return {void}
         */
        switchCustomer(customerId) {
            if (customerId === this.currentCustomerId) {
                this.close();
                return;
            }
            
            this.button.prop('disabled', true);
            
            $.ajax({
                url: sawCustomerSwitcher.ajaxurl,
                type: 'POST',
                data: {
                    action: 'saw_switch_customer',
                    customer_id: customerId,
                    nonce: sawCustomerSwitcher.nonce
                },
                success: (response) => {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        this.showNotification(response.data?.message || 'Chyba při přepínání zákazníka', 'error');
                        this.button.prop('disabled', false);
                    }
                },
                error: (xhr, status, error) => {
                    this.showNotification('Chyba serveru při přepínání zákazníka', 'error');
                    this.button.prop('disabled', false);
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
                <div class="saw-switcher-loading">
                    <div class="saw-spinner"></div>
                    <span>Načítání zákazníků...</span>
                </div>
            `);
        }
        
        /**
         * Show error state
         *
         * Displays error message in dropdown.
         *
         * @since 4.7.0
         * @param {string} message Error message
         * @return {void}
         */
        showError(message) {
            this.list.html(`
                <div class="saw-switcher-error">${this.escapeHtml(message)}</div>
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
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    }

    /* ============================================
       BRANCH SWITCHER
       ============================================ */

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
         * Auto-reloads page if branch was auto-selected by server.
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
                    
                    // AUTO-SELECT: Reload page if branch was auto-selected
                    if (response.data.auto_selected === true) {
                        window.location.reload();
                        return;
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

    /* ============================================
       LANGUAGE SWITCHER
       ============================================ */

    /**
     * Language Switcher Class
     * 
     * Manages language selection dropdown and AJAX-based language switching.
     * 
     * @class
     * @since 4.7.0
     */
    class LanguageSwitcher {
        /**
         * Constructor
         * 
         * Initializes the language switcher with DOM elements and state.
         * 
         * @since 4.7.0
         */
        constructor() {
            this.button = $('#sawLanguageSwitcherButton');
            this.dropdown = $('#sawLanguageSwitcherDropdown');
            this.currentLanguage = null;
            this.isOpen = false;
            
            this.init();
        }
        
        /**
         * Initialize component
         * 
         * Sets up event listeners and validates required elements.
         * 
         * @since 4.7.0
         * @return {void}
         */
        init() {
            if (!this.button.length || !this.dropdown.length) {
                return;
            }
            
            if (typeof sawLanguageSwitcher === 'undefined') {
                return;
            }
            
            this.currentLanguage = this.button.data('current-language');
            
            // Button click
            this.button.on('click', (e) => {
                e.stopPropagation();
                this.toggle();
            });
            
            // Language item click
            this.dropdown.find('.saw-language-item').on('click', (e) => {
                const language = $(e.currentTarget).data('language');
                this.switchLanguage(language);
            });
            
            // Outside click
            $(document).on('click', (e) => {
                if (!$(e.target).closest('#sawLanguageSwitcher').length) {
                    this.close();
                }
            });
            
            // ESC key
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.close();
                }
            });
        }
        
        /**
         * Toggle dropdown
         * 
         * Opens dropdown if closed, closes if open.
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
         * Displays the language selection dropdown.
         * 
         * @since 4.7.0
         * @return {void}
         */
        open() {
            this.isOpen = true;
            this.dropdown.addClass('active');
        }
        
        /**
         * Close dropdown
         * 
         * Hides the language selection dropdown.
         * 
         * @since 4.7.0
         * @return {void}
         */
        close() {
            this.isOpen = false;
            this.dropdown.removeClass('active');
        }
        
        /**
         * Switch language
         * 
         * Sends AJAX request to switch the current language and reloads
         * the page on success.
         * 
         * @since 4.7.0
         * @param {string} language - Language code to switch to
         * @return {void}
         */
        switchLanguage(language) {
            if (language === this.currentLanguage) {
                this.close();
                return;
            }
            
            this.button.prop('disabled', true);
            
            $.ajax({
                url: sawLanguageSwitcher.ajaxurl,
                type: 'POST',
                data: {
                    action: 'saw_switch_language',
                    language: language,
                    nonce: sawLanguageSwitcher.nonce
                },
                success: (response) => {
                    if (response && response.success) {
                        window.location.reload();
                    } else {
                        const message = (response && response.data && response.data.message) 
                            ? response.data.message 
                            : 'Chyba při přepínání jazyka';
                        alert(message);
                        this.button.prop('disabled', false);
                    }
                },
                error: (xhr, status, error) => {
                    let message = 'Chyba serveru (status: ' + xhr.status + ')';
                    
                    if (xhr.status === 0) {
                        message = 'Síťová chyba - zkontrolujte připojení';
                    } else if (xhr.status === 400) {
                        message = 'Chybný požadavek (400) - problém s daty nebo nonce';
                    } else if (xhr.status === 403) {
                        message = 'Nedostatečná oprávnění (403)';
                    } else if (xhr.status === 404) {
                        message = 'AJAX endpoint nenalezen (404)';
                    }
                    
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response && response.data && response.data.message) {
                            message = response.data.message;
                        }
                    } catch (e) {
                        // Could not parse error response
                    }
                    
                    alert(message);
                    this.button.prop('disabled', false);
                }
            });
        }
    }

    /* ============================================
       INITIALIZATION
       ============================================ */

    /**
     * Initialize all navigation components
     * Can be called on document ready or after AJAX page load
     * 
     * @since 1.0.0
     */
    function initNavigationComponents() {
        console.log('[Navigation Components] initNavigationComponents() called');
        
        // Initialize customer switcher
        if ($('#sawCustomerSwitcherButton').length && !window.customerSwitcher) {
            console.log('[Navigation Components] Initializing customer switcher');
            window.customerSwitcher = new CustomerSwitcher();
        }
        
        // Initialize branch switcher
        if ($('#sawBranchSwitcher').length && !window.branchSwitcher) {
            console.log('[Navigation Components] Initializing branch switcher');
            window.branchSwitcher = new BranchSwitcher();
        }
        
        // Initialize language switcher
        if ($('#sawLanguageSwitcher').length && !window.languageSwitcher) {
            console.log('[Navigation Components] Initializing language switcher');
            window.languageSwitcher = new LanguageSwitcher();
        } else if ($('#sawLanguageSwitcher').length && window.languageSwitcher) {
            console.log('[Navigation Components] Language switcher already exists, reinitializing...');
            // Reinitialize if already exists
            try {
                window.languageSwitcher = new LanguageSwitcher();
            } catch (e) {
                console.error('[Navigation Components] Error reinitializing language switcher:', e);
            }
        }
    }

    // Initialize on document ready
    $(document).ready(function() {
        initNavigationComponents();
    });

    // Re-initialize after AJAX page load
    $(document).on('saw:page-loaded saw:scripts-reinitialized', function(e, data) {
        console.log('[Navigation Components] Event received:', e.type, 'Data:', data);
        // Reinitialize navigation components after AJAX navigation
        initNavigationComponents();
    });

})(jQuery);

