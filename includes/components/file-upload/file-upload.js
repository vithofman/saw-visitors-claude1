/**
 * File Upload Component Scripts - Modern & Elegant
 * 
 * Handles file selection, validation, preview display, and drag-and-drop
 * functionality for the file upload component.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/FileUpload
 * @version     1.0.0
 * @since       1.0.0
 * @author      SAW Visitors Team
 */

(function($) {
    'use strict';
    
    /**
     * SAW File Upload Component Class
     * 
     * Manages file upload interactions including validation, preview,
     * and drag-and-drop support.
     * 
     * @class
     * @since 1.0.0
     */
    class SAWFileUpload {
        /**
         * Constructor
         * 
         * Initializes the file upload component with all required elements
         * and configuration.
         * 
         * @since 1.0.0
         * @param {jQuery} $component - The component container element
         */
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
        
        /**
         * Initialize component
         * 
         * Sets up event bindings and stores original help text.
         * 
         * @since 1.0.0
         * @return {void}
         */
        init() {
            this.bindEvents();
            this.storeOriginalHelpText();
        }
        
        /**
         * Bind all event handlers
         * 
         * Attaches event listeners for file input, drag-and-drop,
         * and remove actions.
         * 
         * @since 1.0.0
         * @return {void}
         */
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
        
        /**
         * Handle file selection
         * 
         * Validates selected file and displays preview if valid.
         * 
         * @since 1.0.0
         * @param {File} file - The selected file object
         * @return {void}
         */
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
        
        /**
         * Show image preview
         * 
         * Displays the selected image in the preview box with remove overlay.
         * 
         * @since 1.0.0
         * @param {string} src - The image data URL
         * @return {void}
         */
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
        
        /**
         * Show file information
         * 
         * Displays file name, size, and type in the info section.
         * 
         * @since 1.0.0
         * @param {File} file - The selected file object
         * @return {void}
         */
        showFileInfo(file) {
            const size = this.formatFileSize(file.size);
            
            this.$selectedInfo.find('.saw-file-selected-name').text(file.name);
            this.$selectedInfo.find('.saw-file-selected-meta').text(
                'Velikost: ' + size + ' • Typ: ' + file.type.split('/')[1].toUpperCase()
            );
            this.$selectedInfo.removeClass('hidden');
        }
        
        /**
         * Clear selected file
         * 
         * Removes the selected file and hides file info.
         * 
         * @since 1.0.0
         * @return {void}
         */
        clearSelectedFile() {
            this.$input.val('');
            this.$selectedInfo.addClass('hidden');
            this.clearError();
        }
        
        /**
         * Remove existing file
         * 
         * Removes the current file, resets preview, and sets remove flag.
         * 
         * @since 1.0.0
         * @return {void}
         */
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
        
        /**
         * Show error message
         * 
         * Displays an error message in the help text area.
         * 
         * @since 1.0.0
         * @param {string} message - The error message to display
         * @return {void}
         */
        showError(message) {
            this.$helpText.text(message).addClass('error');
        }
        
        /**
         * Clear error message
         * 
         * Removes error styling and restores original help text.
         * 
         * @since 1.0.0
         * @return {void}
         */
        clearError() {
            this.$helpText.removeClass('error');
            const originalText = this.$helpText.data('original-text');
            if (originalText) {
                this.$helpText.text(originalText);
            }
        }
        
        /**
         * Store original help text
         * 
         * Saves the initial help text for restoration after errors.
         * 
         * @since 1.0.0
         * @return {void}
         */
        storeOriginalHelpText() {
            this.$helpText.data('original-text', this.$helpText.text());
        }
        
        /**
         * Format file size
         * 
         * Converts bytes to human-readable format (B, KB, MB, GB).
         * 
         * @since 1.0.0
         * @param {number} bytes - File size in bytes
         * @return {string} Formatted file size string
         */
        formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
    }
    
    /**
     * Initialize all file upload components
     * 
     * @since 1.0.0
     */
    $(document).ready(function() {
        $('.saw-file-upload-component').each(function() {
            new SAWFileUpload($(this));
        });
    });
    
})(jQuery);