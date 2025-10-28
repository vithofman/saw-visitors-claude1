/**
 * SAW Customer Switcher JavaScript
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 */

(function($) {
    'use strict';
    
    class CustomerSwitcher {
        constructor() {
            this.button = $('#sawCustomerSwitcherButton');
            this.dropdown = null;
            this.customers = [];
            this.currentCustomerId = null;
            this.isLoading = false;
            
            this.init();
        }
        
        init() {
            if (!this.button.length) {
                console.log('SAW Customer Switcher: Button not found');
                return;
            }
            
            console.log('SAW Customer Switcher: Initializing...');
            
            // Create dropdown
            this.createDropdown();
            
            // Bind events
            this.button.on('click', (e) => {
                e.stopPropagation();
                this.toggleDropdown();
            });
            
            // Close on outside click
            $(document).on('click', (e) => {
                if (!$(e.target).closest('.saw-customer-switcher').length) {
                    this.closeDropdown();
                }
            });
            
            // ESC key to close
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.closeDropdown();
                }
            });
            
            console.log('SAW Customer Switcher: Initialized');
        }
        
        createDropdown() {
            const html = `
                <div class="saw-customer-switcher-dropdown" id="sawCustomerSwitcherDropdown">
                    <div class="saw-customer-search">
                        <input 
                            type="text" 
                            id="sawCustomerSearchInput" 
                            placeholder="Vyhledat zákazníka..."
                            autocomplete="off"
                        >
                    </div>
                    <div class="saw-customer-list" id="sawCustomerList">
                        <div class="saw-customer-loading">
                            <span class="spinner is-active"></span>
                            <div>Načítám zákazníky...</div>
                        </div>
                    </div>
                    <div class="saw-customer-footer" id="sawCustomerFooter">
                        Načítám...
                    </div>
                </div>
            `;
            
            this.button.parent().append(html);
            this.dropdown = $('#sawCustomerSwitcherDropdown');
            
            // Search input event with debounce
            let searchTimeout;
            $('#sawCustomerSearchInput').on('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.filterCustomers(e.target.value);
                }, 300);
            });
        }
        
        toggleDropdown() {
            if (this.dropdown.hasClass('active')) {
                this.closeDropdown();
            } else {
                this.openDropdown();
            }
        }
        
        openDropdown() {
            this.dropdown.addClass('active');
            
            // Focus search input
            setTimeout(() => {
                $('#sawCustomerSearchInput').focus();
            }, 100);
            
            // Load customers if not loaded
            if (this.customers.length === 0 && !this.isLoading) {
                this.loadCustomers();
            }
        }
        
        closeDropdown() {
            this.dropdown.removeClass('active');
            $('#sawCustomerSearchInput').val('');
        }
        
        loadCustomers() {
            if (this.isLoading) {
                return;
            }
            
            this.isLoading = true;
            
            console.log('SAW Customer Switcher: Loading customers...');
            console.log('AJAX URL:', sawCustomerSwitcher.ajaxurl);
            
            $.ajax({
                url: sawCustomerSwitcher.ajaxurl,
                method: 'POST',
                data: {
                    action: 'saw_get_customers_for_switcher',
                    nonce: sawCustomerSwitcher.nonce
                },
                success: (response) => {
                    console.log('SAW Customer Switcher: Response received', response);
                    
                    if (response.success) {
                        this.customers = response.data.customers || [];
                        this.currentCustomerId = response.data.current_customer_id;
                        
                        console.log(`SAW Customer Switcher: Loaded ${this.customers.length} customers`);
                        console.log('Current customer ID:', this.currentCustomerId);
                        
                        this.renderCustomers();
                    } else {
                        console.error('SAW Customer Switcher: Error', response.data);
                        this.showError(response.data.message || 'Chyba při načítání zákazníků');
                    }
                    
                    this.isLoading = false;
                },
                error: (xhr, status, error) => {
                    console.error('SAW Customer Switcher: AJAX error', {xhr, status, error});
                    this.showError('Chyba při komunikaci se serverem');
                    this.isLoading = false;
                }
            });
        }
        
        renderCustomers(filter = '') {
            const list = $('#sawCustomerList');
            list.empty();
            
            let filtered = this.customers;
            
            // Apply filter
            if (filter) {
                const lowerFilter = filter.trim().toLowerCase();
                filtered = this.customers.filter(customer => {
                    const name = (customer.name || '').toLowerCase();
                    const ico = (customer.ico || '').toLowerCase();
                    
                    console.log('Filtering:', {
                        filter: lowerFilter,
                        name: name,
                        ico: ico,
                        nameMatch: name.includes(lowerFilter),
                        icoMatch: ico.includes(lowerFilter)
                    });
                    
                    return name.includes(lowerFilter) || ico.includes(lowerFilter);
                });
                
                console.log('Filter results:', filtered.length, 'of', this.customers.length);
            }
            
            // Empty state
            if (filtered.length === 0) {
                list.html(`
                    <div class="saw-customer-empty">
                        ${filter ? 'Nenalezeni žádní zákazníci pro váš dotaz' : 'Žádní zákazníci'}
                    </div>
                `);
                $('#sawCustomerFooter').text('0 zákazníků');
                return;
            }
            
            // Sort alphabetically
            filtered.sort((a, b) => {
                const nameA = (a.name || '').toLowerCase();
                const nameB = (b.name || '').toLowerCase();
                return nameA.localeCompare(nameB, 'cs');
            });
            
            // Render items
            filtered.forEach(customer => {
                const isActive = parseInt(customer.id) === parseInt(this.currentCustomerId);
                
                const item = $(`
                    <div class="saw-customer-item ${isActive ? 'active' : ''}" data-customer-id="${customer.id}">
                        <div class="saw-customer-item-check">
                            ${isActive ? '✓' : ''}
                        </div>
                        <div class="saw-customer-item-info">
                            <div class="saw-customer-item-name">${this.escapeHtml(customer.name || 'Bez názvu')}</div>
                            ${customer.ico ? `<div class="saw-customer-item-ico">IČO: ${this.escapeHtml(customer.ico)}</div>` : ''}
                        </div>
                    </div>
                `);
                
                if (!isActive) {
                    item.on('click', () => {
                        this.switchCustomer(customer.id, customer.name);
                    });
                }
                
                list.append(item);
            });
            
            // Update footer
            const totalText = `${filtered.length} zákazník${this.getCzechPlural(filtered.length)}`;
            const filterText = filter ? ' (filtrováno)' : '';
            $('#sawCustomerFooter').text(totalText + filterText);
        }
        
        filterCustomers(search) {
            this.renderCustomers(search);
        }
        
        switchCustomer(customerId, customerName) {
            if (parseInt(customerId) === parseInt(this.currentCustomerId)) {
                this.closeDropdown();
                return;
            }
            
            console.log(`SAW Customer Switcher: Switching to customer ${customerId}`);
            
            // Show loading state
            this.button.prop('disabled', true).text('Přepínám...');
            this.closeDropdown();
            
            $.ajax({
                url: sawCustomerSwitcher.ajaxurl,
                method: 'POST',
                data: {
                    action: 'saw_switch_customer',
                    nonce: sawCustomerSwitcher.nonce,
                    customer_id: customerId
                },
                success: (response) => {
                    console.log('SAW Customer Switcher: Switch response', response);
                    
                    if (response.success) {
                        console.log('SAW Customer Switcher: Switch successful, reloading...');
                        // Reload page to reflect changes
                        window.location.reload();
                    } else {
                        console.error('SAW Customer Switcher: Switch failed', response.data);
                        alert(response.data.message || 'Chyba při přepínání zákazníka');
                        this.button.prop('disabled', false).text('🏢 Přepnout zákazníka');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('SAW Customer Switcher: Switch AJAX error', {xhr, status, error});
                    alert('Chyba při komunikaci se serverem');
                    this.button.prop('disabled', false).text('🏢 Přepnout zákazníka');
                }
            });
        }
        
        showError(message) {
            const list = $('#sawCustomerList');
            list.html(`
                <div class="saw-customer-empty" style="color: #ef4444;">
                    ❌ ${this.escapeHtml(message)}
                </div>
            `);
            $('#sawCustomerFooter').text('Chyba');
        }
        
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        getCzechPlural(count) {
            if (count === 1) return '';
            if (count >= 2 && count <= 4) return 'y';
            return 'ů';
        }
    }
    
    // Initialize when ready
    $(document).ready(function() {
        console.log('SAW Customer Switcher: DOM ready, initializing...');
        
        // Check if we have the required global variable
        if (typeof sawCustomerSwitcher === 'undefined') {
            console.error('SAW Customer Switcher: sawCustomerSwitcher object not found!');
            return;
        }
        
        new CustomerSwitcher();
    });
    
})(jQuery);