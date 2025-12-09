/**
 * Invitation Autosave
 * 
 * Automatically saves invitation data (risks text + visitors) every 30 seconds
 * Works for all invitation steps (risks, visitors)
 * 
 * @package SAW_Visitors
 * @version 3.9.10 - Removed toast notifications and fixed beforeunload
 */

(function($) {
    'use strict';
    
    let autosaveTimer;
    let isDirty = false;
    let isSaving = false;
    let formIsSubmitting = false;
    
    // Check if sawInvitation is available
    if (typeof sawInvitation === 'undefined') {
        console.log('[Invitation Autosave] Not in invitation context');
        return;
    }
    
    if (!sawInvitation.autosaveNonce) {
        console.warn('[Invitation Autosave] Missing autosaveNonce');
        return;
    }
    
    const token = sawInvitation.token;
    const ajaxurl = sawInvitation.ajaxurl;
    const nonce = sawInvitation.autosaveNonce;
    const currentStep = sawInvitation.currentStep || '';
    
    /**
     * Get risks text from editor
     */
    function getRisksText() {
        if (typeof tinymce !== 'undefined') {
            const editor = tinymce.get('risks_text');
            if (editor) {
                return editor.getContent();
            }
        }
        
        const textarea = $('#risks_text');
        if (textarea.length) {
            return textarea.val();
        }
        
        return '';
    }
    
    /**
     * Get visitors data from form
     */
    function getVisitorsData() {
        const visitors = [];
        
        $('input[name^="visitor_"]:checked, input[name^="existing_visitor_ids"]:checked').each(function() {
            const visitorId = $(this).val();
            const $row = $(this).closest('tr, .visitor-row, [data-visitor-id]');
            const trainingSkip = $row.find('input[name^="training_skip"], input[name*="training_skip"]').is(':checked');
            
            visitors.push({
                id: parseInt(visitorId, 10),
                training_skip: trainingSkip ? 1 : 0
            });
        });
        
        const existingIds = $('input[name="existing_visitor_ids[]"]:checked').map(function() {
            return parseInt($(this).val(), 10);
        }).get();
        
        existingIds.forEach(function(id) {
            const exists = visitors.some(function(v) {
                return v.id === id;
            });
            
            if (!exists) {
                const $row = $('[data-visitor-id="' + id + '"], input[value="' + id + '"]').closest('tr, .visitor-row');
                const trainingSkip = $row.find('input[name*="training_skip"]').is(':checked');
                
                visitors.push({
                    id: id,
                    training_skip: trainingSkip ? 1 : 0
                });
            }
        });
        
        $('.new-visitor-row, [data-visitor="new"]').each(function() {
            const $row = $(this);
            const firstName = $row.find('input[name*="first_name"], input[name*="[first_name]"]').val();
            const lastName = $row.find('input[name*="last_name"], input[name*="[last_name]"]').val();
            const trainingSkip = $row.find('input[name*="training_skip"]').is(':checked');
            
            if (firstName && lastName) {
                visitors.push({
                    id: null,
                    first_name: firstName.trim(),
                    last_name: lastName.trim(),
                    position: $row.find('input[name*="position"]').val() || '',
                    email: $row.find('input[name*="email"]').val() || '',
                    phone: $row.find('input[name*="phone"]').val() || '',
                    training_skip: trainingSkip ? 1 : 0
                });
            }
        });
        
        return visitors.filter(function(v) {
            if (v.id !== null) {
                return true;
            }
            return v.first_name && v.last_name;
        });
    }
    
    /**
     * Detect current invitation step
     */
    function detectCurrentStep() {
        if (currentStep) {
            return currentStep;
        }
        
        const urlParams = new URLSearchParams(window.location.search);
        const step = urlParams.get('step');
        if (step) {
            return step;
        }
        
        if ($('#risks_text').length > 0 || $('.saw-risks-step').length > 0) {
            return 'risks';
        }
        
        if ($('input[name^="visitor_"]').length > 0 || $('.saw-visitors-step').length > 0) {
            return 'visitors';
        }
        
        return '';
    }
    
    /**
     * Perform autosave (silent, no notifications)
     */
    function doAutosave() {
        if (!isDirty || isSaving) {
            return;
        }
        
        isSaving = true;
        const step = detectCurrentStep();
        const autosaveData = {};
        
        if (typeof tinymce !== 'undefined') {
            tinymce.triggerSave();
        }
        
        if (step === 'risks') {
            const risksText = getRisksText();
            if (risksText) {
                autosaveData.risks_text = risksText;
            }
        } else if (step === 'visitors') {
            const visitors = getVisitorsData();
            if (visitors.length > 0) {
                autosaveData.visitors = visitors;
            }
        } else {
            isSaving = false;
            return;
        }
        
        if (Object.keys(autosaveData).length === 0) {
            isSaving = false;
            return;
        }
        
        const bodyParams = new URLSearchParams({
            action: 'saw_invitation_autosave',
            nonce: nonce,
            token: token,
            data: JSON.stringify(autosaveData)
        });
        
        fetch(ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: bodyParams
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                isDirty = false;
                // No toast notification - silent save
            }
        })
        .catch(error => {
            console.error('[Invitation Autosave] AJAX error:', error);
        })
        .finally(() => {
            isSaving = false;
        });
    }
    
    /**
     * Initialize autosave
     */
    function initAutosave() {
        const step = detectCurrentStep();
        
        if (step === 'risks') {
            $(document).on('change keyup', '#risks_text, .wp-editor-area', function() {
                isDirty = true;
                clearTimeout(autosaveTimer);
                autosaveTimer = setTimeout(doAutosave, 30000);
            });
            
            if (typeof tinymce !== 'undefined') {
                const editor = tinymce.get('risks_text');
                if (editor) {
                    editor.on('keyup change', function() {
                        isDirty = true;
                        clearTimeout(autosaveTimer);
                        autosaveTimer = setTimeout(doAutosave, 30000);
                    });
                }
                
                tinymce.on('AddEditor', function(e) {
                    if (e.editor.id === 'risks_text') {
                        e.editor.on('keyup change', function() {
                            isDirty = true;
                            clearTimeout(autosaveTimer);
                            autosaveTimer = setTimeout(doAutosave, 30000);
                        });
                    }
                });
            }
        } else if (step === 'visitors') {
            $(document).on('change keyup', 
                'input[name^="visitor_"], ' +
                'input[name^="existing_visitor_ids"], ' +
                'input[name^="new_visitors"], ' +
                'input[name*="first_name"], ' +
                'input[name*="last_name"], ' +
                'input[name*="training_skip"]',
                function() {
                    isDirty = true;
                    clearTimeout(autosaveTimer);
                    autosaveTimer = setTimeout(doAutosave, 30000);
                }
            );
        }
        
        // Disable beforeunload when form is submitted
        $('form').on('submit', function() {
            formIsSubmitting = true;
            isDirty = false;
        });
        
        // Also handle button clicks that submit forms
        $('button[type="submit"], input[type="submit"]').on('click', function() {
            formIsSubmitting = true;
            isDirty = false;
        });
        
        // Handle navigation links in sidebar
        $('.saw-sidebar a, .saw-progress-step a, .saw-nav-link').on('click', function() {
            formIsSubmitting = true;
            isDirty = false;
        });
        
        // beforeunload - only warn if truly dirty AND not submitting
        window.addEventListener('beforeunload', function(e) {
            if (isDirty && !isSaving && !formIsSubmitting) {
                e.preventDefault();
                e.returnValue = 'Máte neuložené změny. Opravdu chcete opustit stránku?';
                return e.returnValue;
            }
        });
    }
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        if (typeof tinymce !== 'undefined') {
            tinymce.on('Ready', function() {
                setTimeout(initAutosave, 500);
            });
        } else {
            initAutosave();
        }
    });
    
    // No CSS for indicator - removed toast notifications
    
})(jQuery);
