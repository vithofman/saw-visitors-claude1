/**
 * Visitors Module Scripts
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visitors
 * @version     1.0.0
 */

(function($) {
    'use strict';
    
    // ========================================
    // CERTIFICATES MANAGER
    // ========================================
    const SAWVisitorCertificates = {
        
        init: function() {
            this.certificateIndex = $('#certificates-container .saw-certificate-row').length || 0;
            this.bindEvents();
        },
        
        bindEvents: function() {
            $(document).on('click', '#add-certificate-btn', this.addCertificate.bind(this));
            $(document).on('click', '.saw-remove-certificate', this.removeCertificate.bind(this));
        },
        
        addCertificate: function(e) {
            e.preventDefault();
            
            const html = `
                <div class="saw-certificate-row" data-index="${this.certificateIndex}">
                    <div class="saw-form-row" style="margin-bottom: 0;">
                        <div class="saw-form-group saw-col-4">
                            <label class="saw-label">Název průkazu</label>
                            <input type="text" 
                                   name="certificates[${this.certificateIndex}][certificate_name]" 
                                   class="saw-input" 
                                   placeholder="např. Svářečský průkaz">
                        </div>
                        
                        <div class="saw-form-group saw-col-3">
                            <label class="saw-label">Číslo průkazu</label>
                            <input type="text" 
                                   name="certificates[${this.certificateIndex}][certificate_number]" 
                                   class="saw-input" 
                                   placeholder="ABC123456">
                        </div>
                        
                        <div class="saw-form-group saw-col-3">
                            <label class="saw-label">Platnost do</label>
                            <input type="date" 
                                   name="certificates[${this.certificateIndex}][valid_until]" 
                                   class="saw-input">
                        </div>
                        
                        <div class="saw-form-group saw-col-2" style="display: flex; align-items: flex-end;">
                            <button type="button" class="saw-button saw-button-danger saw-remove-certificate" style="width: 100%;">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            $('#certificates-container').append(html);
            this.certificateIndex++;
        },
        
        removeCertificate: function(e) {
            e.preventDefault();
            
            const $row = $(e.currentTarget).closest('.saw-certificate-row');
            
            // Confirm before removing
            if (confirm('Opravdu chcete odstranit tento průkaz?')) {
                $row.fadeOut(200, function() {
                    $(this).remove();
                });
            }
        }
    };
    
    // ========================================
    // DETAIL TABS
    // ========================================
    const SAWVisitorDetailTabs = {
        
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            $(document).on('click', '.saw-detail-tab', this.switchTab.bind(this));
        },
        
        switchTab: function(e) {
            e.preventDefault();
            
            const $tab = $(e.currentTarget);
            const tabName = $tab.data('tab');
            
            // Update active tab
            $('.saw-detail-tab').removeClass('active');
            $tab.addClass('active');
            
            // Update active content
            $('.saw-detail-tab-content').removeClass('active');
            $('[data-tab-content="' + tabName + '"]').addClass('active');
            
            // Update URL without reload
            if (history.pushState) {
                const url = new URL(window.location);
                url.searchParams.set('tab', tabName);
                history.pushState({}, '', url);
            }
        }
    };
    
    // ========================================
    // INITIALIZATION
    // ========================================
    $(document).ready(function() {
        // Only initialize if we're on visitors module
        if ($('.saw-module-visitors').length > 0) {
            SAWVisitorCertificates.init();
        }
        
        // Initialize detail tabs if present
        if ($('.saw-detail-tabs').length > 0) {
            SAWVisitorDetailTabs.init();
        }
    });
    
})(jQuery);
