/**
 * SAW Customer Detail Modal - Tab System
 * 
 * @package SAW_Visitors
 * @version 4.7.4
 */

(function($) {
    'use strict';
    
    const CustomerModal = {
        $modal: null,
        currentCustomerId: null,
        
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
            
            this.bindEvents();
            console.log('✅ Customer Modal initialized');
        },
        
        bindEvents() {
            $(document).on('click', '.saw-customer-name', (e) => {
                e.preventDefault();
                const customerId = $(e.currentTarget).data('customer-id');
                this.open(customerId);
            });
            
            $(document).on('click', '#saw-modal-close, #saw-modal-mobile-close', () => {
                this.close();
            });
            
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && this.$modal.is(':visible')) {
                    this.close();
                }
            });
            
            this.$modal.on('click', (e) => {
                if ($(e.target).is('.saw-modal-overlay')) {
                    this.close();
                }
            });
            
            $(document).on('click', '.saw-modal-tab', (e) => {
                const tabId = $(e.currentTarget).data('tab');
                this.switchTab(tabId);
            });
            
            $(document).on('click', '#saw-modal-edit, #saw-modal-mobile-edit', () => {
                if (this.currentCustomerId && sawCustomerModal.editUrl) {
                    window.location.href = sawCustomerModal.editUrl + this.currentCustomerId;
                }
            });
            
            $(document).on('click', '#saw-modal-delete, #saw-modal-mobile-delete', () => {
                this.handleDelete();
            });
            
            $(document).on('click', '.copy-btn', (e) => {
                const type = $(e.currentTarget).data('copy');
                this.copyToClipboard(type);
            });
        },
        
        open(customerId) {
            console.log('📂 Opening modal for customer:', customerId);
            
            this.currentCustomerId = customerId;
            
            this.$modal.fadeIn(200);
            $('body').css('overflow', 'hidden');
            
            this.loadCustomerData(customerId);
        },
        
        close() {
            console.log('🚪 Closing modal');
            
            this.$modal.fadeOut(200);
            $('body').css('overflow', '');
            
            setTimeout(() => {
                this.reset();
            }, 200);
        },
        
        reset() {
            this.currentCustomerId = null;
            $('#saw-modal-loading').show();
            $('#saw-modal-content').hide();
            $('#saw-modal-footer').hide();
            $('.saw-modal-tab').first().trigger('click');
        },
        
        switchTab(tabId) {
            $('.saw-modal-tab').removeClass('active');
            $('.saw-modal-tab[data-tab="' + tabId + '"]').addClass('active');
            
            $('.saw-modal-tab-panel').removeClass('active');
            $('#saw-tab-' + tabId).addClass('active');
        },
        
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
        
        renderCustomer(customer) {
            console.log('🎨 Rendering customer:', customer);
            
            $('#saw-modal-title, #saw-modal-mobile-title').text(customer.name);
            $('#saw-modal-ico').text(customer.ico ? 'IČO: ' + customer.ico : '');
            
            if (customer.logo_url_full) {
                $('#saw-modal-logo').show();
                $('#saw-modal-logo-img').attr('src', customer.logo_url_full);
            } else {
                $('#saw-modal-logo').hide();
            }
            
            if (customer.primary_color) {
                this.applyBrandColor(customer.primary_color);
            }
            
            this.renderBasicInfo(customer);
            this.renderAddresses(customer);
            this.renderContact(customer);
            this.renderBusiness(customer);
            this.renderSystem(customer);
            
            $('#saw-modal-loading').hide();
            $('#saw-modal-content').show();
            $('#saw-modal-footer').show();
        },
        
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
        
        renderBusiness(customer) {
            const statusBadge = $('#business-status-badge');
            statusBadge.removeClass('saw-badge-potential saw-badge-active saw-badge-inactive');
            statusBadge.addClass('saw-badge-' + customer.status);
            statusBadge.text(customer.status_label);
            
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
            
            if (customer.acquisition_source) {
                $('#business-acquisition-source').text(customer.acquisition_source);
                $('#business-acquisition-row').show();
            } else {
                $('#business-acquisition-row').hide();
            }
            
            if (customer.subscription_type) {
                $('#business-subscription-type').text(customer.subscription_type_label);
                $('#business-subscription-row').show();
            } else {
                $('#business-subscription-row').hide();
            }
            
            if (customer.last_payment_date_formatted) {
                $('#business-last-payment').text(customer.last_payment_date_formatted);
                $('#business-payment-row').show();
            } else {
                $('#business-payment-row').hide();
            }
            
            if (customer.notes) {
                $('#business-notes').text(customer.notes);
                $('#business-notes-section').show();
            } else {
                $('#business-notes-section').hide();
            }
        },
        
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
        
        applyBrandColor(color) {
            const header = $('#saw-modal-header, #saw-modal-mobile-header');
            header.css('background-color', color);
            
            const textColor = this.getContrastColor(color);
            header.find('.saw-modal-title, .saw-modal-ico, .saw-modal-mobile-title').css('color', textColor);
            header.find('.dashicons').css('color', textColor);
        },
        
        getContrastColor(hexColor) {
            const r = parseInt(hexColor.substr(1, 2), 16);
            const g = parseInt(hexColor.substr(3, 2), 16);
            const b = parseInt(hexColor.substr(5, 2), 16);
            const brightness = ((r * 299) + (g * 587) + (b * 114)) / 1000;
            return brightness > 155 ? '#000000' : '#ffffff';
        },
        
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
                const $temp = $('<input>');
                $('body').append($temp);
                $temp.val(text).select();
                document.execCommand('copy');
                $temp.remove();
                alert('Zkopírováno: ' + text);
            }
        }
    };
    
    $(document).ready(() => {
        CustomerModal.init();
    });
    
})(jQuery);