/**
 * SAW Customer Detail Modal JavaScript
 * 
 * @package SAW_Visitors
 * @version 4.6.1 ENHANCED
 */

(function($) {
    'use strict';
    
    const SawCustomerDetailModal = {
        
        currentCustomerId: null,
        currentCustomerData: null,
        
        /**
         * Inicializace
         */
        init: function() {
            this.bindEvents();
            console.log('SAW Customer Detail Modal: Initialized');
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;
            
            // Otevřít modal (delegovaný event na detail tlačítko v tabulce)
            $(document).on('click', '.saw-action-detail, [data-action="detail"]', function(e) {
                e.preventDefault();
                const customerId = $(this).data('id') || $(this).closest('tr').data('id');
                if (customerId) {
                    self.open(customerId);
                }
            });
            
            // Zavřít modal
            $('#saw-modal-close, #saw-modal-mobile-close').on('click', function() {
                self.close();
            });
            
            // Zavřít při kliknutí mimo modal
            $('#saw-customer-detail-modal').on('click', function(e) {
                if ($(e.target).is('#saw-customer-detail-modal')) {
                    self.close();
                }
            });
            
            // ESC key
            $(document).on('keyup', function(e) {
                if (e.key === 'Escape' && $('#saw-customer-detail-modal').is(':visible')) {
                    self.close();
                }
            });
            
            // Edit button
            $('#saw-modal-edit, #saw-modal-mobile-edit').on('click', function() {
                if (self.currentCustomerId) {
                    window.location.href = '/admin/settings/customers/edit/' + self.currentCustomerId + '/';
                }
            });
            
            // Delete button
            $('#saw-modal-delete, #saw-modal-mobile-delete').on('click', function() {
                if (self.currentCustomerId && confirm('Opravdu chcete smazat tohoto zákazníka?')) {
                    self.deleteCustomer(self.currentCustomerId);
                }
            });
            
            // Copy to clipboard
            $('.copy-btn').on('click', function() {
                const type = $(this).data('copy');
                let text = '';
                
                if (type === 'email') {
                    text = $('#saw-contact-email').text();
                } else if (type === 'phone') {
                    text = $('#saw-contact-phone').text();
                }
                
                if (text && text !== '—') {
                    self.copyToClipboard(text);
                    $(this).text('✅');
                    setTimeout(() => {
                        $(this).text('📋');
                    }, 2000);
                }
            });
        },
        
        /**
         * Otevřít modal
         */
        open: function(customerId) {
            this.currentCustomerId = customerId;
            
            $('#saw-customer-detail-modal').fadeIn(200);
            $('#saw-modal-loading').show();
            $('#saw-modal-content').hide();
            $('#saw-modal-footer').hide();
            
            this.loadCustomerData(customerId);
        },
        
        /**
         * Zavřít modal
         */
        close: function() {
            $('#saw-customer-detail-modal').fadeOut(200);
            this.currentCustomerId = null;
            this.currentCustomerData = null;
        },
        
        /**
         * Načíst data zákazníka přes AJAX
         */
        loadCustomerData: function(customerId) {
            const self = this;
            
            $.ajax({
                url: sawAdminTableAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'saw_get_customer_detail',
                    nonce: sawAdminTableAjax.nonce,
                    customer_id: customerId
                },
                success: function(response) {
                    if (response.success) {
                        self.currentCustomerData = response.data;
                        self.renderCustomerData(response.data);
                    } else {
                        alert('Chyba při načítání: ' + (response.data.message || 'Neznámá chyba'));
                        self.close();
                    }
                },
                error: function() {
                    alert('Chyba při načítání dat zákazníka.');
                    self.close();
                }
            });
        },
        
        /**
         * Vykreslit data zákazníka do modalu
         */
        renderCustomerData: function(data) {
            // Header barva podle primary_color
            const primaryColor = data.primary_color || '#2563eb';
            $('#saw-modal-header, #saw-modal-mobile-header').css('background-color', primaryColor);
            
            // Text barva podle kontrastu
            const textColor = this.getContrastColor(primaryColor);
            $('#saw-modal-header .saw-modal-title, #saw-modal-header .saw-modal-ico, #saw-modal-mobile-header .saw-modal-mobile-title').css('color', textColor);
            $('#saw-modal-header .saw-modal-close .dashicons, #saw-modal-mobile-header .dashicons').css('color', textColor);
            
            // Logo
            if (data.logo_url_full) {
                $('#saw-modal-logo').show();
                $('#saw-modal-logo-img').attr('src', data.logo_url_full);
            } else {
                $('#saw-modal-logo').hide();
            }
            
            // Title
            $('#saw-modal-title, #saw-modal-mobile-title').text(data.name);
            
            // IČO
            if (data.ico) {
                $('#saw-modal-ico').text('IČO: ' + data.ico).show();
            } else {
                $('#saw-modal-ico').hide();
            }
            
            // Základní informace
            $('#saw-info-name').text(data.name);
            $('#saw-info-ico').text(data.ico || '—');
            
            if (data.dic) {
                $('#saw-info-dic').text(data.dic);
                $('#saw-dic-row').show();
            } else {
                $('#saw-dic-row').hide();
            }
            
            // Provozní adresa
            if (data.operational_address) {
                $('#saw-operational-address').html(data.operational_address.replace(/\n/g, '<br>'));
                $('#operational-address-section').show();
            } else {
                $('#operational-address-section').hide();
            }
            
            // Fakturační adresa
            if (data.has_billing_address && data.billing_address) {
                $('#saw-billing-address').html(data.billing_address.replace(/\n/g, '<br>'));
                $('#billing-address-section').show();
            } else {
                $('#billing-address-section').hide();
            }
            
            // Kontaktní osoba
            let hasContact = false;
            
            if (data.contact_person) {
                $('#saw-contact-person').text(data.contact_person);
                $('#contact-person-row').show();
                hasContact = true;
            } else {
                $('#contact-person-row').hide();
            }
            
            if (data.contact_position) {
                $('#saw-contact-position').text(data.contact_position);
                $('#contact-position-row').show();
                hasContact = true;
            } else {
                $('#contact-position-row').hide();
            }
            
            if (data.contact_email) {
                $('#saw-contact-email').text(data.contact_email);
                $('#contact-email-row').show();
                hasContact = true;
            } else {
                $('#contact-email-row').hide();
            }
            
            if (data.contact_phone) {
                $('#saw-contact-phone').text(data.contact_phone);
                $('#contact-phone-row').show();
                hasContact = true;
            } else {
                $('#contact-phone-row').hide();
            }
            
            if (data.website) {
                $('#saw-contact-website').attr('href', data.website).text(data.website);
                $('#contact-website-row').show();
                hasContact = true;
            } else {
                $('#contact-website-row').hide();
            }
            
            if (hasContact) {
                $('#contact-section').show();
            } else {
                $('#contact-section').hide();
            }
            
            // Obchodní údaje
            $('#saw-customer-status-badge')
                .text(data.status_label)
                .css('background-color', data.status_color);
            
            $('#saw-account-type-badge')
                .text(data.account_type_display_name)
                .css('background-color', data.account_type_color);
            
            if (data.acquisition_source) {
                $('#saw-acquisition-source').text(data.acquisition_source);
                $('#acquisition-row').show();
            } else {
                $('#acquisition-row').hide();
            }
            
            if (data.subscription_type) {
                $('#saw-subscription-type').text(data.subscription_type);
                $('#subscription-row').show();
            } else {
                $('#subscription-row').hide();
            }
            
            if (data.last_payment_date) {
                $('#saw-last-payment').text(data.last_payment_date);
                $('#payment-row').show();
            } else {
                $('#payment-row').hide();
            }
            
            // Poznámky
            if (data.notes) {
                $('#saw-info-notes').html(data.notes.replace(/\n/g, '<br>'));
                $('#notes-section').show();
            } else {
                $('#notes-section').hide();
            }
            
            // Metadata
            $('#saw-info-created').text(data.created_at || '—');
            
            if (data.updated_at) {
                $('#saw-info-updated').text(data.updated_at);
                $('#saw-updated-row').show();
            } else {
                $('#saw-updated-row').hide();
            }
            
            // Zobrazit obsah
            $('#saw-modal-loading').hide();
            $('#saw-modal-content').fadeIn(200);
            $('#saw-modal-footer').fadeIn(200);
            
            // Mobile: zkopírovat obsah
            $('#saw-modal-mobile-body').html($('#saw-modal-content').html());
        },
        
        /**
         * Smazat zákazníka
         */
        deleteCustomer: function(customerId) {
            const self = this;
            
            $.ajax({
                url: sawAdminTableAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'saw_admin_table_delete',
                    nonce: sawAdminTableAjax.nonce,
                    entity: 'customers',
                    id: customerId
                },
                success: function(response) {
                    if (response.success) {
                        alert('Zákazník byl smazán.');
                        self.close();
                        location.reload();
                    } else {
                        alert('Chyba při mazání: ' + (response.data.message || 'Neznámá chyba'));
                    }
                },
                error: function() {
                    alert('Chyba při mazání zákazníka.');
                }
            });
        },
        
        /**
         * Zkopírovat do schránky
         */
        copyToClipboard: function(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    console.log('Copied:', text);
                });
            } else {
                // Fallback
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            }
        },
        
        /**
         * Získat kontrastní barvu textu (bílá/černá) podle pozadí
         */
        getContrastColor: function(hexColor) {
            const r = parseInt(hexColor.substr(1, 2), 16);
            const g = parseInt(hexColor.substr(3, 2), 16);
            const b = parseInt(hexColor.substr(5, 2), 16);
            
            const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
            
            return luminance > 0.5 ? '#000000' : '#ffffff';
        }
    };
    
    // Inicializace
    $(document).ready(function() {
        SawCustomerDetailModal.init();
    });
    
    // Export pro případné externí použití
    window.SawCustomerDetailModal = SawCustomerDetailModal;
    
})(jQuery);