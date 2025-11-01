/**
 * File Upload Component Scripts - Modern & Elegant
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

(function($) {
    'use strict';
    
    class SAWFileUpload {
        constructor($component) {
            this.$component = $component;
            this.$input = $component.find('.saw-file-input');
            this.$preview = $component.find('.saw-file-preview-box');
            this.$removeOverlay = $component.find('.saw-file-remove-overlay');
            this.$selectedInfo = $component.find('.saw-file-selected-info');
            this.$clearBtn = $component.find('.saw-file-clear-btn');
            this.$helpText = $component.find('.saw-help-text');
            this.$hiddenRemove = $component.find('.saw-file-remove-flag');
            
            this.maxSize = parseInt(this.$input.data('max-size')) || 2097152;
            this.allowedTypes = (this.$input.attr('accept') || '').split(',').map(t => t.trim());
            
            this.init();
        }
        
        init() {
            this.bindEvents();
            this.storeOriginalHelpText();
        }
        
        bindEvents() {
            // File input change
            this.$input.on('change', (e) => {
                this.handleFileSelect(e.target.files[0]);
            });
            
            // Remove via overlay
            this.$removeOverlay.on('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.removeExistingFile();
            });
            
            // Clear selected file
            this.$clearBtn.on('click', (e) => {
                e.preventDefault();
                this.clearSelectedFile();
            });
            
            // Drag & Drop
            this.$preview.on('dragover', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.$preview.addClass('dragging');
            });
            
            this.$preview.on('dragleave', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.$preview.removeClass('dragging');
            });
            
            this.$preview.on('drop', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.$preview.removeClass('dragging');
                
                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    this.$input[0].files = files;
                    this.handleFileSelect(files[0]);
                }
            });
        }
        
        handleFileSelect(file) {
            if (!file) {
                return;
            }
            
            // Validate size
            if (file.size > this.maxSize) {
                const maxMB = Math.round(this.maxSize / 1048576 * 10) / 10;
                this.showError('Soubor je příliš velký! Maximální velikost je ' + maxMB + 'MB.');
                this.$input.val('');
                return;
            }
            
            // Validate type
            if (this.allowedTypes.length > 0 && !this.allowedTypes.some(type => file.type.match(type))) {
                this.showError('Neplatný typ souboru!');
                this.$input.val('');
                return;
            }
            
            this.clearError();
            
            // Show preview
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.showPreview(e.target.result);
                };
                reader.readAsDataURL(file);
            }
            
            // Show file info
            this.showFileInfo(file);
            
            // Clear remove flag
            if (this.$hiddenRemove.length) {
                this.$hiddenRemove.val('0');
            }
        }
        
        showPreview(src) {
            this.$preview.html(
                '<img src="' + src + '" alt="Preview" class="saw-preview-image">' +
                '<button type="button" class="saw-file-remove-overlay" title="Odstranit">' +
                '<span class="dashicons dashicons-no-alt"></span>' +
                '</button>'
            );
            this.$preview.addClass('has-file');
            
            // Rebind overlay event
            this.$removeOverlay = this.$preview.find('.saw-file-remove-overlay');
            this.$removeOverlay.on('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.removeExistingFile();
            });
        }
        
        showFileInfo(file) {
            const size = this.formatFileSize(file.size);
            
            this.$selectedInfo.find('.saw-file-selected-name').text(file.name);
            this.$selectedInfo.find('.saw-file-selected-meta').text(
                'Velikost: ' + size + ' • Typ: ' + file.type.split('/')[1].toUpperCase()
            );
            this.$selectedInfo.removeClass('hidden');
        }
        
        clearSelectedFile() {
            this.$input.val('');
            this.$selectedInfo.addClass('hidden');
            this.clearError();
        }
        
        removeExistingFile() {
            // Clear input
            this.$input.val('');
            
            // Reset preview
            this.$preview.html(
                '<div class="saw-file-empty-state">' +
                '<div class="saw-file-icon-wrapper">' +
                '<span class="dashicons dashicons-format-image"></span>' +
                '</div>' +
                '<p class="saw-file-empty-text">Zatím žádné logo</p>' +
                '</div>'
            );
            this.$preview.removeClass('has-file');
            
            // Hide file info
            this.$selectedInfo.addClass('hidden');
            
            // Set remove flag
            if (this.$hiddenRemove.length) {
                this.$hiddenRemove.val('1');
            }
            
            this.clearError();
        }
        
        showError(message) {
            this.$helpText.text(message).addClass('error');
        }
        
        clearError() {
            this.$helpText.removeClass('error');
            const originalText = this.$helpText.data('original-text');
            if (originalText) {
                this.$helpText.text(originalText);
            }
        }
        
        storeOriginalHelpText() {
            this.$helpText.data('original-text', this.$helpText.text());
        }
        
        formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
    }
    
    // Initialize
    $(document).ready(function() {
        $('.saw-file-upload-component').each(function() {
            new SAWFileUpload($(this));
        });
    });
    
})(jQuery);