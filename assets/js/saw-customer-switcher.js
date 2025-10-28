/**
 * SAW Customer Switcher JavaScript (COMPLETE FIX)
 * 
 * ✅ VYLEPŠENO:
 * - Lepší error handling a debugging
 * - Zobrazení current customer v buttonu
 * - Loading states
 * - Better UX
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
            this.currentCustomerName = null;
            this.isLoading = false;
            
            console.log('🏢 Customer Switcher: Constructor called');
            console.log('   Button found:', this.button.length > 0);
            
            this.init();
        }
        
        init() {
            if (!this.button.length) {
                console.log('⚠️ SAW Customer Switcher: Button not found (not SuperAdmin)');
                return;
            }
            
            // Check if sawCustomerSwitcher global exists
            if (typeof sawCustomerSwitcher === 'undefined') {
                console.error('❌ SAW Customer Switcher: sawCustomerSwitcher object not found!');
                console.error('   The header did not properly inline the localized data.');
                console.error('   Make sure SAW_App_Header->enqueue_customer_switcher_assets() is called.');
                return;
            }
            
            console.log('✅ SAW Customer Switcher: Initializing...');
            console.log('   AJAX URL:', sawCustomerSwitcher.ajaxurl);
            console.log('   Nonce:', sawCustomerSwitcher.nonce ? 'Present' : 'MISSING!');
            
            // Get current customer from button data
            this.currentCustomerId = this.button.data('current-customer-id');
            this.currentCustomerName = this.button.data('current-customer-name');
            
            console.log('   Current customer ID from button:', this.currentCustomerId);
            console.log('   Current customer name from button:', this.currentCustomerName);
            
            // Create dropdown
            this.createDropdown();
            
            // Bind events
            this.button.on('click', (e) => {
                e.stopPropagation();
                console.log('🖱️ Customer Switcher: Button clicked');
                this.toggleDropdown();
            });
            
            // Close on outside click
            $(document).on('click', (e) => {
                if (!$(e.target).closest('.saw-customer-switcher').length) {
                    if (this.dropdown && this.dropdown.hasClass('active')) {
                        console.log('🖱️ Customer Switcher: Outside click, closing dropdown');
                        this.closeDropdown();
                    }
                }
            });
            
            // ESC key to close
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape') {
                    if (this.dropdown && this.dropdown.hasClass('active')) {
                        console.log('⌨️ Customer Switcher: ESC pressed, closing dropdown');
                        this.closeDropdown();
                    }
                }
            });
            
            console.log('✅ SAW Customer Switcher: Initialized successfully');
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
                            <span class="spinner is-active" style="float: none; margin: 0 auto;"></span>
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
            
            console.log('✅ Dropdown created, element found:', this.dropdown.length > 0);
            
            // Search input event with debounce
            let searchTimeout;
            $('#sawCustomerSearchInput').on('input', (e) => {
                const searchValue = e.target.value;
                console.log('🔍 Customer Switcher: Search input changed:', searchValue);
                
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.filterCustomers(searchValue);
                }, 300);
            });
        }
        
        toggleDropdown() {
            if (this.dropdown.hasClass('active')) {
                console.log('🔽 Closing dropdown');
                this.closeDropdown();
            } else {
                console.log('🔼 Opening dropdown');
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
                console.log('📦 Loading customers for the first time...');
                this.loadCustomers();
            } else {
                console.log('📦 Using cached customers:', this.customers.length);
                this.renderCustomers();
            }
        }
        
        closeDropdown() {
            this.dropdown.removeClass('active');
            $('#sawCustomerSearchInput').val('');
        }
        
        loadCustomers() {
            if (this.isLoading) {
                console.log('⏳ Already loading customers, skipping...');
                return;
            }
            
            this.isLoading = true;
            
            console.log('🚀 SAW Customer Switcher: Loading customers...');
            console.log('   AJAX URL:', sawCustomerSwitcher.ajaxurl);
            console.log('   Action: saw_get_customers_for_switcher');
            console.log('   Nonce:', sawCustomerSwitcher.nonce);
            
            $.ajax({
                url: sawCustomerSwitcher.ajaxurl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'saw_get_customers_for_switcher',
                    nonce: sawCustomerSwitcher.nonce
                },
                timeout: 10000, // 10 seconds timeout
                success: (response) => {
                    console.log('✅ SAW Customer Switcher: Response received', response);
                    
                    if (response && response.success) {
                        this.customers = response.data.customers || [];
                        
                        // Get current customer ID from response or button data
                        if (response.data.current_customer_id) {
                            this.currentCustomerId = parseInt(response.data.current_customer_id);
                        }
                        
                        console.log(`✅ Loaded ${this.customers.length} customers`);
                        console.log('   Current customer ID:', this.currentCustomerId);
                        
                        if (this.customers.length === 0) {
                            this.showError('Žádní zákazníci nebyli nalezeni');
                        } else {
                            this.renderCustomers();
                        }
                    } else {
                        console.error('❌ SAW Customer Switcher: Error in response', response);
                        const message = (response && response.data && response.data.message) 
                            ? response.data.message 
                            : 'Chyba při načítání zákazníků';
                        this.showError(message);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('❌ SAW Customer Switcher: AJAX error', {
                        status: status,
                        error: error,
                        statusCode: xhr.status,
                        responseText: xhr.responseText
                    });
                    
                    let errorMessage = 'Chyba při komunikaci se serverem';
                    
                    if (xhr.status === 403) {
                        errorMessage = 'Nedostatečná oprávnění (403)';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Chyba serveru (500)';
                    } else if (status === 'timeout') {
                        errorMessage = 'Vypršel časový limit požadavku';
                    }
                    
                    this.showError(errorMessage);
                },
                complete: () => {
                    this.isLoading = false;
                    console.log('✅ Customer loading complete');
                }
            });
        }
        
        renderCustomers(filter = '') {
            const list = $('#sawCustomerList');
            list.empty();
            
            // Filter customers
            const lowerFilter = filter.toLowerCase().trim();
            const filtered = filter 
                ? this.customers.filter(c => {
                    const name = (c.name || '').toLowerCase();
                    const ico = (c.ico || '').toLowerCase();
                    return name.includes(lowerFilter) || ico.includes(lowerFilter);
                })
                : this.customers;
            
            console.log(`📋 Rendering ${filtered.length} customers (filtered from ${this.customers.length})`);
            
            if (filtered.length === 0) {
                list.html(`
                    <div class="saw-customer-empty">
                        ${filter ? 'Žádní zákazníci nevyhovují vyhledávání' : 'Žádní zákazníci'}
                    </div>
                `);
                $('#sawCustomerFooter').text('0 zákazníků');
                return;
            }
            
            // Sort alphabetically
            filtered.sort((a, b) => {
                return (a.name || '').localeCompare(b.name || '', 'cs');
            });
            
            // Render each customer
            filtered.forEach(customer => {
                const isActive = parseInt(customer.id) === parseInt(this.currentCustomerId);
                
                const item = $(`
                    <div class="saw-customer-item ${isActive ? 'active' : ''}" 
                         data-customer-id="${customer.id}"
                         style="cursor: ${isActive ? 'default' : 'pointer'}">
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
                        console.log('🖱️ Customer item clicked:', customer.id, customer.name);
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
            console.log('🔍 Filtering customers by:', search);
            this.renderCustomers(search);
        }
        
        switchCustomer(customerId, customerName) {
            if (parseInt(customerId) === parseInt(this.currentCustomerId)) {
                console.log('ℹ️ Already on this customer, closing dropdown');
                this.closeDropdown();
                return;
            }
            
            console.log(`🔄 SAW Customer Switcher: Switching to customer ${customerId} (${customerName})`);
            
            // Show loading state
            const originalText = this.button.html();
            this.button.prop('disabled', true).html('⏳ Přepínám...');
            this.closeDropdown();
            
            $.ajax({
                url: sawCustomerSwitcher.ajaxurl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'saw_switch_customer',
                    nonce: sawCustomerSwitcher.nonce,
                    customer_id: customerId
                },
                timeout: 10000,
                success: (response) => {
                    console.log('✅ SAW Customer Switcher: Switch response', response);
                    
                    if (response && response.success) {
                        console.log('✅ Switch successful, reloading page...');
                        
                        // Show success message briefly before reload
                        this.button.html('✅ Přepnuto!');
                        
                        // Reload page after short delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    } else {
                        console.error('❌ SAW Customer Switcher: Switch failed', response);
                        const message = (response && response.data && response.data.message) 
                            ? response.data.message 
                            : 'Chyba při přepínání zákazníka';
                        alert(message);
                        this.button.prop('disabled', false).html(originalText);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('❌ SAW Customer Switcher: Switch AJAX error', {
                        status: status,
                        error: error,
                        statusCode: xhr.status,
                        responseText: xhr.responseText
                    });
                    
                    let errorMessage = 'Chyba při komunikaci se serverem';
                    
                    if (xhr.status === 403) {
                        errorMessage = 'Nedostatečná oprávnění';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Chyba serveru';
                    }
                    
                    alert(errorMessage);
                    this.button.prop('disabled', false).html(originalText);
                }
            });
        }
        
        showError(message) {
            const list = $('#sawCustomerList');
            list.html(`
                <div class="saw-customer-empty" style="color: #ef4444; padding: 32px 24px; text-align: center;">
                    <div style="font-size: 32px; margin-bottom: 8px;">❌</div>
                    <div>${this.escapeHtml(message)}</div>
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
        console.log('📦 SAW Customer Switcher: DOM ready, initializing...');
        
        // Small delay to ensure everything is loaded
        setTimeout(() => {
            new CustomerSwitcher();
        }, 100);
    });
    
})(jQuery);