/**
 * Invitation Autosave
 * 
 * Automatically saves visitor data every 30 seconds
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

(function($) {
    'use strict';
    
    let autosaveTimer;
    let isDirty = false;
    let isSaving = false;
    
    // Get invitation token from global or data attribute
    const INVITATION_TOKEN = (window.sawInvitation && window.sawInvitation.token) || window.INVITATION_TOKEN || $('body').data('invitation-token') || '';
    
    if (!INVITATION_TOKEN) {
        console.log('[Autosave] No invitation token found, autosave disabled');
        return;
    }
    
    /**
     * Get visitors data from form
     */
    function getVisitorsData() {
        const visitors = [];
        
        // Get existing visitors (checkboxes)
        $('input[name^="visitor_"]:checked').each(function() {
            const visitorId = $(this).val();
            const trainingSkip = $(this).closest('tr, .visitor-row').find('input[name^="training_skip"]').is(':checked');
            
            visitors.push({
                id: visitorId,
                training_skip: trainingSkip ? 1 : 0
            });
        });
        
        // Get new visitors (form inputs)
        $('.new-visitor-row, [data-visitor="new"]').each(function() {
            const firstName = $(this).find('input[name*="first_name"]').val();
            const lastName = $(this).find('input[name*="last_name"]').val();
            const trainingSkip = $(this).find('input[name*="training_skip"]').is(':checked');
            
            if (firstName && lastName) {
                visitors.push({
                    id: null,
                    first_name: firstName,
                    last_name: lastName,
                    training_skip: trainingSkip ? 1 : 0
                });
            }
        });
        
        return visitors;
    }
    
    /**
     * Perform autosave
     */
    function doAutosave() {
        if (!isDirty || isSaving) {
            return;
        }
        
        isSaving = true;
        const visitors = getVisitorsData();
        
        $.ajax({
            url: sawGlobal.ajaxurl || ajaxurl,
            method: 'POST',
            data: {
                action: 'saw_invitation_autosave',
                token: INVITATION_TOKEN,
                visitors: visitors,
                nonce: sawGlobal.nonce || $('#_wpnonce').val()
            },
            success: function(response) {
                if (response.success) {
                    isDirty = false;
                    showSaveIndicator('✓ Uloženo ' + new Date().toLocaleTimeString());
                } else {
                    console.error('[Autosave] Error:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('[Autosave] AJAX error:', error);
            },
            complete: function() {
                isSaving = false;
            }
        });
    }
    
    /**
     * Show save indicator
     */
    function showSaveIndicator(message) {
        let indicator = $('#autosave-indicator');
        
        if (indicator.length === 0) {
            indicator = $('<div id="autosave-indicator"></div>');
            $('body').append(indicator);
        }
        
        indicator.text(message).fadeIn(200);
        
        setTimeout(function() {
            indicator.fadeOut(200);
        }, 2000);
    }
    
    /**
     * Initialize autosave
     */
    function initAutosave() {
        // Mark as dirty on any change
        $(document).on('change keyup', 'input, textarea, select', function() {
            isDirty = true;
            clearTimeout(autosaveTimer);
            autosaveTimer = setTimeout(doAutosave, 30000); // 30s
        });
        
        // Manual save before leaving
        window.addEventListener('beforeunload', function(e) {
            if (isDirty && !isSaving) {
                // Try to save synchronously (may not work in all browsers)
                $.ajax({
                    url: sawGlobal.ajaxurl || ajaxurl,
                    method: 'POST',
                    async: false,
                    data: {
                        action: 'saw_invitation_autosave',
                        token: INVITATION_TOKEN,
                        visitors: getVisitorsData(),
                        nonce: sawGlobal.nonce || $('#_wpnonce').val()
                    }
                });
                
                e.returnValue = 'Máte neuložené změny';
            }
        });
        
        console.log('[Autosave] Initialized for invitation token:', INVITATION_TOKEN);
    }
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        initAutosave();
    });
    
    // Add styles for indicator
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            #autosave-indicator {
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: #10b981;
                color: white;
                padding: 0.75rem 1.5rem;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                z-index: 10000;
                font-size: 0.875rem;
                font-weight: 600;
                display: none;
            }
        `)
        .appendTo('head');
    
})(jQuery);

