/**
 * Content Module Scripts
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

(function ($) {
    'use strict';

    /**
     * Initialize content module functionality
     * Can be called on document ready or after AJAX page load
     */
    function initContentModule() {
        console.log('[Content Module] initContentModule() called');
        
        // Auto-hide success notification after 4 seconds
        var $notification = $('.saw-success-notification');
        if ($notification.length) {
            setTimeout(function () {
                $notification.addClass('hiding');
                setTimeout(function () {
                    $notification.remove();
                }, 400);
            }, 4000);
        }

        // CRITICAL: Initialize TinyMCE editors after AJAX load
        // Wait for all assets to load, then initialize with longer delay
        setTimeout(function() {
            initTinyMCEEditors();
            // Initialize media buttons right after TinyMCE (they're part of editor initialization)
            setTimeout(function() {
                initMediaButtons();
            }, 500);
        }, 1500);

        // Přepínání jazykových záložek - use event delegation for AJAX-loaded content
        const $tabButtons = $('.saw-tab-btn');
        console.log('[Content Module] Found', $tabButtons.length, 'tab buttons');
        $(document).off('click', '.saw-tab-btn').on('click', '.saw-tab-btn', function () {
            console.log('[Content Module] Tab button clicked:', $(this).data('tab'));
            $('.saw-tab-btn').removeClass('active');
            $(this).addClass('active');

            const tab = $(this).data('tab');
            $('.saw-tab-content').hide();
            $('[data-tab-content="' + tab + '"]').show();

            // Po přepnutí záložky reinicializuj media buttons
            setTimeout(function () {
                if (typeof wp !== 'undefined' && wp.media) {
                    $('.wp-media-buttons .insert-media').each(function () {
                        var $button = $(this);
                        var editorId = $button.data('editor');

                        if (!editorId) {
                            var $editorWrap = $button.closest('.wp-editor-wrap');
                            if ($editorWrap.length) {
                                editorId = $editorWrap.attr('id').replace('wp-', '').replace('-wrap', '');
                                $button.attr('data-editor', editorId);
                            }
                        }
                    });
                }
            }, 100);
        });

        // Rozbalovací sekce - use event delegation for AJAX-loaded content
        const $sectionHeaders = $('.saw-section-header');
        console.log('[Content Module] Found', $sectionHeaders.length, 'section headers');
        $(document).off('click', '.saw-section-header').on('click', '.saw-section-header', function (e) {
            console.log('[Content Module] Section header clicked');
            e.preventDefault();
            $(this).closest('.saw-collapsible-section').toggleClass('open');
        });

        // Rozbalovací sekce oddělení - already using event delegation
        const $deptHeaders = $('.saw-department-header');
        console.log('[Content Module] Found', $deptHeaders.length, 'department headers');
        $(document).off('click', '.saw-department-header').on('click', '.saw-department-header', function (e) {
            console.log('[Content Module] Department header clicked');
            e.preventDefault();
            $(this).closest('.saw-department-subsection').toggleClass('open');
        });

        // Přidat další dokument
        $('.saw-add-document').on('click', function () {
            const targetId = $(this).data('target');
            const deptId = $(this).data('dept-id');
            const docType = $(this).data('doc-type');
            const $list = $('#' + targetId);

            const $newItem = $('<div class="saw-document-item"></div>');

            // Create select for document type
            let selectName = '';
            if (docType === 'risks') {
                selectName = 'risks_doc_type[]';
            } else if (docType === 'additional') {
                selectName = 'additional_doc_type[]';
            } else if (docType === 'department' && deptId) {
                selectName = 'department_doc_type[' + deptId + '][]';
            }

            const $selectWrapper = $('<div class="saw-doc-type-select"></div>');
            const $select = $('<select name="' + selectName + '" class="saw-select"></select>');
            $select.append('<option value="">-- Vyberte typ dokumentu --</option>');

            if (typeof sawDocumentTypes !== 'undefined') {
                sawDocumentTypes.forEach(function (type) {
                    $select.append('<option value="' + type.id + '">' + type.name + '</option>');
                });
            }

            $selectWrapper.append($select);

            // File input
            let inputName = 'additional_documents[]';
            if (targetId.includes('risks-docs')) {
                inputName = 'risks_documents[]';
            } else if (targetId.includes('dept-docs') && deptId) {
                inputName = 'department_documents[' + deptId + '][]';
            }

            const $fileInput = $('<input type="file" name="' + inputName + '" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.pages,.numbers,.key,.txt,.rtf" class="saw-file-input" data-requires-type="true">');

            const $removeBtn = $('<button type="button" class="saw-remove-document">🗑️</button>');

            $removeBtn.on('click', function () {
                $newItem.remove();
            });

            $newItem.append($selectWrapper);
            $newItem.append($fileInput);
            $newItem.append($removeBtn);

            $list.append($newItem);
        });

        // Form validation and AJAX submit - typ dokumentu je povinný pouze když je vybrán soubor
        $(document).off('submit.saw-content-form', '.saw-content-form').on('submit.saw-content-form', '.saw-content-form', function (e) {
            e.preventDefault(); // Always prevent default to use AJAX
            
            const $form = $(this);
            let hasError = false;

            $form.find('.saw-document-item').each(function () {
                const $fileInput = $(this).find('input[type="file"]');
                const $select = $(this).find('select');

                // Pokud je vybrán soubor
                if ($fileInput[0] && $fileInput[0].files && $fileInput[0].files.length > 0) {
                    // Musí být vybrán typ
                    if (!$select.val()) {
                        hasError = true;
                        $select.css('border', '2px solid #dc2626');

                        // Scroll na první chybu
                        if (!$('.saw-validation-error').length) {
                            $select.before('<div class="saw-validation-error">⚠️ Vyberte typ dokumentu pro nahraný soubor</div>');
                            $('html, body').animate({
                                scrollTop: $select.offset().top - 100
                            }, 500);
                        }
                    } else {
                        $select.css('border', '');
                        $(this).find('.saw-validation-error').remove();
                    }
                }
            });

            if (hasError) {
                return false;
            }

            // CRITICAL: Save TinyMCE content before submitting
            if (typeof tinymce !== 'undefined') {
                tinymce.triggerSave();
            }

            // AJAX submit
            const formData = new FormData($form[0]);
            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = $submitBtn.html();
            
            $submitBtn.prop('disabled', true).html('⏳ Ukládám...');

            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        const $successMsg = $('<div class="saw-success-notification" style="position: fixed; top: 20px; right: 20px; background: #10b981; color: white; padding: 1rem 1.5rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 100000;">✅ Obsah byl úspěšně uložen!</div>');
                        $('body').append($successMsg);
                        
                        // Auto-hide after 4 seconds
                        setTimeout(function() {
                            $successMsg.addClass('hiding');
                            setTimeout(function() {
                                $successMsg.remove();
                            }, 400);
                        }, 4000);
                        
                        // Optionally reload page content via AJAX to get fresh data
                        // But don't do full page reload to preserve user's work
                        console.log('[Content Module] Form saved successfully');
                    } else {
                        alert('Chyba při ukládání: ' + (response.data || 'Neznámá chyba'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[Content Module] Form submit error:', error);
                    alert('Chyba při ukládání: ' + error);
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).html(originalText);
                }
            });

            return false;
        });

        // Remove error on change
        $(document).on('change', '.saw-document-item select', function () {
            $(this).css('border', '');
            $(this).siblings('.saw-validation-error').remove();
        });

        // Odstranit dokument (pro dynamicky přidané) - already using event delegation
        // No change needed
    }

    // Initialize on document ready
    $(document).ready(function () {
        initContentModule();
    });

    /**
     * Initialize TinyMCE editors
     * Waits for wp.editor to be available before initializing
     * CRITICAL: Removes existing editors first to ensure clean initialization
     */
    function initTinyMCEEditors() {
        let retryCount = 0;
        const maxRetries = 50; // Max 5 seconds
        
        function waitForEditor() {
            if (typeof wp !== 'undefined' && typeof wp.editor !== 'undefined' && typeof wp.editor.initialize === 'function') {
                console.log('[Content Module] WordPress editor available, initializing TinyMCE editors...');
                
                // CRITICAL: Remove all existing TinyMCE editors first
                if (typeof tinymce !== 'undefined') {
                    $('textarea.wp-editor-area').each(function() {
                        const editorId = $(this).attr('id');
                        if (editorId && tinymce.get(editorId)) {
                            console.log('[Content Module] Removing existing editor:', editorId);
                            wp.editor.remove(editorId);
                        }
                    });
                }
                
                // Wait a bit for cleanup
                setTimeout(function() {
                    // Find all textareas that should be TinyMCE editors
                    $('textarea.wp-editor-area').each(function() {
                        const $textarea = $(this);
                        const editorId = $textarea.attr('id');
                        
                        if (!editorId) {
                            console.warn('[Content Module] Textarea without ID found');
                            return;
                        }
                        
                        // Initialize editor with WordPress API
                        try {
                            // Get textarea name from the textarea element
                            const textareaName = $textarea.attr('name') || '';
                            
                            // Initialize with same settings as PHP wp_editor()
                            wp.editor.initialize(editorId, {
                                tinymce: {
                                    toolbar1: 'formatselect,bold,italic,underline,strikethrough,forecolor,backcolor,bullist,numlist,alignleft,aligncenter,alignright,link,unlink',
                                    toolbar2: 'undo,redo,removeformat,code,hr,blockquote,subscript,superscript,charmap,indent,outdent,pastetext,searchreplace,fullscreen',
                                    block_formats: 'Odstavec=p;Nadpis 1=h1;Nadpis 2=h2;Nadpis 3=h3;Nadpis 4=h4;Citace=blockquote',
                                    setup: function(editor) {
                                        // Ensure editor is fully ready
                                        editor.on('init', function() {
                                            console.log('[Content Module] TinyMCE editor fully initialized:', editorId);
                                        });
                                    }
                                },
                                media_buttons: true,
                                quicktags: false,
                                textarea_name: textareaName
                            });
                            console.log('[Content Module] Initialized TinyMCE editor:', editorId);
                        } catch (e) {
                            console.error('[Content Module] Error initializing TinyMCE:', editorId, e);
                        }
                    });
                }, 100);
            } else {
                retryCount++;
                if (retryCount < maxRetries) {
                    setTimeout(waitForEditor, 100);
                } else {
                    console.warn('[Content Module] WordPress editor not available after ' + maxRetries + ' retries');
                }
            }
        }
        
        waitForEditor();
    }

    /**
     * Initialize WordPress Media Library buttons
     * CRITICAL: WordPress handles this automatically via wp.editor.initialize with media_buttons: true
     * We just need to ensure the buttons are visible and functional
     */
    function initMediaButtons() {
        let retryCount = 0;
        const maxRetries = 50; // Max 5 seconds
        
        function waitForMedia() {
            if (typeof wp !== 'undefined' && typeof wp.media !== 'undefined' && typeof wp.editor !== 'undefined') {
                console.log('[Content Module] WordPress media library available, checking media buttons...');
                
                // WordPress automatically handles media buttons when wp.editor.initialize is called with media_buttons: true
                // Just verify they exist and are visible
                $('.wp-media-buttons .insert-media').each(function () {
                    const $button = $(this);
                    const $editorWrap = $button.closest('.wp-editor-wrap');
                    
                    if ($editorWrap.length) {
                        const editorId = $editorWrap.attr('id').replace('wp-', '').replace('-wrap', '');
                        console.log('[Content Module] Media button found for editor:', editorId);
                        
                        // Ensure button is visible
                        if ($button.is(':hidden')) {
                            $button.show();
                            console.log('[Content Module] Media button was hidden, showing it');
                        }
                    }
                });
            } else {
                retryCount++;
                if (retryCount < maxRetries) {
                    setTimeout(waitForMedia, 100);
                } else {
                    console.warn('[Content Module] WordPress media library not available after ' + maxRetries + ' retries');
                }
            }
        }
        
        waitForMedia();
    }

    // Track if we've already initialized to prevent duplicate calls
    let contentModuleInitialized = false;
    
    // Re-initialize after AJAX page load
    $(document).on('saw:page-loaded saw:module-reinit', function (e, data) {
        console.log('[Content Module] Event received:', e.type, 'Data:', data);
        // Check if we're on content page
        const isContentPage = $('#saw-app-content').find('.saw-content-form').length > 0;
        console.log('[Content Module] Is content page:', isContentPage);
        
        // Only reinitialize if content module is active
        if (data && typeof data === 'object' && (data.active_menu === 'content' || isContentPage)) {
            // Reset flag on new page load
            if (e.type === 'saw:page-loaded') {
                contentModuleInitialized = false;
            }
            if (!contentModuleInitialized) {
                console.log('[Content Module] Re-initializing after AJAX load (active_menu:', data.active_menu, ')');
                initContentModule();
                contentModuleInitialized = true;
            } else {
                console.log('[Content Module] Already initialized, skipping');
            }
        } else if (isContentPage) {
            // If no data provided but we're on content page, initialize anyway
            if (!contentModuleInitialized) {
                console.log('[Content Module] Re-initializing after AJAX load (detected by DOM)');
                initContentModule();
                contentModuleInitialized = true;
            } else {
                console.log('[Content Module] Already initialized, skipping');
            }
        } else {
            console.log('[Content Module] Skipping - not content page');
        }
    });

})(jQuery);
