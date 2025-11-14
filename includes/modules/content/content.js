/**
 * Content Module Scripts
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Auto-hide success notification after 4 seconds
        var $notification = $('.saw-success-notification');
        if ($notification.length) {
            setTimeout(function() {
                $notification.addClass('hiding');
                setTimeout(function() {
                    $notification.remove();
                }, 400);
            }, 4000);
        }
        
        // Reinicializace WP Media Buttons po načtení stránky
        if (typeof wp !== 'undefined' && wp.media) {
            // Fix pro media buttons v dynamických editorech
            $('.wp-media-buttons .insert-media').each(function() {
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
        
        // Přepínání jazykových záložek
        $('.saw-tab-btn').on('click', function() {
            $('.saw-tab-btn').removeClass('active');
            $(this).addClass('active');
            
            const tab = $(this).data('tab');
            $('.saw-tab-content').hide();
            $('[data-tab-content="' + tab + '"]').show();
            
            // Po přepnutí záložky reinicializuj media buttons
            setTimeout(function() {
                if (typeof wp !== 'undefined' && wp.media) {
                    $('.wp-media-buttons .insert-media').each(function() {
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
        
        // Rozbalovací sekce
        $('.saw-section-header').on('click', function(e) {
            e.preventDefault();
            $(this).closest('.saw-collapsible-section').toggleClass('open');
        });
        
        // Rozbalovací sekce oddělení
        $(document).on('click', '.saw-department-header', function(e) {
            e.preventDefault();
            $(this).closest('.saw-department-subsection').toggleClass('open');
        });
        
        // Přidat další dokument
        $('.saw-add-document').on('click', function() {
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
                sawDocumentTypes.forEach(function(type) {
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
            
            $removeBtn.on('click', function() {
                $newItem.remove();
            });
            
            $newItem.append($selectWrapper);
            $newItem.append($fileInput);
            $newItem.append($removeBtn);
            
            $list.append($newItem);
        });
        
        // Form validation - typ dokumentu je povinný pouze když je vybrán soubor
        $('.saw-content-form').on('submit', function(e) {
            let hasError = false;
            
            $(this).find('.saw-document-item').each(function() {
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
        $(document).on('change', '.saw-document-item select', function() {
            $(this).css('border', '');
            $(this).siblings('.saw-validation-error').remove();
        });
        
        // Odstranit dokument (pro dynamicky přidané)
        $(document).on('click', '.saw-remove-document', function() {
            $(this).closest('.saw-document-item').remove();
        });
        
    });
    
})(jQuery);
