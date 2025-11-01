/**
 * SAW Customer Switcher - JavaScript
 * 
 * @package SAW_Visitors
 * @since 4.7.0
 */

(function($) {
    'use strict';
    
    class CustomerSwitcher {
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
        
        init() {
            if (!this.button.length || !this.dropdown.length) {
                return;
            }
            
            this.currentCustomerId = this.button.data('current-customer-id');
            
            // Button click
            this.button.on('click', (e) => {
                e.stopPropagation();
                this.toggle();
            });
            
            // Search input
            this.searchInput.on('input', () => {
                this.filterCustomers(this.searchInput.val());
            });
            
            // Outside click
            $(document).on('click', (e) => {
                if (!$(e.target).closest('#sawCustomerSwitcher').length) {
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
        
        toggle() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        }
        
        open() {
            this.isOpen = true;
            this.dropdown.addClass('active');
            this.searchInput.focus();
            
            // Load customers if not loaded
            if (this.customers.length === 0) {
                this.loadCustomers();
            }
        }
        
        close() {
            this.isOpen = false;
            this.dropdown.removeClass('active');
            this.searchInput.val('');
            this.filteredCustomers = this.customers;
        }
        
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
                    
                    if (response.success && response.data) {
                        this.customers = response.data;
                        this.filteredCustomers = this.customers;
                        this.renderCustomers();
                    } else {
                        this.showError('Nepodařilo se načíst zákazníky');
                    }
                },
                error: () => {
                    this.isLoading = false;
                    this.showError('Chyba serveru');
                }
            });
        }
        
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
                                `<img src="${customer.logo_url}" alt="${customer.name}">` : 
                                `<div class="saw-switcher-item-logo-fallback">
                                    <svg width="24" height="24" viewBox="0 0 40 40" fill="none">
                                        <rect width="40" height="40" rx="8" fill="#2563eb"/>
                                        <text x="20" y="28" font-size="20" font-weight="bold" fill="white" text-anchor="middle">
                                            ${customer.name.charAt(0).toUpperCase()}
                                        </text>
                                    </svg>
                                </div>`
                            }
                        </div>
                        <div class="saw-switcher-item-info">
                            <div class="saw-switcher-item-name">${customer.name}</div>
                            ${customer.ico ? `<div class="saw-switcher-item-ico">IČO: ${customer.ico}</div>` : ''}
                        </div>
                        ${isActive ? '<div class="saw-switcher-item-check">✓</div>' : ''}
                    </div>
                `;
            });
            
            this.list.html(html);
            
            // Attach click handlers
            this.list.find('.saw-switcher-item').on('click', (e) => {
                const customerId = $(e.currentTarget).data('customer-id');
                this.switchCustomer(customerId);
            });
        }
        
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
                        alert(response.data?.message || 'Chyba při přepínání zákazníka');
                        this.button.prop('disabled', false);
                    }
                },
                error: () => {
                    alert('Chyba serveru');
                    this.button.prop('disabled', false);
                }
            });
        }
        
        showLoading() {
            this.list.html(`
                <div class="saw-switcher-loading">
                    <div class="saw-spinner"></div>
                    <span>Načítání zákazníků...</span>
                </div>
            `);
        }
        
        showError(message) {
            this.list.html(`
                <div class="saw-switcher-error">${message}</div>
            `);
        }
    }
    
    // Initialize
    $(document).ready(function() {
        new CustomerSwitcher();
    });
    
})(jQuery);
