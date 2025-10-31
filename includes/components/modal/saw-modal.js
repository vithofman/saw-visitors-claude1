/**
 * SAW Modal Component JavaScript
 * 
 * Univerzální modal systém s podporou:
 * - Otevírání/zavírání modálů
 * - AJAX načítání obsahu
 * - Event handling (ESC, backdrop click)
 * - Multiple modals support
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 * @since   4.6.1
 */

(function($) {
    'use strict';
    
    /**
     * SAW Modal System
     */
    const SAWModal = {
        
        /**
         * Open modal
         * 
         * @param {string} modalId - Modal ID (bez prefixu 'saw-modal-')
         * @param {object} data - Data pro AJAX request (optional)
         */
        open: function(modalId, data = {}) {
            const fullId = this.getFullId(modalId);
            const $modal = $('#' + fullId);
            
            if ($modal.length === 0) {
                console.error('SAWModal: Modal not found:', fullId);
                return;
            }
            
            console.log('🔵 SAWModal: Opening modal', fullId);
            
            // Check if AJAX enabled
            const ajaxEnabled = $modal.data('ajax-enabled') === 1;
            
            if (ajaxEnabled) {
                this.loadAjaxContent($modal, data);
            }
            
            // Open modal
            $modal.addClass('saw-modal-open');
            $('body').addClass('saw-modal-active');
            
            // Focus close button
            $modal.find('[data-modal-close]').first().focus();
            
            // Trigger event
            $(document).trigger('saw:modal:opened', {
                modalId: modalId,
                fullId: fullId,
                data: data
            });
        },
        
        /**
         * Close modal
         * 
         * @param {string} modalId - Modal ID (bez prefixu 'saw-modal-')
         */
        close: function(modalId) {
            const fullId = this.getFullId(modalId);
            const $modal = $('#' + fullId);
            
            if ($modal.length === 0) {
                console.error('SAWModal: Modal not found:', fullId);
                return;
            }
            
            console.log('🔵 SAWModal: Closing modal', fullId);
            
            $modal.removeClass('saw-modal-open');
            
            // Remove body class only if no other modals are open
            if ($('.saw-modal.saw-modal-open').length === 0) {
                $('body').removeClass('saw-modal-active');
            }
            
            // Trigger event
            $(document).trigger('saw:modal:closed', {
                modalId: modalId,
                fullId: fullId
            });
        },
        
        /**
         * Load content via AJAX
         * 
         * @param {jQuery} $modal - Modal jQuery object
         * @param {object} data - Data to send with AJAX request
         */
        loadAjaxContent: function($modal, data = {}) {
            const ajaxAction = $modal.data('ajax-action');
            const ajaxData = $modal.data('ajax-data') || {};
            const $content = $modal.find('.saw-modal-body');
            
            if (!ajaxAction) {
                console.error('SAWModal: No AJAX action specified');
                return;
            }
            
            console.log('🔵 SAWModal: Loading AJAX content', {
                action: ajaxAction,
                data: data,
                ajaxData: ajaxData
            });
            
            // Show loading state
            $content.html('<div class="saw-modal-loading"><div class="saw-spinner"></div><p>Načítám...</p></div>');
            
            // Merge data - use custom nonce from data if available, otherwise use default
            const requestData = $.extend({}, ajaxData, data, {
                action: ajaxAction
            });
            
            // Add nonce - prefer custom nonce from data, fallback to global
            if (!requestData.nonce) {
                requestData.nonce = sawModalGlobal.nonce;
            }
            
            // AJAX request
            $.ajax({
                url: sawModalGlobal.ajaxurl,
                method: 'POST',
                data: requestData,
                success: (response) => {
                    console.log('✅ SAWModal: AJAX response received', response);
                    
                    if (response.success) {
                        this.handleAjaxSuccess($modal, $content, response.data);
                    } else {
                        this.handleAjaxError($modal, $content, response.data?.message || 'Neznámá chyba');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('❌ SAWModal: AJAX error', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                    
                    this.handleAjaxError($modal, $content, 'Chyba serveru (' + xhr.status + ')');
                }
            });
        },
        
        /**
         * Handle successful AJAX response
         * 
         * @param {jQuery} $modal - Modal jQuery object
         * @param {jQuery} $content - Content container
         * @param {object} data - Response data
         */
        handleAjaxSuccess: function($modal, $content, data) {
            // Check if data contains HTML content directly
            if (typeof data === 'string') {
                $content.html(data);
                return;
            }
            
            // Check for 'html' property
            if (data.html) {
                $content.html(data.html);
                return;
            }
            
            // Check for 'content' property
            if (data.content) {
                $content.html(data.content);
                return;
            }
            
            // For customer detail - render using template
            if (data.customer || data.customers || data.item) {
                const item = data.customer || data.customers || data.item;
                const html = this.renderCustomerDetail(item);
                $content.html(html);
                return;
            }
            
            // Fallback: show raw data
            console.warn('SAWModal: Unknown response format, rendering as JSON', data);
            $content.html('<pre>' + JSON.stringify(data, null, 2) + '</pre>');
        },
        
        /**
         * Handle AJAX error
         * 
         * @param {jQuery} $modal - Modal jQuery object
         * @param {jQuery} $content - Content container
         * @param {string} message - Error message
         */
        handleAjaxError: function($modal, $content, message) {
            $content.html(
                '<div class="saw-alert saw-alert-danger">' +
                '<strong>Chyba:</strong> ' + this.escapeHtml(message) +
                '</div>'
            );
        },
        
        /**
         * Render customer detail HTML
         * 
         * @param {object} customer - Customer data
         * @return {string} HTML
         */
        renderCustomerDetail: function(customer) {
            let html = '<div class="saw-detail-grid">';
            
            // Header with logo
            html += '<div class="saw-detail-header">';
            if (customer.logo_url) {
                html += '<img src="' + customer.logo_url + '" alt="Logo" class="saw-detail-logo">';
            }
            html += '<div class="saw-detail-header-info">';
            html += '<h2>' + this.escapeHtml(customer.name) + '</h2>';
            if (customer.ico) {
                html += '<p class="saw-text-muted">IČO: ' + this.escapeHtml(customer.ico) + '</p>';
            }
            html += '</div>';
            html += '</div>';
            
            html += '<div class="saw-detail-sections">';
            
            // Basic info
            html += '<div class="saw-detail-section">';
            html += '<h3>Základní údaje</h3>';
            html += '<dl>';
            html += this.renderDetailRow('Status', customer.status_label || customer.status);
            html += this.renderDetailRow('Typ předplatného', customer.subscription_type_label || customer.subscription_type);
            if (customer.dic) {
                html += this.renderDetailRow('DIČ', customer.dic);
            }
            if (customer.primary_color) {
                html += this.renderDetailRow('Hlavní barva', '<span class="saw-color-badge" style="background-color: ' + customer.primary_color + '; width: 24px; height: 24px; display: inline-block; border-radius: 4px;"></span> ' + customer.primary_color);
            }
            html += '</dl>';
            html += '</div>';
            
            // Contact info
            if (customer.contact_person || customer.contact_email || customer.contact_phone) {
                html += '<div class="saw-detail-section">';
                html += '<h3>Kontaktní údaje</h3>';
                html += '<dl>';
                if (customer.contact_person) {
                    html += this.renderDetailRow('Kontaktní osoba', customer.contact_person);
                }
                if (customer.contact_email) {
                    html += this.renderDetailRow('Email', '<a href="mailto:' + customer.contact_email + '">' + customer.contact_email + '</a>');
                }
                if (customer.contact_phone) {
                    html += this.renderDetailRow('Telefon', '<a href="tel:' + customer.contact_phone + '">' + customer.contact_phone + '</a>');
                }
                html += '</dl>';
                html += '</div>';
            }
            
            // Operational address
            if (customer.formatted_operational_address) {
                html += '<div class="saw-detail-section">';
                html += '<h3>Provozní adresa</h3>';
                html += '<p>' + this.escapeHtml(customer.formatted_operational_address) + '</p>';
                html += '</div>';
            }
            
            // Billing address
            if (customer.formatted_billing_address) {
                html += '<div class="saw-detail-section">';
                html += '<h3>Fakturační adresa</h3>';
                html += '<p>' + this.escapeHtml(customer.formatted_billing_address) + '</p>';
                html += '</div>';
            }
            
            // Notes
            if (customer.notes) {
                html += '<div class="saw-detail-section">';
                html += '<h3>Poznámky</h3>';
                html += '<p>' + this.escapeHtml(customer.notes) + '</p>';
                html += '</div>';
            }
            
            // Meta info
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
            
            html += '</div>';
            html += '</div>';
            
            return html;
        },
        
        /**
         * Render detail row (dt/dd pair)
         * 
         * @param {string} label - Label
         * @param {string} value - Value
         * @return {string} HTML
         */
        renderDetailRow: function(label, value) {
            if (!value || value === '-' || value === '') {
                return '';
            }
            return '<dt>' + this.escapeHtml(label) + '</dt><dd>' + value + '</dd>';
        },
        
        /**
         * Get full modal ID with prefix
         * 
         * @param {string} modalId - Short modal ID
         * @return {string} Full ID
         */
        getFullId: function(modalId) {
            if (modalId.startsWith('saw-modal-')) {
                return modalId;
            }
            return 'saw-modal-' + modalId;
        },
        
        /**
         * Escape HTML
         * 
         * @param {string} text - Text to escape
         * @return {string} Escaped text
         */
        escapeHtml: function(text) {
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
    
    /**
     * Event Handlers
     */
    
    // Close button click
    $(document).on('click', '[data-modal-close]', function() {
        const $modal = $(this).closest('.saw-modal');
        const modalId = $modal.attr('id').replace('saw-modal-', '');
        SAWModal.close(modalId);
    });
    
    // Backdrop click
    $(document).on('click', '.saw-modal-overlay', function(e) {
        const $modal = $(this).closest('.saw-modal');
        const closeOnBackdrop = $modal.data('close-backdrop') !== 0;
        
        if (closeOnBackdrop) {
            const modalId = $modal.attr('id').replace('saw-modal-', '');
            SAWModal.close(modalId);
        }
    });
    
    // ESC key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('body').hasClass('saw-modal-active')) {
            const $openModal = $('.saw-modal.saw-modal-open').last();
            
            if ($openModal.length > 0) {
                const closeOnEscape = $openModal.data('close-escape') !== 0;
                
                if (closeOnEscape) {
                    const modalId = $openModal.attr('id').replace('saw-modal-', '');
                    SAWModal.close(modalId);
                }
            }
        }
    });
    
    // Footer button actions
    $(document).on('click', '[data-modal-action]', function() {
        const action = $(this).data('modal-action');
        const $modal = $(this).closest('.saw-modal');
        const modalId = $modal.attr('id').replace('saw-modal-', '');
        
        if (action === 'close') {
            SAWModal.close(modalId);
        } else if (action === 'confirm') {
            $(document).trigger('saw:modal:confirmed', {
                modalId: modalId,
                button: this
            });
        } else {
            $(document).trigger('saw:modal:action', {
                modalId: modalId,
                action: action,
                button: this
            });
        }
    });
    
    /**
     * Initialize
     */
    $(document).ready(function() {
        console.log('🚀 SAWModal initialized');
    });
    
    /**
     * Export to global scope
     */
    window.SAWModal = SAWModal;
    
})(jQuery);
