/**
 * Rich Text Editor Component - JavaScript
 * 
 * Inicializace media buttons a dark mode pro WordPress TinyMCE editor.
 * Vychází z funkčního kódu z content modulu.
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 * @since 7.0.0
 */

(function($) {
    'use strict';
    
    /**
     * Initialize all Rich Text Editors on page
     */
    function initRichTextEditors() {
        console.log('[SAW RichText] Initializing editors...');
        
        // Find all editor wrappers
        $('.saw-richtext-editor-wrapper').each(function() {
            const $wrapper = $(this);
            const editorId = $wrapper.data('editor-id');
            const darkMode = $wrapper.data('dark-mode') === 1;
            
            if (!editorId) {
                console.warn('[SAW RichText] Wrapper missing editor-id');
                return;
            }
            
            console.log('[SAW RichText] Found editor:', editorId, 'dark mode:', darkMode);
            
            // Initialize media buttons
            initMediaButtons(editorId);
            
            // Apply dark mode if enabled
            if (darkMode) {
                applyDarkMode(editorId);
            }
        });
    }
    
    /**
     * Initialize WordPress Media Library buttons
     * 
     * Ensures media buttons are visible and functional.
     * WordPress should handle this automatically, but we double-check.
     */
    function initMediaButtons(editorId) {
        let retryCount = 0;
        const maxRetries = 50; // Max 5 seconds
        
        function waitForMedia() {
            if (typeof wp !== 'undefined' && typeof wp.media !== 'undefined') {
                console.log('[SAW RichText] WordPress media available for:', editorId);
                
                const $editorWrap = $('#wp-' + editorId + '-wrap');
                if ($editorWrap.length === 0) {
                    console.warn('[SAW RichText] Editor wrap not found:', editorId);
                    return;
                }
                
                // Check for media buttons
                let $mediaButtons = $editorWrap.find('.wp-media-buttons');
                
                if ($mediaButtons.length === 0) {
                    // Media buttons missing - add them manually
                    console.log('[SAW RichText] Media buttons missing for:', editorId, '- adding manually');
                    
                    const $mediaButtonsDiv = $('<div class="wp-media-buttons"></div>');
                    $mediaButtonsDiv.html(
                        '<button type="button" class="button insert-media add_media" data-editor="' + editorId + '">' +
                        '<span class="wp-media-buttons-icon"></span> Přidat média' +
                        '</button>'
                    );
                    
                    const $editorContainer = $editorWrap.find('.wp-editor-container');
                    if ($editorContainer.length) {
                        $editorContainer.before($mediaButtonsDiv);
                    } else {
                        $editorWrap.prepend($mediaButtonsDiv);
                    }
                    
                    $mediaButtons = $mediaButtonsDiv;
                    console.log('[SAW RichText] Manually added media buttons for:', editorId);
                } else {
                    // Ensure media buttons are visible
                    $mediaButtons.show().css({
                        'display': 'block',
                        'visibility': 'visible',
                        'opacity': '1'
                    });
                    console.log('[SAW RichText] Media buttons found and visible for:', editorId);
                }
                
                // Ensure click handler is attached
                ensureMediaButtonHandler(editorId);
                
            } else {
                retryCount++;
                if (retryCount < maxRetries) {
                    setTimeout(waitForMedia, 100);
                } else {
                    console.warn('[SAW RichText] WordPress media not available after retries for:', editorId);
                }
            }
        }
        
        waitForMedia();
    }
    
    /**
     * Ensure media button click handler is attached
     */
    function ensureMediaButtonHandler(editorId) {
        const $button = $('#wp-' + editorId + '-wrap').find('.insert-media');
        
        if ($button.length === 0) {
            return;
        }
        
        // Check if handler already attached
        if ($button.data('media-handler-attached')) {
            return;
        }
        
        // WordPress should handle this automatically, but we ensure it
        $button.on('click', function(e) {
            e.preventDefault();
            
            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                console.error('[SAW RichText] WordPress media not available');
                return;
            }
            
            // Get or create media frame
            const editor = editorId;
            let frame = wp.media.frames[editor];
            
            if (!frame) {
                frame = wp.media({
                    title: 'Vyberte nebo nahrajte média',
                    button: {
                        text: 'Vložit do editoru'
                    },
                    multiple: false
                });
                
                // When media is selected
                frame.on('select', function() {
                    const attachment = frame.state().get('selection').first().toJSON();
                    
                    // Insert into TinyMCE
                    if (typeof tinymce !== 'undefined') {
                        const ed = tinymce.get(editor);
                        if (ed) {
                            let html = '';
                            
                            if (attachment.type === 'image') {
                                html = '<img src="' + attachment.url + '" alt="' + (attachment.alt || attachment.title || '') + '" />';
                            } else {
                                html = '<a href="' + attachment.url + '">' + (attachment.title || attachment.filename) + '</a>';
                            }
                            
                            ed.insertContent(html);
                        }
                    }
                });
                
                wp.media.frames[editor] = frame;
            }
            
            frame.open();
        });
        
        $button.data('media-handler-attached', true);
        console.log('[SAW RichText] Media button handler attached for:', editorId);
    }
    
    /**
     * Apply dark mode styling to TinyMCE editor
     * 
     * This adds dark mode CSS to the editor iframe content.
     */
    function applyDarkMode(editorId) {
        let retryCount = 0;
        const maxRetries = 50;
        
        function waitForTinyMCE() {
            if (typeof tinymce !== 'undefined') {
                const editor = tinymce.get(editorId);
                
                if (editor && editor.initialized) {
                    console.log('[SAW RichText] Applying dark mode to:', editorId);
                    
                    // Get iframe document
                    const iframeDoc = editor.getDoc();
                    
                    if (iframeDoc) {
                        // Add dark mode styles to iframe head
                        const style = iframeDoc.createElement('style');
                        style.textContent = `
                            body.mce-content-body {
                                background: #1a202c !important;
                                color: #e2e8f0 !important;
                            }
                            body.mce-content-body h1,
                            body.mce-content-body h2,
                            body.mce-content-body h3,
                            body.mce-content-body h4,
                            body.mce-content-body h5,
                            body.mce-content-body h6 {
                                color: #fff !important;
                            }
                            body.mce-content-body p {
                                color: #e2e8f0 !important;
                            }
                            body.mce-content-body blockquote {
                                border-left: 4px solid #667eea !important;
                                padding-left: 1em !important;
                                color: #a0aec0 !important;
                                font-style: italic !important;
                            }
                            body.mce-content-body a {
                                color: #667eea !important;
                            }
                            body.mce-content-body ul,
                            body.mce-content-body ol {
                                color: #e2e8f0 !important;
                            }
                        `;
                        iframeDoc.head.appendChild(style);
                        console.log('[SAW RichText] Dark mode styles applied to:', editorId);
                    }
                } else {
                    retryCount++;
                    if (retryCount < maxRetries) {
                        setTimeout(waitForTinyMCE, 100);
                    } else {
                        console.warn('[SAW RichText] TinyMCE not ready after retries for:', editorId);
                    }
                }
            } else {
                retryCount++;
                if (retryCount < maxRetries) {
                    setTimeout(waitForTinyMCE, 100);
                } else {
                    console.warn('[SAW RichText] TinyMCE not available after retries for:', editorId);
                }
            }
        }
        
        waitForTinyMCE();
    }
    
    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        console.log('[SAW RichText] Document ready');
        
        // Wait a bit for WordPress to fully load
        setTimeout(function() {
            initRichTextEditors();
        }, 100);
    });
    
    /**
     * Re-initialize on AJAX page loads (for SPA-style navigation)
     */
    $(document).on('saw:content-loaded', function() {
        console.log('[SAW RichText] Content loaded event');
        setTimeout(function() {
            initRichTextEditors();
        }, 100);
    });
    
})(jQuery);
