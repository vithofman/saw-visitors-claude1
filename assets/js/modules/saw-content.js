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

        // Reinicializace WP Media Buttons po načtení stránky
        if (typeof wp !== 'undefined' && wp.media) {
            // Fix pro media buttons v dynamických editorech
            $('.wp-media-buttons .insert-media').each(function () {
                var $button = $(this);
                var editorId = $button.data('editor');

                if (!editorId) {
                    // Zjisti editor ID z atributu
                    var $editorWrap = $button.closest('.wp-editor-wrap');
                    if ($editorWrap.length) {
                        editorId = $editorWrap.attr('id').replace('wp-', '').replace('-wrap', '');
                        $button.attr('data-editor', editorId);
                    }
                }
            });
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

        // Form validation - typ dokumentu je povinný pouze když je vybrán soubor
        $('.saw-content-form').on('submit', function (e) {
            let hasError = false;

            $(this).find('.saw-document-item').each(function () {
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
                e.preventDefault();
                return false;
            }

            return true;
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

    // Re-initialize after AJAX page load
    $(document).on('saw:page-loaded saw:module-reinit', function (e, data) {
        console.log('[Content Module] Event received:', e.type, 'Data:', data);
        // Check if we're on content page
        const isContentPage = $('#saw-app-content').find('.saw-content-form').length > 0;
        console.log('[Content Module] Is content page:', isContentPage);
        
        // Only reinitialize if content module is active
        if (data && typeof data === 'object' && (data.active_menu === 'content' || isContentPage)) {
            console.log('[Content Module] Re-initializing after AJAX load (active_menu:', data.active_menu, ')');
            initContentModule();
        } else if (isContentPage) {
            // If no data provided but we're on content page, initialize anyway
            console.log('[Content Module] Re-initializing after AJAX load (detected by DOM)');
            initContentModule();
        } else {
            console.log('[Content Module] Skipping - not content page');
        }
    });

})(jQuery);
