/**
 * Invitation Autosave
 * 
 * Automatically saves risks text content every 30 seconds
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

(function($) {
    'use strict';
    
    let autosaveTimer;
    let isDirty = false;
    let isSaving = false;
    
    // Check if sawInvitation is available
    if (typeof sawInvitation === 'undefined' || !sawInvitation.autosaveNonce) {
        console.log('[Invitation Autosave] Not initialized - missing sawInvitation object');
        return;
    }
    
    const token = sawInvitation.token;
    const ajaxurl = sawInvitation.ajaxurl;
    const nonce = sawInvitation.autosaveNonce;
    
    /**
     * Get risks text from editor
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
     * Perform autosave
     */
    function doAutosave() {
        if (!isDirty || isSaving) {
            return;
        }
        
        isSaving = true;
        const risksText = getRisksText();
        
        // Save TinyMCE content
        if (typeof tinymce !== 'undefined') {
            tinymce.triggerSave();
        }
        
        fetch(ajaxurl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'saw_invitation_autosave',
                nonce: nonce,
                token: token,
                data: JSON.stringify({
                    risks_text: risksText
                })
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                isDirty = false;
                showSaveIndicator('✓ Uloženo ' + new Date().toLocaleTimeString());
            } else {
                console.error('[Invitation Autosave] Error:', data.data);
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
        $(document).on('change keyup', '#risks_text, .wp-editor-area', function() {
            isDirty = true;
            clearTimeout(autosaveTimer);
            autosaveTimer = setTimeout(doAutosave, 30000); // 30s
        });
        
        // Also listen to TinyMCE changes
        if (typeof tinymce !== 'undefined') {
            tinymce.on('AddEditor', function(e) {
                e.editor.on('keyup change', function() {
                    isDirty = true;
                    clearTimeout(autosaveTimer);
                    autosaveTimer = setTimeout(doAutosave, 30000);
                });
            });
        }
        
        // Manual save before leaving
        window.addEventListener('beforeunload', function(e) {
            if (isDirty && !isSaving) {
                e.preventDefault();
                e.returnValue = 'Máte neuložené změny. Opravdu chcete opustit stránku?';
                return e.returnValue;
            }
        });
        
        console.log('[Invitation Autosave] Initialized for token:', token);
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

