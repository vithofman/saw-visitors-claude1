/**
 * Content Module Scripts
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
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
            const $list = $('#' + targetId);
            
            const $newItem = $('<div class="saw-document-item"></div>');
            
            let inputName = 'additional_documents[]';
            if (targetId.includes('risks-docs')) {
                inputName = 'risks_documents[]';
            } else if (targetId.includes('dept-docs') && deptId) {
                inputName = 'department_documents[' + deptId + '][]';
            }
            
            const $fileInput = $('<input type="file" name="' + inputName + '" accept="application/pdf,.doc,.docx" class="saw-file-input">');
            
            const $removeBtn = $('<button type="button" class="saw-remove-document">🗑️</button>');
            
            $removeBtn.on('click', function() {
                $newItem.remove();
            });
            
            $newItem.append($fileInput);
            $newItem.append($removeBtn);
            
            $list.append($newItem);
        });
        
        // Odstranit dokument (pro dynamicky přidané)
        $(document).on('click', '.saw-remove-document', function() {
            $(this).closest('.saw-document-item').remove();
        });
        
    });
    
})(jQuery);
