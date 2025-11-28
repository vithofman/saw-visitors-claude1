/**
 * Content Module Scripts
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

(function ($) {
    'use strict';

    // Prevent double loading of the script
    if (window.sawContentScriptLoaded) {
        console.log('[Content Module] Script already loaded, skipping execution');
        return;
    }
    window.sawContentScriptLoaded = true;
    console.log('[Content Module] Script loaded for the first time');

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

        // CRITICAL: Content page uses full page reload, so editors are already initialized by WordPress
        // Only initialize if we're on AJAX-loaded content (shouldn't happen for content page)
        // For content page, WordPress wp_editor() already initializes everything correctly
        const isContentPage = window.location.pathname.indexOf('/admin/content') !== -1 || 
                              window.location.pathname.indexOf('/content') !== -1;
        
        if (!isContentPage) {
            // Non-content page - initialize editors after AJAX load
            setTimeout(function () {
                initTinyMCEEditors();
                setTimeout(function () {
                    initMediaButtons();
                }, 500);
            }, 1500);
        } else {
            // Content page - just ensure media buttons are visible
            // WordPress wp_editor() already initialized everything on page load
            console.log('[Content Module] Content page detected - skipping editor re-initialization');
            setTimeout(function () {
                initMediaButtons();
            }, 1000);
        }

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
            const $section = $(this).closest('.saw-collapsible-section');
            const wasOpen = $section.hasClass('open');
            $section.toggleClass('open');
            const isNowOpen = $section.hasClass('open');
            
            // CRITICAL: If section with WYSIWYG editor is opened, DON'T re-initialize editors
            // WordPress wp_editor() already initializes them correctly on page load
            // Re-initialization causes media buttons to disappear
            if (isNowOpen && !wasOpen) {
                const $editorWraps = $section.find('.wp-editor-wrap');
                if ($editorWraps.length > 0) {
                    console.log('[Content Module] Section with editor opened - preserving existing editors');
                    // Just ensure media buttons are visible, don't re-initialize
                    setTimeout(function() {
                        $editorWraps.each(function() {
                            const $editorWrap = $(this);
                            const editorId = $editorWrap.attr('id').replace('wp-', '').replace('-wrap', '');
                            let $mediaButtons = $editorWrap.find('.wp-media-buttons');
                            if ($mediaButtons.length === 0) {
                                // Media buttons missing - add them manually
                                const $mediaButtonsDiv = $('<div class="wp-media-buttons"></div>');
                                $mediaButtonsDiv.html('<button type="button" class="button insert-media add_media" data-editor="' + editorId + '"><span class="wp-media-buttons-icon"></span> Přidat média</button>');
                                const $editorContainer = $editorWrap.find('.wp-editor-container');
                                if ($editorContainer.length) {
                                    $editorContainer.before($mediaButtonsDiv);
                                } else {
                                    $editorWrap.prepend($mediaButtonsDiv);
                                }
                                console.log('[Content Module] Manually added media buttons after section open:', editorId);
                            } else {
                                // Ensure media buttons are visible
                                $mediaButtons.show().css({
                                    'display': 'block !important',
                                    'visibility': 'visible !important',
                                    'opacity': '1 !important'
                                });
                                console.log('[Content Module] Media buttons made visible for:', editorId);
                            }
                        });
                    }, 100);
                }
            }
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
        $(document).off('click', '.saw-add-document').on('click', '.saw-add-document', function () {
            const targetId = $(this).data('target');
            const deptId = $(this).data('dept-id');
            const docType = $(this).data('doc-type');
            const $list = $('#' + targetId);

            // Get next index
            const nextIndex = $list.find('.saw-document-item').length;

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
            $selectWrapper.append('<label class="saw-doc-type-label">Typ dokumentu</label>');
            const $select = $('<select name="' + selectName + '" class="saw-select"></select>');
            $select.append('<option value="">-- Vyberte typ dokumentu --</option>');

            if (typeof sawDocumentTypes !== 'undefined') {
                sawDocumentTypes.forEach(function (type) {
                    $select.append('<option value="' + type.id + '">' + type.name + '</option>');
                });
            }

            $selectWrapper.append($select);

            // File input name
            let inputName = 'additional_documents[]';
            let inputId = 'additional-doc-input-' + nextIndex;
            if (targetId.includes('risks-docs')) {
                inputName = 'risks_documents[]';
                inputId = 'risks-doc-input-' + nextIndex;
            } else if (targetId.includes('dept-docs') && deptId) {
                inputName = 'department_documents[' + deptId + '][]';
                inputId = 'dept-doc-input-' + deptId + '-' + nextIndex;
            }

            // Create modern file upload container
            const $uploadContainer = $('<div class="saw-file-upload-modern-container"></div>');
            const uploadOptions = {
                multiple: true,
                accept: '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.pages,.numbers,.key,.txt,.rtf',
                maxSize: 10485760, // 10MB
                uploadUrl: sawFileUpload.ajaxurl,
                context: 'content_documents',
                name: inputName,
                id: inputId,
            };
            $uploadContainer.attr('data-options', JSON.stringify(uploadOptions));
            $uploadContainer.attr('data-name', inputName);
            $uploadContainer.attr('data-context', 'content_documents');

            // Remove button
            const $removeBtn = $('<button type="button" class="saw-remove-document">🗑️</button>');
            $removeBtn.on('click', function () {
                $newItem.remove();
            });

            $newItem.append($selectWrapper);
            $newItem.append($uploadContainer);
            $newItem.append($removeBtn);

            $list.append($newItem);

            // Initialize modern file upload component
            if (typeof SAWModernFileUpload !== 'undefined') {
                const uploadInstance = new SAWModernFileUpload($uploadContainer, uploadOptions);
                $uploadContainer.data('saw-file-upload-instance', uploadInstance);
            }
        });

        // Form validation and AJAX submit - typ dokumentu je povinný pouze když je vybrán soubor
        console.log('[Content Module] Binding form submit handler');
        $(document).off('submit.saw-content-form', '.saw-content-form').on('submit.saw-content-form', '.saw-content-form', function (e) {
            console.log('[Content Module] Form submit triggered');
            e.preventDefault(); // Always prevent default to use AJAX

            const $form = $(this);
            console.log('[Content Module] Form found:', $form.length);
            let hasError = false;

            // Validate document type selection for uploaded files
            $form.find('.saw-document-item').each(function () {
                const $uploadContainer = $(this).find('.saw-file-upload-modern-container');
                
                // Get uploaded files from modern upload component
                if ($uploadContainer.length) {
                    const uploadInstance = $uploadContainer.data('saw-file-upload-instance');
                    if (uploadInstance) {
                        const uploadedFiles = uploadInstance.getUploadedFiles();
                        
                        // OPRAVA: Hledat select UVNITŘ upload containeru
                        const $select = $uploadContainer.find('.saw-category-select-input');
                        
                        // OPRAVA: Kontrolovat pouze pokud select existuje A jsou nahrány soubory
                        if ($select.length > 0 && uploadedFiles.length > 0 && !$select.val()) {
                            hasError = true;
                            $select.css('border', '2px solid #dc2626');
                            
                            // Scroll to first error
                            if (!$form.find('.saw-validation-error').length) {
                                $select.before('<div class="saw-validation-error">⚠️ Vyberte typ dokumentu pro nahraný soubor</div>');
                                $('html, body').animate({
                                    scrollTop: $select.offset().top - 100
                                }, 500);
                            }
                        } else if ($select.length > 0) {
                            $select.css('border', '');
                            $uploadContainer.find('.saw-validation-error').remove();
                        }
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

            // Collect uploaded file metadata from all upload components
            const uploadedFilesData = {};
            
            // PDF map
            const $pdfUpload = $form.find('[data-context="content_pdf_map"]');
            if ($pdfUpload.length) {
                const pdfInstance = $pdfUpload.data('saw-file-upload-instance');
                if (pdfInstance) {
                    const pdfFiles = pdfInstance.getUploadedFiles();
                    if (pdfFiles.length > 0) {
                        uploadedFilesData.pdf_map = pdfFiles[0]; // Single file
                    }
                }
            }
            
            // Documents (risks, additional, department) - now using category from component
            $form.find('.saw-document-item').each(function() {
                const $uploadContainer = $(this).find('.saw-file-upload-modern-container');
                
                if ($uploadContainer.length) {
                    const uploadInstance = $uploadContainer.data('saw-file-upload-instance');
                    if (uploadInstance) {
                        const files = uploadInstance.getUploadedFiles();
                        const category = uploadInstance.getSelectedCategory();
                        
                        // Get the category select name from the component's category select
                        const $categorySelect = $uploadContainer.find('.saw-category-select-input');
                        const categorySelectName = $categorySelect.attr('name');
                        
                        if (files.length > 0) {
                            // Category is required for documents
                            if (!category || (Array.isArray(category) && category.length === 0)) {
                                hasError = true;
                                if ($categorySelect.length && $categorySelect.is(':visible')) {
                                    $categorySelect.css('border', '2px solid #dc2626');
                                    if (!$form.find('.saw-validation-error').length) {
                                        $uploadContainer.before('<div class="saw-validation-error">⚠️ Vyberte typ dokumentu před nahráním souboru</div>');
                                        $('html, body').animate({
                                            scrollTop: $categorySelect.offset().top - 100
                                        }, 500);
                                    }
                                }
                            } else {
                                // Use category select name or construct from input name
                                let key = categorySelectName;
                                if (!key) {
                                    // Construct key from input name (e.g., 'risks_documents[]' -> 'risks_documents[]_category')
                                    const inputName = uploadInstance.options.name;
                                    key = inputName + '_category';
                                }
                                
                                if (!uploadedFilesData[key]) {
                                    uploadedFilesData[key] = [];
                                }
                                
                                // For multiple category, assign each file to selected categories
                                // For single category, assign all files to that category
                                if (Array.isArray(category)) {
                                    // Multiple categories selected - assign files to each category
                                    category.forEach(catId => {
                                        files.forEach(file => {
                                            uploadedFilesData[key].push({
                                                file: file,
                                                doc_type: catId
                                            });
                                        });
                                    });
                                } else {
                                    // Single category - assign all files to that category
                                    files.forEach(file => {
                                        uploadedFilesData[key].push({
                                            file: file,
                                            doc_type: category
                                        });
                                    });
                                }
                            }
                        }
                    }
                }
            });

            // AJAX submit
            const formData = new FormData($form[0]);
            
            // Add uploaded files metadata
            formData.append('uploaded_files', JSON.stringify(uploadedFilesData));
            
            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = $submitBtn.html();

            $submitBtn.prop('disabled', true).html('⏳ Ukládám...');
            console.log('[Content Module] FormData created with uploaded files, submitting to:', window.location.href);

            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        // Show success message - use CSS class instead of inline styles
                        const $successMsg = $('<div class="saw-success-notification">✅ Obsah byl úspěšně uložen!</div>');
                        $('body').append($successMsg);

                        // Auto-hide after 4 seconds
                        setTimeout(function () {
                            $successMsg.addClass('hiding');
                            setTimeout(function () {
                                $successMsg.remove();
                            }, 400);
                        }, 4000);

                        // Refresh existing files in all upload components
                        $form.find('.saw-file-upload-modern-container').each(function() {
                            const $container = $(this);
                            const uploadInstance = $container.data('saw-file-upload-instance');
                            if (uploadInstance && typeof uploadInstance.refreshExistingFiles === 'function') {
                                // Refresh from current page URL (will return updated file list)
                                uploadInstance.refreshExistingFiles(window.location.href + '&action=get_existing_files');
                            }
                        });

                        console.log('[Content Module] Form saved successfully');
                    } else {
                        alert('Chyba při ukládání: ' + (response.data || 'Neznámá chyba'));
                    }
                },
                error: function (xhr, status, error) {
                    console.error('[Content Module] Form submit error:', error);
                    alert('Chyba při ukládání: ' + error);
                },
                complete: function () {
                    $submitBtn.prop('disabled', false).html(originalText);
                }
            });

            return false;
        });

        // Remove error on change
        $(document).on('change', '.saw-category-select-input', function () {
            $(this).css('border', '');
            $(this).closest('.saw-file-upload-modern-container').find('.saw-validation-error').remove();
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

                // CRITICAL: DON'T remove existing editors on content page!
                // Content page uses full page reload, so editors are already initialized correctly
                // Removing them causes media buttons to disappear
                const isContentPage = window.location.pathname.indexOf('/admin/content') !== -1 || 
                                      window.location.pathname.indexOf('/content') !== -1;
                
                if (!isContentPage) {
                    // Only remove editors on non-content pages (AJAX-loaded content)
                    if (typeof tinymce !== 'undefined') {
                        $('textarea.wp-editor-area').each(function () {
                            const editorId = $(this).attr('id');
                            if (editorId && tinymce.get(editorId)) {
                                console.log('[Content Module] Removing existing editor:', editorId);
                                wp.editor.remove(editorId);
                            }
                        });
                    }
                } else {
                    console.log('[Content Module] Content page - preserving existing editors');
                }

                // Wait a bit for cleanup
                setTimeout(function () {
                    // Find all textareas that should be TinyMCE editors
                    $('textarea.wp-editor-area').each(function () {
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
                                    setup: function (editor) {
                                        // Ensure editor is fully ready
                                        editor.on('init', function () {
                                            console.log('[Content Module] TinyMCE editor fully initialized:', editorId);
                                        });
                                    }
                                },
                                media_buttons: true,
                                quicktags: false,
                                textarea_name: textareaName
                            });
                            console.log('[Content Module] Initialized TinyMCE editor:', editorId);
                            
                            // CRITICAL: Ensure media buttons are visible after initialization
                            setTimeout(function() {
                                const $editorWrap = $('#wp-' + editorId + '-wrap');
                                if ($editorWrap.length) {
                                    let $mediaButtons = $editorWrap.find('.wp-media-buttons');
                                    if ($mediaButtons.length === 0) {
                                        // Media buttons missing - add them manually
                                        const $mediaButtonsDiv = $('<div class="wp-media-buttons"></div>');
                                        $mediaButtonsDiv.html('<button type="button" class="button insert-media add_media" data-editor="' + editorId + '"><span class="wp-media-buttons-icon"></span> Přidat média</button>');
                                        const $editorContainer = $editorWrap.find('.wp-editor-container');
                                        if ($editorContainer.length) {
                                            $editorContainer.before($mediaButtonsDiv);
                                        } else {
                                            $editorWrap.prepend($mediaButtonsDiv);
                                        }
                                        console.log('[Content Module] Manually added media buttons for:', editorId);
                                    } else {
                                        // Ensure media buttons are visible
                                        $mediaButtons.show().css({
                                            'display': 'block',
                                            'visibility': 'visible',
                                            'opacity': '1'
                                        });
                                        console.log('[Content Module] Media buttons found and made visible for:', editorId);
                                    }
                                }
                            }, 500);
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

                // Check all editor wraps for media buttons
                $('.wp-editor-wrap').each(function () {
                    const $editorWrap = $(this);
                    const editorId = $editorWrap.attr('id').replace('wp-', '').replace('-wrap', '');
                    let $mediaButtons = $editorWrap.find('.wp-media-buttons');
                    
                    if ($mediaButtons.length === 0) {
                        // Media buttons missing - add them manually
                        console.log('[Content Module] Media buttons missing for editor:', editorId, '- adding manually');
                        const $mediaButtonsDiv = $('<div class="wp-media-buttons"></div>');
                        $mediaButtonsDiv.html('<button type="button" class="button insert-media add_media" data-editor="' + editorId + '"><span class="wp-media-buttons-icon"></span> Přidat média</button>');
                        const $editorContainer = $editorWrap.find('.wp-editor-container');
                        if ($editorContainer.length) {
                            $editorContainer.before($mediaButtonsDiv);
                        } else {
                            $editorWrap.prepend($mediaButtonsDiv);
                        }
                    } else {
                        // Media buttons exist - ensure they're visible
                        $mediaButtons.css({
                            'display': 'block !important',
                            'visibility': 'visible !important',
                            'opacity': '1 !important'
                        });
                        $mediaButtons.find('.insert-media').css({
                            'display': 'inline-block !important',
                            'visibility': 'visible !important',
                            'opacity': '1 !important'
                        });
                        console.log('[Content Module] Media buttons found and made visible for:', editorId);
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
        console.log('[Content Module] Event received:', e.type);

        // Check if we're on content page
        const isContentPage = $('#saw-app-content').find('.saw-content-form').length > 0;

        if (isContentPage) {
            console.log('[Content Module] Content page detected, re-initializing...');
            // Reset initialized flag to force re-init
            contentModuleInitialized = false;
            initContentModule();
        }
    });

})(jQuery);
