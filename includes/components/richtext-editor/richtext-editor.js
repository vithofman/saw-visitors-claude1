/**
 * Rich Text Editor Component - JavaScript
 * 
 * Inicializace WordPress media gallery a dark mode styling.
 * 
 * @package SAW_Visitors
 * @version 1.0.1 - Enhanced dark mode with direct iframe manipulation
 * @since 7.0.0
 */
(function($) {
    'use strict';
    
    console.log('[SAW RichText] Script loaded');
    
    /**
     * Initialize media library for all rich text editors
     */
    function initMediaLibrary() {
        let retryCount = 0;
        const maxRetries = 50;
        
        function waitForMedia() {
            if (typeof wp !== 'undefined' && typeof wp.media !== 'undefined') {
                console.log('[SAW RichText] WordPress media available');
                
                // Find all richtext editor wrappers
                $('.saw-richtext-editor-wrapper').each(function() {
                    const $wrapper = $(this);
                    const editorId = $wrapper.data('editor-id');
                    const darkMode = $wrapper.data('dark-mode') === 1;
                    
                    console.log('[SAW RichText] Processing editor:', editorId, 'Dark mode:', darkMode);
                    
                    // Ensure media buttons exist
                    ensureMediaButtons(editorId);
                    
                    // Apply dark mode if enabled
                    if (darkMode) {
                        applyDarkMode(editorId);
                    }
                });
            } else {
                retryCount++;
                if (retryCount < maxRetries) {
                    setTimeout(waitForMedia, 100);
                } else {
                    console.error('[SAW RichText] WordPress media not available after ' + maxRetries + ' retries');
                }
            }
        }
        
        waitForMedia();
    }
    
    /**
     * Ensure media buttons exist for editor
     */
    function ensureMediaButtons(editorId) {
        const $wrapper = $('#wp-' + editorId + '-wrap');
        
        if (!$wrapper.length) {
            console.warn('[SAW RichText] Editor wrap not found:', editorId);
            return;
        }
        
        let $mediaButtons = $wrapper.find('.wp-media-buttons');
        
        if (!$mediaButtons.length) {
            console.log('[SAW RichText] Creating media buttons for:', editorId);
            
            $mediaButtons = $('<div class="wp-media-buttons"></div>');
            const $button = $('<button type="button" class="button insert-media add_media" data-editor="' + editorId + '"></button>');
            $button.html('<span class="wp-media-buttons-icon"></span> Přidat média');
            
            $mediaButtons.append($button);
            
            const $editorContainer = $wrapper.find('.wp-editor-container');
            if ($editorContainer.length) {
                $editorContainer.before($mediaButtons);
            } else {
                $wrapper.prepend($mediaButtons);
            }
        }
        
        // Attach click handler
        attachMediaButtonHandler(editorId);
    }
    
    /**
     * Attach click handler to media button
     */
    function attachMediaButtonHandler(editorId) {
        const $button = $('[data-editor="' + editorId + '"]');
        
        if (!$button.length) {
            console.warn('[SAW RichText] Media button not found:', editorId);
            return;
        }
        
        // Remove existing handlers
        $button.off('click.saw-media');
        
        // Attach new handler
        $button.on('click.saw-media', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('[SAW RichText] Media button clicked:', editorId);
            
            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                console.error('[SAW RichText] WordPress media not available');
                return false;
            }
            
            // Create or get media frame
            let frame = wp.media.frames['editor-' + editorId];
            
            if (!frame) {
                console.log('[SAW RichText] Creating media frame:', editorId);
                
                frame = wp.media({
                    title: 'Vyberte nebo nahrajte média',
                    button: {
                        text: 'Vložit do editoru'
                    },
                    multiple: false,
                    library: {
                        type: 'image'
                    }
                });
                
                // When media is selected
                frame.on('select', function() {
                    const attachment = frame.state().get('selection').first().toJSON();
                    console.log('[SAW RichText] Media selected:', attachment);
                    
                    // Insert into editor
                    insertIntoEditor(editorId, attachment);
                });
                
                wp.media.frames['editor-' + editorId] = frame;
            }
            
            frame.open();
            return false;
        });
        
        console.log('[SAW RichText] Media button handler attached:', editorId);
    }
    
    /**
     * Insert attachment into editor
     */
    function insertIntoEditor(editorId, attachment) {
        let html = '';
        
        if (attachment.type === 'image') {
            html = '<img src="' + attachment.url + '" alt="' + (attachment.alt || attachment.title) + '" />';
        } else {
            html = '<a href="' + attachment.url + '">' + (attachment.title || attachment.filename) + '</a>';
        }
        
        // Try TinyMCE first
        if (typeof tinymce !== 'undefined') {
            const editor = tinymce.get(editorId);
            
            if (editor && !editor.isHidden()) {
                editor.insertContent(html);
                console.log('[SAW RichText] Inserted into TinyMCE:', editorId);
                return;
            }
        }
        
        // Fallback to textarea
        const $textarea = $('#' + editorId);
        if ($textarea.length) {
            const content = $textarea.val();
            $textarea.val(content + html);
            console.log('[SAW RichText] Inserted into textarea:', editorId);
        }
    }
    
    /**
     * Apply dark mode styling to TinyMCE iframe
     * 
     * ✅ ENHANCED: Applies styles directly to iframe body with !important
     */
    function applyDarkMode(editorId) {
        let retryCount = 0;
        const maxRetries = 100;
        
        function waitForTinyMCE() {
            if (typeof tinymce !== 'undefined') {
                const editor = tinymce.get(editorId);
                
                if (editor && editor.initialized) {
                    console.log('[SAW RichText] Applying dark mode to:', editorId);
                    
                    // Get iframe document
                    const iframeDoc = editor.getDoc();
                    
                    if (iframeDoc && iframeDoc.body) {
                        // ✅ CRITICAL: Apply inline styles directly to body
                        // This overrides any existing styles with !important
                        iframeDoc.body.style.cssText = `
                            background-color: #1a202c !important;
                            background: #1a202c !important;
                            color: #e2e8f0 !important;
                        `;
                        
                        // Add stylesheet to iframe head for other elements
                        const style = iframeDoc.createElement('style');
                        style.id = 'saw-dark-mode';
                        style.textContent = `
                            /* Force dark background on all elements */
                            body, body.mce-content-body {
                                background-color: #1a202c !important;
                                background: #1a202c !important;
                                color: #e2e8f0 !important;
                            }
                            
                            /* Headings */
                            h1, h2, h3, h4, h5, h6 {
                                color: #f7fafc !important;
                            }
                            
                            /* Paragraphs */
                            p {
                                color: #e2e8f0 !important;
                            }
                            
                            /* Links */
                            a {
                                color: #667eea !important;
                            }
                            
                            /* Lists */
                            ul, ol, li {
                                color: #e2e8f0 !important;
                            }
                            
                            /* Blockquotes */
                            blockquote {
                                border-left: 4px solid #667eea !important;
                                padding-left: 1em !important;
                                color: #cbd5e0 !important;
                                font-style: italic !important;
                            }
                            
                            /* Strong/Bold */
                            strong, b {
                                color: #f7fafc !important;
                            }
                            
                            /* Emphasis/Italic */
                            em, i {
                                color: #e2e8f0 !important;
                            }
                            
                            /* Code */
                            code {
                                background: #2d3748 !important;
                                color: #10b981 !important;
                                padding: 2px 6px !important;
                                border-radius: 4px !important;
                            }
                            
                            /* Pre */
                            pre {
                                background: #2d3748 !important;
                                color: #e2e8f0 !important;
                                padding: 12px !important;
                                border-radius: 6px !important;
                            }
                            
                            /* Images */
                            img {
                                max-width: 100% !important;
                                height: auto !important;
                            }
                        `;
                        
                        // Remove existing dark mode styles if any
                        const existingStyle = iframeDoc.getElementById('saw-dark-mode');
                        if (existingStyle) {
                            existingStyle.remove();
                        }
                        
                        iframeDoc.head.appendChild(style);
                        
                        console.log('[SAW RichText] Dark mode styles applied to:', editorId);
                    } else {
                        console.warn('[SAW RichText] iframe document or body not found:', editorId);
                    }
                } else {
                    retryCount++;
                    if (retryCount < maxRetries) {
                        setTimeout(waitForTinyMCE, 100);
                    } else {
                        console.error('[SAW RichText] TinyMCE not initialized after ' + maxRetries + ' retries:', editorId);
                    }
                }
            } else {
                retryCount++;
                if (retryCount < maxRetries) {
                    setTimeout(waitForTinyMCE, 100);
                } else {
                    console.error('[SAW RichText] TinyMCE not available after ' + maxRetries + ' retries');
                }
            }
        }
        
        waitForTinyMCE();
    }
    
    // Initialize on document ready
    $(document).ready(function() {
        console.log('[SAW RichText] DOM ready, initializing...');
        initMediaLibrary();
    });
    
})(jQuery);