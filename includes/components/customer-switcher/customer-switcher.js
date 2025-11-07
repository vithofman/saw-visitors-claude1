/**
 * SAW Customer Switcher - JavaScript
 *
 * Handles customer selection dropdown with AJAX loading, search filtering,
 * and customer switching functionality for super admins.
 *
 * @package SAW_Visitors
 * @since   4.7.0
 * @version 1.0.0
 */

(function($) {
    'use strict';
    
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
    
    /**
     * Initialize on document ready
     *
     * @since 4.7.0
     */
    $(document).ready(function() {
        new CustomerSwitcher();
    });
    
})(jQuery);