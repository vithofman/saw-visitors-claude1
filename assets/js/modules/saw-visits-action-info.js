/**
 * SAW Visits - Action Info Section Handler
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

(function($) {
    'use strict';
    
    const SAWVisitActionInfo = {
        
        init: function() {
            this.bindToggle();
            this.bindOOPPSelector();
            this.bindDocuments();
        },
        
        /**
         * Toggle section visibility
         */
        bindToggle: function() {
            $('#has_action_info').on('change', function() {
                const $content = $('#action-info-content');
                
                if ($(this).is(':checked')) {
                    $content.slideDown(300);
                } else {
                    $content.slideUp(300);
                }
            });
        },
        
        /**
         * OOPP selector - add/remove
         */
        bindOOPPSelector: function() {
            const $availableList = $('.saw-oopp-available .saw-oopp-list');
            const $selectedList = $('#selected-action-oopp');
            
            // Add OOPP
            $(document).on('click', '.saw-add-oopp', function() {
                const $item = $(this).closest('.saw-oopp-item');
                const id = $item.data('id');
                const name = $item.find('.saw-oopp-name').text();
                const group = $item.find('.saw-oopp-group').text();
                
                // Clone and modify
                const $newItem = $item.clone();
                $newItem.addClass('selected');
                $newItem.find('.saw-add-oopp')
                    .removeClass('saw-add-oopp')
                    .addClass('saw-remove-oopp')
                    .text('✕')
                    .attr('title', 'Odebrat');
                
                // Add checkbox and hidden input
                $newItem.find('.saw-oopp-group').after(`
                    <label class="saw-checkbox-inline">
                        <input type="checkbox" name="action_oopp_required[${id}]" value="1" checked>
                        Povinné
                    </label>
                `);
                $newItem.append(`<input type="hidden" name="action_oopp_ids[]" value="${id}">`);
                
                // Move
                $item.remove();
                $selectedList.append($newItem);
            });
            
            // Remove OOPP
            $(document).on('click', '.saw-remove-oopp', function() {
                const $item = $(this).closest('.saw-oopp-item');
                const id = $item.data('id');
                const name = $item.find('.saw-oopp-name').text();
                const group = $item.find('.saw-oopp-group').text();
                
                // Recreate simple item
                const $newItem = $(`
                    <div class="saw-oopp-item" data-id="${id}">
                        <span class="saw-oopp-name">${name}</span>
                        <span class="saw-oopp-group">${group}</span>
                        <button type="button" class="saw-btn-icon saw-add-oopp" title="Přidat">+</button>
                    </div>
                `);
                
                // Move back
                $item.remove();
                $availableList.append($newItem);
            });
        },
        
        /**
         * Document upload handling
         */
        bindDocuments: function() {
            const $dropzone = $('#action-documents-dropzone');
            const $fileInput = $('#action_documents_upload');
            const $list = $('#action-documents-list');
            
            // Click to upload
            $dropzone.on('click', function() {
                $fileInput.trigger('click');
            });
            
            // Drag & drop
            $dropzone.on('dragover', function(e) {
                e.preventDefault();
                $(this).addClass('dragover');
            });
            
            $dropzone.on('dragleave', function() {
                $(this).removeClass('dragover');
            });
            
            $dropzone.on('drop', function(e) {
                e.preventDefault();
                $(this).removeClass('dragover');
                
                const files = e.originalEvent.dataTransfer.files;
                SAWVisitActionInfo.handleFiles(files);
            });
            
            // File input change
            $fileInput.on('change', function() {
                SAWVisitActionInfo.handleFiles(this.files);
            });
            
            // Remove document
            $(document).on('click', '.saw-remove-action-doc', function() {
                $(this).closest('.saw-action-document-item').remove();
            });
        },
        
        /**
         * Handle file selection
         */
        handleFiles: function(files) {
            const $list = $('#action-documents-list');
            
            Array.from(files).forEach(file => {
                const $item = $(`
                    <div class="saw-action-document-item">
                        <span class="saw-doc-icon">📄</span>
                        <span class="saw-doc-name">${file.name}</span>
                        <span class="saw-doc-size">(${this.formatSize(file.size)})</span>
                        <span class="saw-doc-status">⏳ Nahrává se...</span>
                        <button type="button" class="saw-btn-icon saw-remove-action-doc" title="Odebrat">✕</button>
                    </div>
                `);
                
                $list.append($item);
                
                // Upload via AJAX would go here
                // For now, rely on form submission
            });
        },
        
        formatSize: function(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }
    };
    
    // Initialize
    $(document).ready(function() {
        if ($('#has_action_info').length) {
            SAWVisitActionInfo.init();
        }
    });
    
})(jQuery);

