/**
 * Invitation Autosave
 * 
 * Automatically saves invitation data (risks text + visitors) every 30 seconds
 * Works for all invitation steps (risks, visitors)
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 */

(function($) {
    'use strict';
    
    let autosaveTimer;
    let isDirty = false;
    let isSaving = false;
    
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
     * 
     * @return {string} Risks text content
     */
    function getRisksText() {
        // Try TinyMCE first
        if (typeof tinymce !== 'undefined') {
            const editor = tinymce.get('risks_text');
            if (editor) {
                return editor.getContent();
            }
        }
        
        // Fallback to textarea
        const textarea = $('#risks_text');
        if (textarea.length) {
            return textarea.val();
        }
        
        return '';
    }
    
    /**
     * Get visitors data from form
     * 
     * Collects data from:
     * - Existing visitors (checkboxes)
     * - New visitors (form inputs)
     * 
     * @return {array} Array of visitor objects
     */
    function getVisitorsData() {
        const visitors = [];
        
        // Get existing visitors (checkboxes)
        $('input[name^="visitor_"]:checked, input[name^="existing_visitor_ids"]:checked').each(function() {
            const visitorId = $(this).val();
            const $row = $(this).closest('tr, .visitor-row, [data-visitor-id]');
            const trainingSkip = $row.find('input[name^="training_skip"], input[name*="training_skip"]').is(':checked');
            
            visitors.push({
                id: parseInt(visitorId, 10),
                training_skip: trainingSkip ? 1 : 0
            });
        });
        
        // Also check for hidden inputs with existing_visitor_ids array
        const existingIds = $('input[name="existing_visitor_ids[]"]:checked').map(function() {
            return parseInt($(this).val(), 10);
        }).get();
        
        existingIds.forEach(function(id) {
            // Check if already added
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
        
        // Get new visitors (form inputs)
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
        
        // Also check for new_visitors array inputs
        $('input[name^="new_visitors["]').each(function() {
            const name = $(this).attr('name');
            const match = name.match(/new_visitors\[(\d+)\]\[(\w+)\]/);
            
            if (match) {
                const index = parseInt(match[1], 10);
                const field = match[2];
                const value = $(this).val();
                
                // Find or create visitor object
                let visitor = visitors.find(function(v) {
                    return v.id === null && v._index === index;
                });
                
                if (!visitor) {
                    visitor = {
                        id: null,
                        _index: index,
                        training_skip: 0
                    };
                    visitors.push(visitor);
                }
                
                if (field === 'first_name') {
                    visitor.first_name = value;
                } else if (field === 'last_name') {
                    visitor.last_name = value;
                } else if (field === 'position') {
                    visitor.position = value;
                } else if (field === 'email') {
                    visitor.email = value;
                } else if (field === 'phone') {
                    visitor.phone = value;
                } else if (field === 'training_skip') {
                    visitor.training_skip = $(this).is(':checked') ? 1 : 0;
                }
            }
        });
        
        // Clean up temporary _index property
        visitors.forEach(function(v) {
            delete v._index;
        });
        
        // Filter out incomplete new visitors
        return visitors.filter(function(v) {
            if (v.id !== null) {
                return true; // Existing visitor
            }
            // New visitor must have at least first_name and last_name
            return v.first_name && v.last_name;
        });
    }
    
    /**
     * Detect current invitation step from URL or sawInvitation
     * 
     * @return {string} Current step name
     */
    function detectCurrentStep() {
        // Try from sawInvitation first
        if (currentStep) {
            return currentStep;
        }
        
        // Try from URL query string
        const urlParams = new URLSearchParams(window.location.search);
        const step = urlParams.get('step');
        if (step) {
            return step;
        }
        
        // Try to detect from page content
        if ($('#risks_text').length > 0 || $('.saw-risks-step').length > 0) {
            return 'risks';
        }
        
        if ($('input[name^="visitor_"]').length > 0 || $('.saw-visitors-step').length > 0) {
            return 'visitors';
        }
        
        return '';
    }
    
    /**
     * Perform autosave
     * 
     * Detects current step and saves appropriate data:
     * - risks step: saves risks_text
     * - visitors step: saves visitors array
     */
    function doAutosave() {
        if (!isDirty || isSaving) {
            return;
        }
        
        isSaving = true;
        const step = detectCurrentStep();
        const autosaveData = {};
        
        // Save TinyMCE content if available
        if (typeof tinymce !== 'undefined') {
            tinymce.triggerSave();
        }
        
        // Collect data based on current step
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
            // Unknown step or no data - skip autosave
            console.log('[Invitation Autosave] Unknown step or no data to save:', step);
            isSaving = false;
            return;
        }
        
        // Don't save if no data collected
        if (Object.keys(autosaveData).length === 0) {
            console.log('[Invitation Autosave] No data to save');
            isSaving = false;
            return;
        }
        
        // Prepare request body
        const bodyParams = new URLSearchParams({
            action: 'saw_invitation_autosave',
            nonce: nonce,
            token: token,
            data: JSON.stringify(autosaveData)
        });
        
        // Use fetch() API for modern approach
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
                const time = new Date().toLocaleTimeString();
                showSaveIndicator('✓ Uloženo ' + time);
                console.log('[Invitation Autosave] ✅ Saved successfully for step:', step);
            } else {
                console.error('[Invitation Autosave] ❌ Server error:', data.data || data);
            }
        })
        .catch(error => {
            console.error('[Invitation Autosave] ❌ AJAX error:', error);
        })
        .finally(() => {
            isSaving = false;
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
        const step = detectCurrentStep();
        console.log('[Invitation Autosave] Initializing for step:', step, 'token:', token);
        
        // Setup listeners based on current step
        if (step === 'risks') {
            // Mark as dirty on risks text changes
            $(document).on('change keyup', '#risks_text, .wp-editor-area', function() {
                isDirty = true;
                clearTimeout(autosaveTimer);
                autosaveTimer = setTimeout(doAutosave, 30000); // 30s
            });
            
            // Also listen to TinyMCE changes
            if (typeof tinymce !== 'undefined') {
                // If editor already exists
                const editor = tinymce.get('risks_text');
                if (editor) {
                    editor.on('keyup change', function() {
                        isDirty = true;
                        clearTimeout(autosaveTimer);
                        autosaveTimer = setTimeout(doAutosave, 30000);
                    });
                }
                
                // Listen for new editors
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
            // Mark as dirty on visitor form changes
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
                    autosaveTimer = setTimeout(doAutosave, 30000); // 30s
                }
            );
        }
        
        // Manual save before leaving
        window.addEventListener('beforeunload', function(e) {
            if (isDirty && !isSaving) {
                e.preventDefault();
                e.returnValue = 'Máte neuložené změny. Opravdu chcete opustit stránku?';
                return e.returnValue;
            }
        });
        
        console.log('[Invitation Autosave] ✅ Initialized for step:', step);
    }
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        // Wait for TinyMCE to be ready
        if (typeof tinymce !== 'undefined') {
            tinymce.on('Ready', function() {
                setTimeout(initAutosave, 500);
            });
        } else {
            initAutosave();
        }
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

