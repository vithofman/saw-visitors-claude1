/**
 * SAW Visitors - Content Management Page JavaScript
 * Entity-specific JavaScript for content management functionality
 * 
 * @package SAW_Visitors
 * @since   4.6.1
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        /**
         * Language tabs switching
         */
        $('.saw-language-tab').on('click', function() {
            const lang = $(this).data('lang');
            
            $('.saw-language-tab').removeClass('active');
            $(this).addClass('active');
            
            $('.saw-language-content').hide();
            $(`.saw-language-content[data-lang="${lang}"]`).show();
            
            reinitializeTinyMCE(lang);
        });

        /**
         * Accordion toggle
         */
        $('.saw-accordion-header').on('click', function(e) {
            e.preventDefault();
            const $item = $(this).closest('.saw-accordion-item');
            const $content = $item.find('.saw-accordion-content');
            const isActive = $item.hasClass('active');
            
            if (isActive) {
                $item.removeClass('active');
                $content.hide();
            } else {
                $item.addClass('active');
                $content.show();
                
                setTimeout(function() {
                    $content.find('.wp-editor-area').each(function() {
                        const editorId = $(this).attr('id');
                        if (editorId && typeof tinymce !== 'undefined') {
                            if (!tinymce.get(editorId)) {
                                tinymce.execCommand('mceAddEditor', false, editorId);
                            }
                        }
                    });
                }, 100);
            }
        });

        $('.saw-accordion-item:first-child').addClass('active');
        $('.saw-accordion-item:first-child .saw-accordion-content').show();

        /**
         * Add department editor
         */
        $('.saw-add-dept-btn').on('click', function() {
            const lang = $(this).data('lang');
            const $select = $(`#dept_select_${lang}`);
            const deptId = $select.val();
            const deptName = $select.find('option:selected').text();
            
            if (!deptId) {
                alert('Prosím vyberte oddělení.');
                return;
            }
            
            const $container = $(`#dept_editors_${lang}`);
            if ($container.find(`.saw-dept-editor-block[data-dept-id="${deptId}"]`).length > 0) {
                alert('Toto oddělení je již přidáno.');
                return;
            }
            
            const editorId = `dept_wysiwyg_${lang}_${deptId}_${Date.now()}`;
            const html = `
                <div class="saw-dept-editor-block" data-dept-id="${deptId}">
                    <div class="dept-header">
                        <h4 class="dept-title">🏭 ${deptName}</h4>
                        <button type="button" class="button button-small button-link-delete saw-remove-dept-btn">
                            🗑️ Odstranit
                        </button>
                    </div>
                    <input type="hidden" name="dept_ids_${lang}[]" value="${deptId}">
                    <textarea id="${editorId}" name="dept_wysiwyg_${lang}_${deptId}" rows="8"></textarea>
                </div>
            `;
            
            $container.append(html);
            
            if (typeof wp !== 'undefined' && wp.editor) {
                wp.editor.initialize(editorId, {
                    tinymce: {
                        wpautop: true,
                        plugins: 'charmap,colorpicker,hr,lists,media,paste,tabfocus,textcolor,fullscreen,wordpress,wpautoresize,wpeditimage,wpemoji,wpgallery,wplink,wpdialogs,wptextpattern,wpview',
                        toolbar1: 'bold,italic,bullist,numlist,link,unlink',
                        toolbar2: '',
                    },
                    quicktags: true,
                    mediaButtons: false,
                });
            }
            
            $select.val('');
        });

        /**
         * Remove department editor
         */
        $(document).on('click', '.saw-remove-dept-btn:not([href])', function(e) {
            e.preventDefault();
            
            if (!confirm('Opravdu odstranit informace pro toto oddělení?')) {
                return;
            }
            
            const $block = $(this).closest('.saw-dept-editor-block');
            const editorId = $block.find('textarea').attr('id');
            
            if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                tinymce.execCommand('mceRemoveEditor', false, editorId);
            }
            
            $block.fadeOut(300, function() {
                $(this).remove();
            });
        });

        /**
         * File input - show selected filename
         */
        $('.saw-file-input').on('change', function() {
            const $uploadArea = $(this).closest('.saw-upload-area');
            const files = this.files;
            
            if (files.length > 0) {
                let fileNames = '';
                for (let i = 0; i < files.length; i++) {
                    fileNames += files[i].name;
                    if (i < files.length - 1) {
                        fileNames += ', ';
                    }
                }
                
                let $fileInfo = $uploadArea.find('.upload-file-info');
                if ($fileInfo.length === 0) {
                    $uploadArea.append(`<p class="upload-file-info" style="margin-top: 10px; font-size: 13px; color: #2c3338;"><strong>Vybrané soubory:</strong> ${fileNames}</p>`);
                } else {
                    $fileInfo.html(`<strong>Vybrané soubory:</strong> ${fileNames}`);
                }
                
                $uploadArea.css({
                    'border-color': '#00a32a',
                    'background': '#edfaef'
                });
            }
        });

        /**
         * Video URL validation
         */
        $('.saw-video-url-input').on('blur', function() {
            const url = $(this).val().trim();
            
            if (url === '') {
                return;
            }
            
            const isYouTube = url.includes('youtube.com') || url.includes('youtu.be');
            const isVimeo = url.includes('vimeo.com');
            
            if (!isYouTube && !isVimeo) {
                alert('⚠️ Neplatná URL adresa videa. Podporovány jsou pouze YouTube a Vimeo.');
                $(this).val('');
                $(this).focus();
            }
        });

        /**
         * Drag & drop upload support
         */
        $('.saw-upload-area').on('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).css({
                'border-color': '#0073aa',
                'background': '#f0f6fc'
            });
        });

        $('.saw-upload-area').on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).css({
                'border-color': '#c3c4c7',
                'background': '#f9f9f9'
            });
        });

        $('.saw-upload-area').on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const files = e.originalEvent.dataTransfer.files;
            const $input = $(this).find('.saw-file-input');
            
            if (files.length > 0) {
                $input[0].files = files;
                $input.trigger('change');
            }
        });

        /**
         * Form submit validation
         */
        $('#saw-content-form').on('submit', function(e) {
            let hasContent = false;
            
            $('.saw-language-content').each(function() {
                const $content = $(this);
                
                if ($content.find('.saw-video-url-input').val().trim() !== '') {
                    hasContent = true;
                }
                
                if ($content.find('input[name^="pdf_"]')[0]?.files.length > 0) {
                    hasContent = true;
                }
                
                $content.find('.wp-editor-area').each(function() {
                    const editorId = $(this).attr('id');
                    if (editorId && typeof tinymce !== 'undefined') {
                        const editor = tinymce.get(editorId);
                        if (editor && editor.getContent().trim() !== '') {
                            hasContent = true;
                        }
                    }
                });
                
                if ($content.find('.existing-documents .doc-row').length > 0) {
                    hasContent = true;
                }
            });
            
            if (!hasContent) {
                if (!confirm('⚠️ Nebyly vyplněny žádné údaje. Opravdu chcete pokračovat?')) {
                    e.preventDefault();
                    return false;
                }
            }
            
            $(this).find('.button-primary').prop('disabled', true).text('💾 Ukládám...');
        });

        /**
         * Reinitialize TinyMCE editors for given language
         */
        function reinitializeTinyMCE(lang) {
            $(`.saw-language-content[data-lang="${lang}"] .wp-editor-area`).each(function() {
                const editorId = $(this).attr('id');
                if (editorId && typeof tinymce !== 'undefined') {
                    if (tinymce.get(editorId)) {
                        tinymce.execCommand('mceRemoveEditor', false, editorId);
                    }
                    tinymce.execCommand('mceAddEditor', false, editorId);
                }
            });
        }

    });

})(jQuery);