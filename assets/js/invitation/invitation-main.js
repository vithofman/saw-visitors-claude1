/**
 * Invitation Main JavaScript
 * 
 * Core functionality for visitor invitation flow
 */

(function($) {
    'use strict';
    
    const InvitationApp = {
        init: function() {
            this.bindEvents();
            this.initForms();
        },
        
        bindEvents: function() {
            // Form validation
            $('.saw-visitors-form').on('submit', this.validateVisitorsForm);
        },
        
        initForms: function() {
            // Auto-save indicators
            this.setupAutosave();
        },
        
        validateVisitorsForm: function(e) {
            const existingChecked = $('input[name="existing_visitor_ids[]"]:checked').length;
            const newVisitors = $('.saw-visitor-form').length;
            
            if (existingChecked === 0 && newVisitors === 0) {
                e.preventDefault();
                alert('Musíte vybrat nebo zadat alespoň jednoho návštěvníka');
                return false;
            }
            
            return true;
        },
        
        setupAutosave: function() {
            if (typeof sawInvitation === 'undefined' || !sawInvitation.autosaveNonce) {
                return;
            }
            
            // Autosave handled by invitation-autosave.js
        }
    };
    
    $(document).ready(function() {
        InvitationApp.init();
    });
    
})(jQuery);

