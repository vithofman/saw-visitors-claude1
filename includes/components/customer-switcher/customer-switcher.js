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
            
            if (typeof sawCustomerSwitcher === 'undefined') {
                console.error('SAW Customer Switcher: sawCustomerSwitcher object not found');
                return;
            }
            
            this.currentCustomerId = parseInt(this.button.data('current-customer-id'));
            
            this.button.on('click', (e) => {
                e.stopPropagation();
                this.toggle();
            });
            
            this.searchInput.on('input', () => {
                this.filterCustomers(this.searchInput.val());
            });
            
            $(document).on('click', (e) => {
                if (!$(e.target).closest('#sawCustomerSwitcher').length) {
                    this.close();
                }
            });
            
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
                    console.error('Customer Switcher AJAX Error:', status, error);
                    this.showError('Chyba serveru při načítání zákazníků');
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
            
            this.list.find('.saw-switcher-item').on('click', (e) => {
                const customerId = parseInt($(e.currentTarget).data('customer-id'));
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
                error: (xhr, status, error) => {
                    console.error('Customer Switch Error:', status, error);
                    alert('Chyba serveru při přepínání zákazníka');
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
                <div class="saw-switcher-error">${this.escapeHtml(message)}</div>
            `);
        }
        
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
    
    $(document).ready(function() {
        new CustomerSwitcher();
    });
    
})(jQuery);