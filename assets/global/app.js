/**
 * SAW Global JavaScript
 * 
 * Modal pro detail, delete confirmations, atd.
 * Načítá se na VŠECH SAW stránkách.
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 * @since   4.8.0
 */

(function($) {
    'use strict';
    
    // ===== MODAL SYSTEM =====
    
    const SAW_Modal = {
        /**
         * Otevře modal
         */
        open: function(modalId) {
            const $modal = $('#' + modalId);
            if ($modal.length === 0) {
                console.error('Modal not found:', modalId);
                return;
            }
            
            $modal.addClass('saw-modal-open');
            $('body').addClass('saw-modal-active');
            
            // Focus trap
            $modal.find('[data-modal-close]').first().focus();
        },
        
        /**
         * Zavře modal
         */
        close: function(modalId) {
            const $modal = $('#' + modalId);
            $modal.removeClass('saw-modal-open');
            $('body').removeClass('saw-modal-active');
        },
        
        /**
         * Načte data do modalu přes AJAX
         */
        loadData: function(modalId, ajaxAction, data, onSuccess) {
            const $modal = $('#' + modalId);
            const $content = $modal.find('.saw-modal-body');
            
            // Show loading
            $content.html('<div class="saw-loading"><div class="saw-spinner"></div></div>');
            this.open(modalId);
            
            // AJAX request
            $.ajax({
                url: sawGlobal.ajaxurl,
                method: 'POST',
                data: {
                    action: ajaxAction,
                    ...data
                },
                success: function(response) {
                    if (response.success) {
                        if (onSuccess) {
                            onSuccess(response.data, $modal, $content);
                        }
                    } else {
                        $content.html(
                            '<div class="saw-alert saw-alert-danger">' +
                            '<strong>Chyba:</strong> ' + (response.data?.message || 'Neznámá chyba') +
                            '</div>'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    $content.html(
                        '<div class="saw-alert saw-alert-danger">' +
                        '<strong>Chyba:</strong> ' + error +
                        '</div>'
                    );
                }
            });
        }
    };
    
    // ===== CUSTOMER DETAIL MODAL =====
    
    /**
     * Kliknutí na řádek tabulky
     */
    $(document).on('click', '.saw-customer-row', function(e) {
        // Ignoruj kliknutí na buttony a linky
        if ($(e.target).closest('button, a, .saw-actions-column').length > 0) {
            return;
        }
        
        const customerId = $(this).data('id');
        
        if (!customerId) {
            console.error('Customer ID not found');
            return;
        }
        
        // Načti detail
        SAW_Modal.loadData(
            'saw-customer-modal',
            'saw_get_customers_detail',
            {
                nonce: sawGlobal.customerModalNonce || sawGlobal.nonce,
                customers_id: customerId
            },
            function(data, $modal, $content) {
                const customer = data.customers || data.customer;
                
                if (!customer) {
                    $content.html('<div class="saw-alert saw-alert-danger">Zákazník nenalezen</div>');
                    return;
                }
                
                // Render detail
                const html = renderCustomerDetail(customer);
                $content.html(html);
            }
        );
    });
    
    /**
     * Render detail HTML
     */
    function renderCustomerDetail(customer) {
        let html = '<div class="saw-detail-grid">';
        
        // Logo a základní info
        html += '<div class="saw-detail-header">';
        if (customer.logo_url) {
            html += '<img src="' + customer.logo_url + '" alt="Logo" class="saw-detail-logo">';
        }
        html += '<div class="saw-detail-header-info">';
        html += '<h2>' + escapeHtml(customer.name) + '</h2>';
        if (customer.ico) {
            html += '<p class="saw-text-muted">IČO: ' + escapeHtml(customer.ico) + '</p>';
        }
        html += '</div>';
        html += '</div>';
        
        // Sections
        html += '<div class="saw-detail-sections">';
        
        // Základní údaje
        html += '<div class="saw-detail-section">';
        html += '<h3>Základní údaje</h3>';
        html += '<dl>';
        html += renderDetailRow('Status', customer.status_label || customer.status);
        html += renderDetailRow('Typ předplatného', customer.subscription_type_label || customer.subscription_type);
        if (customer.dic) {
            html += renderDetailRow('DIČ', customer.dic);
        }
        if (customer.primary_color) {
            html += renderDetailRow('Hlavní barva', '<span class="saw-color-badge" style="background-color: ' + customer.primary_color + ';"></span> ' + customer.primary_color);
        }
        html += '</dl>';
        html += '</div>';
        
        // Kontaktní údaje
        if (customer.contact_person || customer.contact_email || customer.contact_phone) {
            html += '<div class="saw-detail-section">';
            html += '<h3>Kontaktní údaje</h3>';
            html += '<dl>';
            if (customer.contact_person) {
                html += renderDetailRow('Kontaktní osoba', customer.contact_person);
            }
            if (customer.contact_email) {
                html += renderDetailRow('Email', '<a href="mailto:' + customer.contact_email + '">' + customer.contact_email + '</a>');
            }
            if (customer.contact_phone) {
                html += renderDetailRow('Telefon', '<a href="tel:' + customer.contact_phone + '">' + customer.contact_phone + '</a>');
            }
            html += '</dl>';
            html += '</div>';
        }
        
        // Adresa
        if (customer.formatted_operational_address) {
            html += '<div class="saw-detail-section">';
            html += '<h3>Provozní adresa</h3>';
            html += '<p>' + escapeHtml(customer.formatted_operational_address) + '</p>';
            html += '</div>';
        }
        
        // Fakturační adresa
        if (customer.formatted_billing_address) {
            html += '<div class="saw-detail-section">';
            html += '<h3>Fakturační adresa</h3>';
            html += '<p>' + escapeHtml(customer.formatted_billing_address) + '</p>';
            html += '</div>';
        }
        
        // Poznámky
        if (customer.notes) {
            html += '<div class="saw-detail-section">';
            html += '<h3>Poznámky</h3>';
            html += '<p>' + escapeHtml(customer.notes) + '</p>';
            html += '</div>';
        }
        
        // Metadata
        html += '<div class="saw-detail-section saw-detail-meta">';
        html += '<p class="saw-text-small saw-text-muted">';
        if (customer.created_at_formatted) {
            html += 'Vytvořeno: ' + customer.created_at_formatted;
        }
        if (customer.updated_at_formatted) {
            html += ' | Upraveno: ' + customer.updated_at_formatted;
        }
        html += '</p>';
        html += '</div>';
        
        html += '</div>'; // .saw-detail-sections
        html += '</div>'; // .saw-detail-grid
        
        return html;
    }
    
    /**
     * Render detail row (dt + dd)
     */
    function renderDetailRow(label, value) {
        if (!value || value === '-' || value === '') {
            return '';
        }
        return '<dt>' + escapeHtml(label) + '</dt><dd>' + value + '</dd>';
    }
    
    /**
     * Escape HTML
     */
    function escapeHtml(text) {
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
    
    // ===== MODAL CONTROLS =====
    
    /**
     * Zavření modalu (X button, overlay, ESC)
     */
    $(document).on('click', '[data-modal-close]', function() {
        const modalId = $(this).closest('.saw-modal').attr('id');
        SAW_Modal.close(modalId);
    });
    
    $(document).on('click', '.saw-modal-overlay', function(e) {
        if (e.target === this) {
            const modalId = $(this).closest('.saw-modal').attr('id');
            SAW_Modal.close(modalId);
        }
    });
    
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('body').hasClass('saw-modal-active')) {
            const $openModal = $('.saw-modal.saw-modal-open');
            if ($openModal.length > 0) {
                SAW_Modal.close($openModal.attr('id'));
            }
        }
    });
    
    // ===== DELETE CONFIRMATION =====
    
    $(document).on('click', '.saw-delete-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const id = $(this).data('id');
        const name = $(this).data('name');
        const entity = $(this).data('entity') || 'customers';
        
        if (!confirm('Opravdu chcete smazat "' + name + '"?')) {
            return;
        }
        
        const $btn = $(this);
        $btn.prop('disabled', true).text('Mažu...');
        
        $.ajax({
            url: sawGlobal.ajaxurl,
            method: 'POST',
            data: {
                action: 'saw_delete_' + entity,
                nonce: sawGlobal.deleteNonce || sawGlobal.nonce,
                entity: entity,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    // Reload page
                    location.reload();
                } else {
                    alert('Chyba: ' + (response.data?.message || 'Neznámá chyba'));
                    $btn.prop('disabled', false).text('Smazat');
                }
            },
            error: function() {
                alert('Chyba při mazání');
                $btn.prop('disabled', false).text('Smazat');
            }
        });
    });
    
    // ===== GLOBAL HELPERS =====
    
    /**
     * Show toast notification
     */
    window.sawShowToast = function(message, type = 'success') {
        const $toast = $('<div class="saw-toast saw-toast-' + type + '">' + escapeHtml(message) + '</div>');
        $('body').append($toast);
        
        setTimeout(function() {
            $toast.addClass('saw-toast-show');
        }, 10);
        
        setTimeout(function() {
            $toast.removeClass('saw-toast-show');
            setTimeout(function() {
                $toast.remove();
            }, 300);
        }, 3000);
    };
    
    // Export modal API
    window.SAW_Modal = SAW_Modal;
    
})(jQuery);