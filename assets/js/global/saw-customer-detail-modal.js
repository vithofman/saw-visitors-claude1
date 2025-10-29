/**
 * SAW Customer Detail Modal
 * 
 * Responsivní modal pro zobrazení detailu zákazníka
 * - Desktop: Modal s blur backdrop
 * - Mobile: Fullscreen view
 * - Branded header with customer color
 * 
 * @package SAW_Visitors
 * @since 4.6.1
 * @version REDESIGNED - Branded header implementation
 */

(function($) {
    'use strict';
    
    const SawCustomerModal = {
        
        $modal: null,
        $modalBody: null,
        $modalContent: null,
        $modalLoading: null,
        $modalFooter: null,
        currentCustomerId: null,
        currentCustomerData: null,
        
        /**
         * Initialize modal functionality
         */
        init: function() {
            this.$modal = $('#saw-customer-detail-modal');
            this.$modalBody = $('#saw-modal-body');
            this.$modalContent = $('#saw-modal-content');
            this.$modalLoading = $('#saw-modal-loading');
            this.$modalFooter = $('#saw-modal-footer');
            
            if (this.$modal.length === 0) {
                console.log('SAW Customer Modal: Modal element not found, skipping init');
                return;
            }
            
            this.bindEvents();
            this.bindRowClickEvent();
            
            console.log('SAW Customer Modal: Initialized');
        },
        
        /**
         * Bind all modal events
         */
        bindEvents: function() {
            const self = this;
            
            // Close buttons
            $('#saw-modal-close, #saw-modal-mobile-close').on('click', function() {
                self.closeModal();
            });
            
            // Click outside to close (pouze desktop)
            this.$modal.on('click', function(e) {
                if ($(e.target).hasClass('saw-modal-overlay') || $(e.target).hasClass('saw-modal-desktop')) {
                    self.closeModal();
                }
            });
            
            // ESC to close
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && self.$modal.is(':visible')) {
                    self.closeModal();
                }
            });
            
            // Edit buttons
            $('#saw-modal-edit, #saw-modal-mobile-edit').on('click', function() {
                self.handleEdit();
            });
            
            // Delete buttons
            $('#saw-modal-delete, #saw-modal-mobile-delete').on('click', function() {
                self.handleDelete();
            });
        },
        
        /**
         * Bind row click event from admin table
         */
        bindRowClickEvent: function() {
            const self = this;
            
            $(document).on('saw:table:row-click', function(e, data) {
                if (data.entity === 'customers') {
                    self.openModal(data.id, data.rowData);
                }
            });
        },
        
        /**
         * Open modal and load customer data
         * 
         * @param {number} customerId - Customer ID
         * @param {object} rowData - Optional cached row data
         */
        openModal: function(customerId, rowData) {
            console.log('SAW Customer Modal: Opening for customer', customerId);
            
            this.currentCustomerId = customerId;
            this.currentCustomerData = rowData || null;
            
            // Show modal
            this.$modal.fadeIn(200);
            $('body').css('overflow', 'hidden');
            
            // Show loading
            this.$modalLoading.show();
            this.$modalContent.hide();
            this.$modalFooter.hide();
            
            // Load customer data
            this.loadCustomerData(customerId);
        },
        
        /**
         * Close modal
         */
        closeModal: function() {
            console.log('SAW Customer Modal: Closing');
            
            this.$modal.fadeOut(200);
            $('body').css('overflow', '');
            
            this.currentCustomerId = null;
            this.currentCustomerData = null;
        },
        
        /**
         * Load customer data via AJAX
         * 
         * @param {number} customerId - Customer ID
         */
        loadCustomerData: function(customerId) {
            const self = this;
            
            // Pokud máme data z řádku, použij je okamžitě
            if (this.currentCustomerData) {
                console.log('SAW Customer Modal: Using cached row data');
                this.renderCustomerData(this.currentCustomerData);
                return;
            }
            
            // Jinak načti přes AJAX
            console.log('SAW Customer Modal: Loading via AJAX');
            
            $.ajax({
                url: sawCustomerModal.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'saw_get_customer_detail',
                    nonce: sawCustomerModal.nonce,
                    customer_id: customerId
                },
                success: function(response) {
                    if (response.success) {
                        console.log('SAW Customer Modal: Data loaded successfully');
                        self.renderCustomerData(response.data.customer);
                    } else {
                        console.error('SAW Customer Modal: Failed to load', response.data);
                        self.showError(response.data.message || 'Nepodařilo se načíst data');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('SAW Customer Modal: AJAX error', error);
                    self.showError('Chyba při komunikaci se serverem');
                }
            });
        },
        
        /**
         * Render customer data in modal
         * 
         * @param {object} customer - Customer data
         */
        renderCustomerData: function(customer) {
            console.log('SAW Customer Modal: Rendering data', customer);
            
            // ✨ NOVÉ: Aplikuj branding barvu na header
            this.applyBrandingColor(customer.primary_color || '#2563eb');
            
            // Header - název a IČO
            $('#saw-modal-title, #saw-modal-mobile-title').text(customer.name || 'Neznámý zákazník');
            $('#saw-modal-ico').text(customer.ico ? 'IČO: ' + customer.ico : '');
            
            // Header logo
            if (customer.logo_url_full || customer.logo_url) {
                const logoUrl = customer.logo_url_full || customer.logo_url;
                $('#saw-modal-logo').show();
                $('#saw-modal-logo-img').attr('src', logoUrl);
            } else {
                $('#saw-modal-logo').hide();
            }
            
            // Základní informace
            $('#saw-info-name').text(customer.name || '—');
            $('#saw-info-ico').text(customer.ico || '—');
            $('#saw-info-address').html(customer.address ? customer.address.replace(/\n/g, '<br>') : '—');
            
            // ✨ ODSTRANĚNO: Branding sekce již není součástí modalu
            
            // Poznámky
            if (customer.notes && customer.notes.trim() !== '') {
                $('#saw-notes-section').show();
                $('#saw-info-notes').html(customer.notes.replace(/\n/g, '<br>'));
            } else {
                $('#saw-notes-section').hide();
            }
            
            // Metadata
            $('#saw-info-created').text(this.formatDate(customer.created_at));
            
            if (customer.updated_at) {
                $('#saw-updated-row').show();
                $('#saw-info-updated').text(this.formatDate(customer.updated_at));
            } else {
                $('#saw-updated-row').hide();
            }
            
            // Copy content to mobile view
            $('#saw-modal-mobile-body').html(this.$modalContent.html());
            
            // Show content
            this.$modalLoading.hide();
            this.$modalContent.show();
            this.$modalFooter.show();
        },
        
        /**
         * ✨ NOVÉ: Aplikuj branding barvu na header s kontrastním textem
         * 
         * @param {string} color - Hex barva (např. #2563eb)
         */
        applyBrandingColor: function(color) {
            // Ověř že color je validní hex
            if (!color || !color.match(/^#[0-9A-F]{6}$/i)) {
                console.warn('SAW Customer Modal: Invalid color, using default', color);
                color = '#2563eb';
            }
            
            // Vypočti kontrastní barvu textu (bílá nebo černá)
            const textColor = this.getContrastColor(color);
            
            console.log('SAW Customer Modal: Applying branding color', {
                background: color,
                text: textColor
            });
            
            // Aplikuj na desktop header
            $('#saw-modal-header').css('background-color', color);
            $('#saw-modal-title').css('color', textColor);
            $('#saw-modal-ico').css('color', this.adjustOpacity(textColor, 0.9));
            $('#saw-modal-close').css('background-color', this.adjustOpacity(textColor, 0.2));
            $('#saw-modal-close .dashicons').css('color', this.adjustOpacity(textColor, 0.95));
            
            // Aplikuj na mobile header
            $('#saw-modal-mobile-header').css('background-color', color);
            $('#saw-modal-mobile-title').css('color', textColor);
            $('#saw-modal-mobile-close').css('background-color', this.adjustOpacity(textColor, 0.2));
            $('#saw-modal-mobile-close .dashicons').css('color', this.adjustOpacity(textColor, 0.95));
            $('#saw-modal-mobile-edit').css('background-color', this.adjustOpacity(textColor, 0.2));
            $('#saw-modal-mobile-edit .dashicons').css('color', this.adjustOpacity(textColor, 0.95));
        },
        
        /**
         * ✨ NOVÉ: Vypočítej kontrastní barvu textu (bílá nebo černá)
         * 
         * Používá algoritmus relativní luminance (WCAG)
         * 
         * @param {string} hexColor - Hex barva pozadí (např. #2563eb)
         * @return {string} '#ffffff' pro světlé pozadí, '#000000' pro tmavé
         */
        getContrastColor: function(hexColor) {
            // Převeď hex na RGB
            const rgb = this.hexToRgb(hexColor);
            
            // Vypočítej relativní luminanci
            // Vzorec: https://www.w3.org/TR/WCAG20/#relativeluminancedef
            const r = rgb.r / 255;
            const g = rgb.g / 255;
            const b = rgb.b / 255;
            
            const rLin = r <= 0.03928 ? r / 12.92 : Math.pow((r + 0.055) / 1.055, 2.4);
            const gLin = g <= 0.03928 ? g / 12.92 : Math.pow((g + 0.055) / 1.055, 2.4);
            const bLin = b <= 0.03928 ? b / 12.92 : Math.pow((b + 0.055) / 1.055, 2.4);
            
            const luminance = 0.2126 * rLin + 0.7152 * gLin + 0.0722 * bLin;
            
            // Pokud je luminance vyšší než 0.5, použij tmavý text, jinak světlý
            return luminance > 0.5 ? '#000000' : '#ffffff';
        },
        
        /**
         * ✨ NOVÉ: Převeď hex barvu na RGB objekt
         * 
         * @param {string} hex - Hex barva (např. #2563eb)
         * @return {object} {r: 37, g: 99, b: 235}
         */
        hexToRgb: function(hex) {
            // Odstraň # pokud tam je
            hex = hex.replace(/^#/, '');
            
            // Parsuj hex na RGB
            const bigint = parseInt(hex, 16);
            const r = (bigint >> 16) & 255;
            const g = (bigint >> 8) & 255;
            const b = bigint & 255;
            
            return {r: r, g: g, b: b};
        },
        
        /**
         * ✨ NOVÉ: Přidej průhlednost k barvě
         * 
         * @param {string} color - Hex nebo rgba barva
         * @param {number} opacity - Průhlednost (0-1)
         * @return {string} rgba barva
         */
        adjustOpacity: function(color, opacity) {
            if (color.startsWith('rgba')) {
                // Už je rgba, jen uprav opacity
                return color.replace(/[\d.]+\)$/g, opacity + ')');
            }
            
            // Převeď hex na rgba
            const rgb = this.hexToRgb(color);
            return 'rgba(' + rgb.r + ', ' + rgb.g + ', ' + rgb.b + ', ' + opacity + ')';
        },
        
        /**
         * Show error message
         * 
         * @param {string} message - Error message
         */
        showError: function(message) {
            this.$modalLoading.hide();
            this.$modalContent.html('<div class="saw-modal-error">' +
                '<span class="dashicons dashicons-warning"></span>' +
                '<p>' + message + '</p>' +
                '</div>');
            this.$modalContent.show();
        },
        
        /**
         * Handle edit button click
         */
        handleEdit: function() {
            if (!this.currentCustomerId) {
                console.error('SAW Customer Modal: No customer ID');
                return;
            }
            
            // Použij editUrl z localized data
            const editUrl = sawCustomerModal.editUrl.replace('{id}', this.currentCustomerId);
            console.log('SAW Customer Modal: Redirecting to edit', editUrl);
            
            window.location.href = editUrl;
        },
        
        /**
         * Handle delete button click
         */
        handleDelete: function() {
            if (!this.currentCustomerId) {
                console.error('SAW Customer Modal: No customer ID');
                return;
            }
            
            const customerName = this.currentCustomerData?.name || 'tohoto zákazníka';
            
            if (!confirm('Opravdu chcete smazat "' + customerName + '"?\n\nTato akce je nevratná!')) {
                return;
            }
            
            console.log('SAW Customer Modal: Deleting customer', this.currentCustomerId);
            
            // Close modal
            this.closeModal();
            
            // Trigger delete event (bude zpracováno v saw-admin-table-ajax.js)
            $(document).trigger('saw:admin-table:delete', {
                entity: 'customers',
                id: this.currentCustomerId,
                name: customerName,
                $button: null // Není z buttonu, ale z modalu
            });
        },
        
        /**
         * Format date to Czech format
         * 
         * @param {string} dateString - Date string
         * @return {string} Formatted date
         */
        formatDate: function(dateString) {
            if (!dateString) {
                return '—';
            }
            
            try {
                const date = new Date(dateString);
                const day = String(date.getDate()).padStart(2, '0');
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const year = date.getFullYear();
                const hours = String(date.getHours()).padStart(2, '0');
                const minutes = String(date.getMinutes()).padStart(2, '0');
                
                return day + '. ' + month + '. ' + year + ' ' + hours + ':' + minutes;
            } catch (e) {
                return dateString;
            }
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        SawCustomerModal.init();
    });
    
    // Expose to global scope
    window.SawCustomerModal = SawCustomerModal;
    
})(jQuery);