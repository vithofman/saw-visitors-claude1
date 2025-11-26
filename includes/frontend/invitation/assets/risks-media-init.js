/**
 * Invitation Risks - Media Library Initialization
 * Podle saw-content.js
 */
(function($) {
    'use strict';
    
    console.log('[Invitation Risks] Initializing media library...');
    
    let retryCount = 0;
    const maxRetries = 50;
    
    function waitForMedia() {
        if (typeof wp !== 'undefined' && typeof wp.media !== 'undefined') {
            console.log('[Invitation Risks] WordPress media available, attaching handlers...');
            
            // Find all media buttons
            $('.wp-media-buttons .insert-media, .wp-media-buttons .add_media').each(function() {
                const $button = $(this);
                const editorId = $button.data('editor') || $button.attr('data-editor');
                
                if (!editorId) {
                    console.warn('[Invitation Risks] Button missing editor ID:', $button);
                    return;
                }
                
                console.log('[Invitation Risks] Attaching handler to button for editor:', editorId);
                
                // Remove any existing handlers
                $button.off('click.saw-media');
                
                // Attach click handler
                $button.on('click.saw-media', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    console.log('[Invitation Risks] Media button clicked for:', editorId);
                    
                    if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                        console.error('[Invitation Risks] WordPress media not available');
                        return false;
                    }
                    
                    // Create or get media frame
                    let frame = wp.media.frames['editor-' + editorId];
                    
                    if (!frame) {
                        console.log('[Invitation Risks] Creating new media frame for:', editorId);
                        
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
                            console.log('[Invitation Risks] Media selected:', attachment);
                            
                            // Get TinyMCE editor
                            if (typeof tinymce !== 'undefined') {
                                const editor = tinymce.get(editorId);
                                
                                if (editor && !editor.isHidden()) {
                                    // Visual mode - insert into TinyMCE
                                    let html = '';
                                    if (attachment.type === 'image') {
                                        html = '<img src="' + attachment.url + '" alt="' + (attachment.alt || attachment.title) + '" />';
                                    } else {
                                        html = '<a href="' + attachment.url + '">' + (attachment.title || attachment.filename) + '</a>';
                                    }
                                    editor.insertContent(html);
                                    console.log('[Invitation Risks] Inserted into TinyMCE:', editorId);
                                } else {
                                    // Text mode - insert into textarea
                                    const $textarea = $('#' + editorId);
                                    if ($textarea.length) {
                                        let html = '';
                                        if (attachment.type === 'image') {
                                            html = '<img src="' + attachment.url + '" alt="' + (attachment.alt || attachment.title) + '" />';
                                        } else {
                                            html = '<a href="' + attachment.url + '">' + (attachment.title || attachment.filename) + '</a>';
                                        }
                                        
                                        const content = $textarea.val();
                                        $textarea.val(content + html);
                                        console.log('[Invitation Risks] Inserted into textarea:', editorId);
                                    }
                                }
                            } else {
                                // TinyMCE not available - insert into textarea
                                const $textarea = $('#' + editorId);
                                if ($textarea.length) {
                                    let html = '';
                                    if (attachment.type === 'image') {
                                        html = '<img src="' + attachment.url + '" alt="' + (attachment.alt || attachment.title) + '" />';
                                    } else {
                                        html = '<a href="' + attachment.url + '">' + (attachment.title || attachment.filename) + '</a>';
                                    }
                                    
                                    const content = $textarea.val();
                                    $textarea.val(content + html);
                                    console.log('[Invitation Risks] Inserted into textarea (no TinyMCE):', editorId);
                                }
                            }
                        });
                        
                        // Store frame
                        wp.media.frames['editor-' + editorId] = frame;
                    }
                    
                    // Open media library
                    console.log('[Invitation Risks] Opening media frame for:', editorId);
                    frame.open();
                    
                    return false;
                });
                
                console.log('[Invitation Risks] Handler attached successfully for:', editorId);
            });
            
        } else {
            retryCount++;
            if (retryCount < maxRetries) {
                setTimeout(waitForMedia, 100);
            } else {
                console.error('[Invitation Risks] WordPress media not available after ' + maxRetries + ' retries');
            }
        }
    }
    
    // Start initialization
    $(document).ready(function() {
        console.log('[Invitation Risks] DOM ready, waiting for media...');
        waitForMedia();
    });
    
})(jQuery);