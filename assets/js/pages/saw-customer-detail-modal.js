/**
 * SAW Customer Detail Modal - Tab System
 * 
 * ✨ ENHANCED v4.7.5: SPA Navigation Support
 * - Exports CustomerModal to window for reinitialization
 * - Listens to 'saw:scripts-reinitialized' event
 * - Fixes modal not working after AJAX navigation
 * - Prevents duplicate event bindings with destroy() method
 * 
 * @package SAW_Visitors
 * @version 4.7.5
 */

(function($) {
    'use strict';
    
    const CustomerModal = {
        $modal: null,
        currentCustomerId: null,
        eventsInitialized: false, // ✨ Flag pro prevenci duplicitních eventů
        
        /**
         * Initialize modal
         * 
         * Volá se:
         * 1. Při prvním načtení stránky (document.ready)
         * 2. Po AJAX načtení (saw:scripts-reinitialized event)
         */
        init() {
            console.log('🎯 Customer Modal: Initializing...');
            
            this.$modal = $('#saw-customer-detail-modal');
            
            if (!this.$modal.length) {
                console.warn('⚠️ Modal element not found');
                return;
            }
            
            if (typeof sawCustomerModal === 'undefined') {
                console.error('❌ sawCustomerModal config not found');
                return;
            }
            
            // ✨ NOVÉ: Unbind předchozí eventy aby se nepřidávaly duplicitně
            if (this.eventsInitialized) {
                console.log('  ↳ Destroying previous event bindings...');
                this.destroy();
            }
            
            this.bindEvents();
            this.eventsInitialized = true;
            
            console.log('✅ Customer Modal initialized');
        },
        
        /**
         * Bind event handlers
         * 
         * DŮLEŽITÉ: Používáme event delegation přes $(document).on()
         * s namespace '.customerModal' pro snadné unbinding
         */
        bindEvents() {
            // ✨ Row click - otevření modalu
            // Event delegation funguje i pro nově načtené řádky po AJAX
            $(document).on('click.customerModal', '.saw-customer-row', (e) => {
                // Ignore clicks on action buttons
                if ($(e.target).closest('.saw-actions').length > 0) {
                    return;
                }
                
                // Ignore direct link clicks
                if ($(e.target).is('a') || $(e.target).closest('a').length > 0) {
                    return;
                }
                
                // Ignore copy email button
                if ($(e.target).hasClass('copy-email-btn') || $(e.target).closest('.copy-email-btn').length > 0) {
                    return;
                }
                
                const $nameElement = $(e.currentTarget).find('.saw-customer-name');
                const customerId = $nameElement.data('customer-id');
                
                if (customerId) {
                    console.log('📋 Opening customer detail:', customerId);
                    this.open(customerId);
                }
            });
            
            // ✨ Close buttons
            $(document).on('click.customerModal', '#saw-modal-close, #saw-modal-mobile-close', () => {
                this.close();
            });
            
            // ✨ ESC key
            $(document).on('keydown.customerModal', (e) => {
                if (e.key === 'Escape' && this.$modal.is(':visible')) {
                    this.close();
                }
            });
            
            // ✨ Overlay click
            this.$modal.on('click.customerModal', (e) => {
                if ($(e.target).is('.saw-modal-overlay')) {
                    this.close();
                }
            });
            
            // ✨ Tab switching
            $(document).on('click.customerModal', '.saw-modal-tab', (e) => {
                const tabId = $(e.currentTarget).data('tab');
                this.switchTab(tabId);
            });
            
            // ✨ Edit button
            $(document).on('click.customerModal', '#saw-modal-edit, #saw-modal-mobile-edit', () => {
                if (this.currentCustomerId && sawCustomerModal.editUrl) {
                    const editUrl = sawCustomerModal.editUrl.replace('{id}', this.currentCustomerId);
                    window.location.href = editUrl;
                }
            });
            
            // ✨ Delete button
            $(document).on('click.customerModal', '#saw-modal-delete, #saw-modal-mobile-delete', () => {
                this.handleDelete();
            });
            
            // ✨ Copy buttons
            $(document).on('click.customerModal', '.copy-btn', (e) => {
                const type = $(e.currentTarget).data('copy');
                this.copyToClipboard(type);
            });
            
            console.log('  ↳ Events bound with namespace .customerModal');
        },
        
        /**
         * ✨ NOVÉ: Destroy method - unbind all events
         * 
         * Zabraňuje duplicitním event handlerům při reinicializaci
         */
        destroy() {
            // Unbind všechny eventy s namespace .customerModal
            $(document).off('.customerModal');
            
            if (this.$modal) {
                this.$modal.off('.customerModal');
            }
            
            console.log('  ↳ Previous events destroyed');
        },
        
        /**
         * Open modal and load customer data
         */
        open(customerId) {
            console.log('📂 Opening modal for customer:', customerId);
            
            this.currentCustomerId = customerId;
            
            this.$modal.fadeIn(200);
            $('body').css('overflow', 'hidden');
            
            this.loadCustomerData(customerId);
        },
        
        /**
         * Close modal
         */
        close() {
            console.log('🚪 Closing modal');
            
            this.$modal.fadeOut(200);
            $('body').css('overflow', '');
            
            setTimeout(() => {
                this.reset();
            }, 200);
        },
        
        /**
         * Reset modal state
         */
        reset() {
            this.currentCustomerId = null;
            $('#saw-modal-loading').show();
            $('#saw-modal-content').hide();
            $('#saw-modal-footer').hide();
            $('.saw-modal-tab').first().trigger('click');
        },
        
        /**
         * Switch between tabs
         */
        switchTab(tabId) {
            $('.saw-modal-tab').removeClass('active');
            $('.saw-modal-tab[data-tab="' + tabId + '"]').addClass('active');
            
            $('.saw-modal-tab-panel').removeClass('active');
            $('#saw-tab-' + tabId).addClass('active');
        },
        
        /**
         * Load customer data via AJAX
         */
        loadCustomerData(customerId) {
            $.ajax({
                url: sawCustomerModal.ajaxurl,
                type: 'POST',
                data: {
                    action: 'saw_get_customer_detail',
                    nonce: sawCustomerModal.nonce,
                    customer_id: customerId
                },
                success: (response) => {
                    if (response.success) {
                        this.renderCustomer(response.data.customer);
                    } else {
                        alert(response.data.message || 'Chyba při načítání dat');
                        this.close();
                    }
                },
                error: () => {
                    alert('Chyba komunikace se serverem');
                    this.close();
                }
            });
        },
        
        /**
         * Render customer data in modal
         */
        renderCustomer(customer) {
            console.log('🎨 Rendering customer:', customer);
            
            // Header
            $('#saw-modal-title, #saw-modal-mobile-title').text(customer.name);
            $('#saw-modal-ico').text(customer.ico ? 'IČO: ' + customer.ico : '');
            
            // Logo
            if (customer.logo_url_full) {
                $('#saw-modal-logo').show();
                $('#saw-modal-logo-img').attr('src', customer.logo_url_full);
            } else {
                $('#saw-modal-logo').hide();
            }
            
            // Brand color
            if (customer.primary_color) {
                this.applyBrandColor(customer.primary_color);
            }
            
            // Render all tabs
            this.renderBasicInfo(customer);
            this.renderAddresses(customer);
            this.renderContact(customer);
            this.renderBusiness(customer);
            this.renderSystem(customer);
            
            // Show content
            $('#saw-modal-loading').hide();
            $('#saw-modal-content').show();
            $('#saw-modal-footer').show();
        },
        
        /**
         * Render Basic Info tab
         */
        renderBasicInfo(customer) {
            $('#basic-name').text(customer.name);
            $('#basic-ico').text(customer.ico || '—');
            
            if (customer.dic) {
                $('#basic-dic').text(customer.dic);
                $('#basic-dic-row').show();
            } else {
                $('#basic-dic-row').hide();
            }
        },
        
        /**
         * Render Addresses tab
         */
        renderAddresses(customer) {
            if (customer.formatted_operational_address) {
                $('#operational-address').html(
                    customer.formatted_operational_address.replace(/,\s*/g, '<br>')
                );
                $('#operational-address-section').show();
            } else {
                $('#operational-address-section').hide();
            }
            
            if (customer.formatted_billing_address) {
                $('#billing-address').html(
                    customer.formatted_billing_address.replace(/,\s*/g, '<br>')
                );
                $('#billing-address-section').show();
            } else {
                $('#billing-address-section').hide();
            }
        },
        
        /**
         * Render Contact tab
         */
        renderContact(customer) {
            let hasContact = false;
            
            if (customer.contact_person) {
                $('#contact-person').text(customer.contact_person);
                $('#contact-person-row').show();
                hasContact = true;
            } else {
                $('#contact-person-row').hide();
            }
            
            if (customer.contact_position) {
                $('#contact-position').text(customer.contact_position);
                $('#contact-position-row').show();
                hasContact = true;
            } else {
                $('#contact-position-row').hide();
            }
            
            if (customer.contact_email) {
                $('#contact-email').text(customer.contact_email);
                $('#contact-email-row').show();
                hasContact = true;
            } else {
                $('#contact-email-row').hide();
            }
            
            if (customer.contact_phone) {
                $('#contact-phone').text(customer.contact_phone);
                $('#contact-phone-row').show();
                hasContact = true;
            } else {
                $('#contact-phone-row').hide();
            }
            
            if (customer.website) {
                $('#contact-website').attr('href', customer.website).text(customer.website);
                $('#contact-website-row').show();
                hasContact = true;
            } else {
                $('#contact-website-row').hide();
            }
            
            $('#contact-section').toggle(hasContact);
        },
        
        /**
         * Render Business tab
         */
        renderBusiness(customer) {
            // Status badge
            const statusBadge = $('#business-status-badge');
            statusBadge.removeClass('saw-badge-potential saw-badge-active saw-badge-inactive');
            statusBadge.addClass('saw-badge-' + customer.status);
            statusBadge.text(customer.status_label);
            
            // Account type
            if (customer.account_type_display_name) {
                const accountBadge = $('#business-account-type-badge');
                accountBadge.text(customer.account_type_display_name);
                if (customer.account_type_color) {
                    accountBadge.css('background-color', customer.account_type_color + '20');
                    accountBadge.css('color', customer.account_type_color);
                }
                $('#business-account-type-row').show();
            } else {
                $('#business-account-type-row').hide();
            }
            
            // Acquisition source
            if (customer.acquisition_source) {
                $('#business-acquisition-source').text(customer.acquisition_source);
                $('#business-acquisition-row').show();
            } else {
                $('#business-acquisition-row').hide();
            }
            
            // Subscription type
            if (customer.subscription_type) {
                $('#business-subscription-type').text(customer.subscription_type_label);
                $('#business-subscription-row').show();
            } else {
                $('#business-subscription-row').hide();
            }
            
            // Last payment
            if (customer.last_payment_date_formatted) {
                $('#business-last-payment').text(customer.last_payment_date_formatted);
                $('#business-payment-row').show();
            } else {
                $('#business-payment-row').hide();
            }
            
            // Notes
            if (customer.notes) {
                $('#business-notes').text(customer.notes);
                $('#business-notes-section').show();
            } else {
                $('#business-notes-section').hide();
            }
        },
        
        /**
         * Render System tab
         */
        renderSystem(customer) {
            $('#system-primary-color-value').text(customer.primary_color);
            $('#system-primary-color-preview').css('background-color', customer.primary_color);
            
            $('#system-language').text(customer.admin_language_label);
            $('#system-created-at').text(customer.created_at_formatted);
            
            if (customer.updated_at_formatted) {
                $('#system-updated-at').text(customer.updated_at_formatted);
                $('#system-updated-row').show();
            } else {
                $('#system-updated-row').hide();
            }
        },
        
        /**
         * Apply brand color to modal header
         */
        applyBrandColor(color) {
            const header = $('#saw-modal-header, #saw-modal-mobile-header');
            header.css('background-color', color);
            
            const textColor = this.getContrastColor(color);
            header.find('.saw-modal-title, .saw-modal-ico, .saw-modal-mobile-title').css('color', textColor);
            header.find('.dashicons').css('color', textColor);
        },
        
        /**
         * Calculate contrast color (black or white)
         */
        getContrastColor(hexColor) {
            const r = parseInt(hexColor.substr(1, 2), 16);
            const g = parseInt(hexColor.substr(3, 2), 16);
            const b = parseInt(hexColor.substr(5, 2), 16);
            const brightness = ((r * 299) + (g * 587) + (b * 114)) / 1000;
            return brightness > 155 ? '#000000' : '#ffffff';
        },
        
        /**
         * Handle customer deletion
         */
        handleDelete() {
            if (!confirm('Opravdu chcete smazat tohoto zákazníka? Tato akce je nevratná.')) {
                return;
            }
            
            $.ajax({
                url: sawCustomerModal.ajaxurl,
                type: 'POST',
                data: {
                    action: 'saw_admin_table_delete',
                    nonce: sawCustomerModal.nonce,
                    id: this.currentCustomerId,
                    entity: 'customers'
                },
                success: (response) => {
                    if (response.success) {
                        alert('Zákazník byl úspěšně smazán');
                        this.close();
                        location.reload();
                    } else {
                        alert(response.data.message || 'Chyba při mazání');
                    }
                },
                error: () => {
                    alert('Chyba komunikace se serverem');
                }
            });
        },
        
        /**
         * Copy text to clipboard
         */
        copyToClipboard(type) {
            let text = '';
            
            if (type === 'email') {
                text = $('#contact-email').text();
            } else if (type === 'phone') {
                text = $('#contact-phone').text();
            }
            
            if (!text) return;
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(() => {
                    alert('Zkopírováno: ' + text);
                });
            } else {
                // Fallback for older browsers
                const $temp = $('<input>');
                $('body').append($temp);
                $temp.val(text).select();
                document.execCommand('copy');
                $temp.remove();
                alert('Zkopírováno: ' + text);
            }
        }
    };
    
    // ✨ NOVÉ: Export do window pro možnost reinicializace
    // Díky tomuto může SAW Navigation volat window.CustomerModal.init()
    window.CustomerModal = CustomerModal;
    
    /**
     * Initialize on document ready (první načtení stránky)
     */
    $(document).ready(function() {
        CustomerModal.init();
    });
    
    /**
     * ✨ NOVÉ: Reinicializace po AJAX načtení
     * 
     * Když saw-app-navigation.js načte novou stránku přes AJAX,
     * vyvolá event 'saw:scripts-reinitialized'.
     * 
     * Na tento event nasloucháme a reinicializujeme modal.
     * Díky destroy() metodě se nepřidávají duplicitní event handlery.
     */
    $(document).on('saw:scripts-reinitialized', function() {
        console.log('🔄 Reinitializing Customer Modal after AJAX...');
        CustomerModal.init();
    });
    
})(jQuery);