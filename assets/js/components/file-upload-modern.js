/**
 * Modern File Upload Component
 * 
 * Handles file uploads with AJAX, progress tracking, drag & drop,
 * toast notifications, and inline validation.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/FileUpload
 * @version     2.0.0
 * @since       2.0.0
 */

(function($) {
    'use strict';
    
    /**
     * SAW Modern File Upload Class
     * 
     * Manages file upload interactions including validation, preview,
     * progress tracking, and drag-and-drop support.
     * 
     * @class
     * @since 2.0.0
     */
    class SAWModernFileUpload {
        /**
         * Constructor
         * 
         * @since 2.0.0
         * @param {jQuery} $container - The component container element
         * @param {object} options - Configuration options
         */
        constructor($container, options = {}) {
            this.$container = $container;
            this.options = $.extend({
                multiple: false,
                accept: '',
                maxSize: 0, // 0 = no limit
                maxFiles: 0, // 0 = no limit
                uploadUrl: sawFileUpload.ajaxurl,
                context: 'documents',
                name: '',
                id: '',
                existingFiles: [], // Array of existing file metadata
                categoryConfig: {}, // Category/document type configuration
            }, options);
            
            this.files = []; // Array of file objects with metadata
            this.uploading = false;
            this.uploadQueue = [];
            this.lastMessage = null; // Last status message for info bar
            
            this.init();
        }
        
        /**
         * Initialize component
         * 
         * @since 2.0.0
         * @return {void}
         */
        init() {
            // Load existing files into files array BEFORE rendering
            if (this.options.existingFiles && Array.isArray(this.options.existingFiles)) {
                this.options.existingFiles.forEach(fileData => {
                    const fileObj = {
                        id: 'existing_' + (fileData.id || Date.now() + Math.random()),
                        file: null, // No File object for existing files
                        name: fileData.name || (fileData.path ? fileData.path.split('/').pop() : 'Unknown'),
                        size: fileData.size || 0,
                        type: fileData.type || 'application/octet-stream',
                        status: 'success',
                        progress: 100,
                        error: null,
                        metadata: fileData, // Store full metadata including id, url, path, category
                        isExisting: true, // Flag to identify existing files
                    };
                    this.files.push(fileObj);
                });
            }
            
            this.bindEvents();
            this.render();
        }
        
        /**
         * Bind event handlers
         * 
         * @since 2.0.0
         * @return {void}
         */
        bindEvents() {
            const self = this;
            
            // File input change
            this.$container.on('change', '.saw-file-input', function(e) {
                const files = Array.from(e.target.files);
                self.handleFileSelect(files);
            });
            
            // Drag & drop
            this.$container.on('dragover', '.saw-upload-zone', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('dragging');
            });
            
            this.$container.on('dragleave', '.saw-upload-zone', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragging');
            });
            
            this.$container.on('drop', '.saw-upload-zone', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragging');
                
                const files = Array.from(e.originalEvent.dataTransfer.files);
                self.handleFileSelect(files);
            });
            
            // Category select change - auto-resume waiting uploads
            // Use event delegation to catch changes even if select is created later
            this.$container.on('change', '.saw-category-select-input', function() {
                // Check for files waiting for category
                const waitingFiles = self.files.filter(f => f.status === 'waiting_category');
                if (waitingFiles.length > 0) {
                    // Get current category value
                    const $select = $(this);
                    const categoryValue = $select.is('select[multiple]') 
                        ? $select.val() 
                        : $select.val();
                    
                    if (categoryValue && (!Array.isArray(categoryValue) || categoryValue.length > 0)) {
                        // Update category for all waiting files and start upload
                        waitingFiles.forEach(fileObj => {
                            fileObj.category = categoryValue;
                            fileObj.status = 'pending';
                            self.uploadFile(fileObj);
                        });
                        
                        self.showInfoMessage('success', `Nahrávání ${waitingFiles.length} soubor${waitingFiles.length > 1 ? 'ů' : ''} bylo spuštěno`);
                    }
                }
            });
            
            // Remove file
            this.$container.on('click', '.saw-file-remove', function(e) {
                e.preventDefault();
                const fileId = $(this).data('file-id');
                const fileObj = self.files.find(f => f.id === fileId);
                
                // If it's an existing file with DB ID, delete physically
                if (fileObj && fileObj.isExisting && fileObj.metadata && fileObj.metadata.id) {
                    self.deleteFile(fileObj.metadata.id, fileId);
                } else {
                    self.removeFile(fileId);
                }
            });
            
            // View file
            this.$container.on('click', '.saw-file-view', function(e) {
                e.preventDefault();
                const fileId = $(this).data('file-id');
                const fileObj = self.files.find(f => f.id === fileId);
                
                if (fileObj && fileObj.metadata && fileObj.metadata.url) {
                    window.open(fileObj.metadata.url, '_blank');
                }
            });
            
            // Retry upload
            this.$container.on('click', '.saw-file-retry', function(e) {
                e.preventDefault();
                const fileId = $(this).data('file-id');
                self.retryUpload(fileId);
            });
        }
        
        /**
         * Render component HTML
         * 
         * @since 2.0.0
         * @return {void}
         */
        render() {
            const multiple = this.options.multiple ? 'multiple' : '';
            const accept = this.options.accept ? `accept="${this.options.accept}"` : '';
            const inputId = this.options.id || 'saw-file-input-' + Math.random().toString(36).substr(2, 9);
            
            // Category select HTML (if enabled) - INSIDE upload zone as second row
            let categorySelectHtml = '';
            if (this.options.categoryConfig && this.options.categoryConfig.enabled) {
                const categoryOptions = this.options.categoryConfig.options || [];
                const categoryLabel = this.options.categoryConfig.label || 'Kategorie';
                const isMultiple = this.options.categoryConfig.multiple || false;
                const categoryName = this.options.name + '_category';
                // Don't set required attribute - validate in JS to prevent browser errors
                
                if (isMultiple) {
                    categorySelectHtml = `
                        <div class="saw-upload-category-row">
                            <label class="saw-upload-category-label">${categoryLabel}:</label>
                            <select name="${categoryName}[]" class="saw-select saw-category-select-input" multiple size="3">
                                ${categoryOptions.map(opt => `<option value="${opt.id}">${this.escapeHtml(opt.name)}</option>`).join('')}
                            </select>
                        </div>
                    `;
                } else {
                    categorySelectHtml = `
                        <div class="saw-upload-category-row">
                            <label class="saw-upload-category-label">${categoryLabel}:</label>
                            <select name="${categoryName}" class="saw-select saw-category-select-input">
                                <option value="">-- Vyberte ${categoryLabel.toLowerCase()} --</option>
                                ${categoryOptions.map(opt => `<option value="${opt.id}">${this.escapeHtml(opt.name)}</option>`).join('')}
                            </select>
                        </div>
                    `;
                }
            }
            
            const html = `
                <div class="saw-file-upload-modern">
                    <div class="saw-upload-zone">
                        <input 
                            type="file" 
                            id="${inputId}"
                            name="${this.options.name}"
                            class="saw-file-input"
                            ${multiple}
                            ${accept}
                            style="display: none;"
                        >
                        <div class="saw-upload-zone-content">
                            <div class="saw-upload-zone-row saw-upload-zone-row-main">
                                <span class="saw-upload-icon">☁️</span>
                                <span class="saw-upload-text">${sawFileUpload.strings.drag_drop}</span>
                                <label for="${inputId}" class="saw-upload-button">${sawFileUpload.strings.select_files}</label>
                                <span class="saw-upload-info" title="Povolené formáty: ${this.options.accept || 'Všechny'}">ℹ️</span>
                            </div>
                            ${categorySelectHtml}
                        </div>
                    </div>
                    <div class="saw-file-list"></div>
                    <div class="saw-status-bar" style="display: none;"></div>
                    <div class="saw-info-bar" style="display: none;"></div>
                    <div class="saw-validation-error" style="display: none;"></div>
                </div>
            `;
            
            this.$container.html(html);
            this.$fileList = this.$container.find('.saw-file-list');
            this.$statusBar = this.$container.find('.saw-status-bar');
            this.$infoBar = this.$container.find('.saw-info-bar');
            this.$validationError = this.$container.find('.saw-validation-error');
            this.$categorySelect = this.$container.find('.saw-category-select-input');
            
            // Render existing files after DOM is ready
            this.renderFileList();
            this.updateStatusBar();
        }
        
        /**
         * Handle file selection
         * 
         * @since 2.0.0
         * @param {Array} files - Array of File objects
         * @return {void}
         */
        handleFileSelect(files) {
            if (!this.options.multiple && files.length > 1) {
                files = [files[0]]; // Take only first file
            }
            
            // Check max files limit
            const currentFileCount = this.files.filter(f => f.status === 'success' || f.status === 'uploading').length;
            const totalAfterAdd = currentFileCount + files.length;
            
            if (this.options.maxFiles > 0 && totalAfterAdd > this.options.maxFiles) {
                const allowed = this.options.maxFiles - currentFileCount;
                if (allowed <= 0) {
                    this.showValidationError(`Maximální počet souborů je ${this.options.maxFiles}`);
                    return;
                }
                files = files.slice(0, allowed);
                this.showValidationError(`Můžete nahrát maximálně ${this.options.maxFiles} souborů. Přidáno ${allowed} z ${files.length + allowed - allowed}`);
            }
            
            // For single file mode (maxFiles = 1), remove existing file
            if (this.options.maxFiles === 1 && this.files.length > 0) {
                // Remove the first file (old one)
                const oldFile = this.files[0];
                if (oldFile.status === 'success' && oldFile.metadata) {
                    // If it's an existing file, mark for deletion
                    if (oldFile.metadata.id) {
                        this.deleteFile(oldFile.metadata.id, oldFile.id);
                    } else {
                        this.removeFile(oldFile.id);
                    }
                } else {
                    this.removeFile(oldFile.id);
                }
            }
            
            // Validate files
            const validFiles = [];
            const errors = [];
            
            files.forEach(file => {
                const validation = this.validateFile(file);
                if (validation.valid) {
                    validFiles.push(file);
                } else {
                    errors.push(validation.error);
                }
            });
            
            // Show validation errors
            if (errors.length > 0) {
                this.showValidationError(errors.join('<br>'));
            } else {
                this.hideValidationError();
            }
            
            // Add valid files and upload
            validFiles.forEach(file => {
                this.addFile(file);
            });
        }
        
        /**
         * Validate file
         * 
         * @since 2.0.0
         * @param {File} file - File object
         * @return {object} Validation result
         */
        validateFile(file) {
            // Check file size
            if (this.options.maxSize > 0 && file.size > this.options.maxSize) {
                const maxMB = Math.round(this.options.maxSize / 1048576 * 10) / 10;
                return {
                    valid: false,
                    error: `Soubor "${file.name}" je příliš velký (max ${maxMB}MB)`
                };
            }
            
            // Check file type
            if (this.options.accept) {
                const allowedTypes = this.options.accept.split(',').map(t => t.trim().toLowerCase());
                const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
                
                const isAllowed = allowedTypes.some(type => {
                    if (type.startsWith('.')) {
                        return type === fileExtension;
                    }
                    // MIME type check
                    return file.type && file.type.match(type.replace('*', ''));
                });
                
                if (!isAllowed) {
                    return {
                        valid: false,
                        error: `Soubor "${file.name}" má nepodporovaný formát`
                    };
                }
            }
            
            return { valid: true };
        }
        
        /**
         * Add file to list and start upload
         * 
         * @since 2.0.0
         * @param {File} file - File object
         * @return {void}
         */
        addFile(file) {
            const fileId = 'file_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            
            // Get category value
            let categoryValue = null;
            if (this.$categorySelect && this.$categorySelect.length) {
                if (this.$categorySelect.is('select[multiple]')) {
                    categoryValue = this.$categorySelect.val(); // Array for multiple
                } else {
                    categoryValue = this.$categorySelect.val(); // Single value
                }
            }
            
            // Check if category is required but not selected
            const categoryRequired = this.options.categoryConfig && 
                                    this.options.categoryConfig.enabled && 
                                    this.options.categoryConfig.required;
            const categoryMissing = !categoryValue || 
                                   (Array.isArray(categoryValue) && categoryValue.length === 0);
            
            // Max files check
            if (this.options.maxFiles > 0 && this.files.length >= this.options.maxFiles) {
                this.showValidationError(`Můžete nahrát maximálně ${this.options.maxFiles} souborů.`);
                return;
            }
            
            const fileObj = {
                id: fileId,
                file: file,
                name: file.name,
                size: file.size,
                type: file.type,
                status: (categoryRequired && categoryMissing) ? 'waiting_category' : 'pending',
                progress: 0,
                error: null,
                metadata: null,
                category: categoryValue, // Store category (can be single value or array)
            };
            
            this.files.push(fileObj);
            this.renderFileList();
            this.updateStatusBar();
            
            // Show info message if waiting for category
            if (fileObj.status === 'waiting_category') {
                this.showInfoMessage('info', 'Vyberte ' + (this.options.categoryConfig.label || 'kategorii') + ' pro pokračování nahrávání');
                // Focus category select
                if (this.$categorySelect && this.$categorySelect.length) {
                    this.$categorySelect.focus();
                }
            } else {
                // Start upload immediately if category is selected
                this.uploadFile(fileObj);
            }
        }
        
        /**
         * Upload file via AJAX
         * 
         * @since 2.0.0
         * @param {object} fileObj - File object with metadata
         * @return {void}
         */
        uploadFile(fileObj) {
            // Check if category is required but missing
            if (this.options.categoryConfig && 
                this.options.categoryConfig.enabled && 
                this.options.categoryConfig.required) {
                const $categorySelect = this.$categorySelect;
                if ($categorySelect && $categorySelect.length && $categorySelect.is(':visible')) {
                    const categoryValue = $categorySelect.is('select[multiple]') 
                        ? $categorySelect.val() 
                        : $categorySelect.val();
                    
                    if (!categoryValue || (Array.isArray(categoryValue) && categoryValue.length === 0)) {
                        // Pause upload - set status to waiting
                        fileObj.status = 'waiting_category';
                        fileObj.category = null; // Clear category
                        this.renderFileList();
                        this.updateStatusBar();
                        this.showInfoMessage('info', 'Vyberte ' + (this.options.categoryConfig.label || 'kategorii') + ' pro pokračování nahrávání');
                        return;
                    }
                    
                    // Update category in fileObj
                    fileObj.category = categoryValue;
                }
            }
            
            fileObj.status = 'uploading';
            fileObj.progress = 0;
            this.renderFileList();
            
            const formData = new FormData();
            formData.append('action', 'saw_upload_file');
            formData.append('nonce', sawFileUpload.nonce);
            formData.append('file', fileObj.file);
            formData.append('context', this.options.context);
            formData.append('max_size', this.options.maxSize);
            formData.append('accept', this.options.accept);
            
            const self = this;
            
            $.ajax({
                url: this.options.uploadUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    
                    // Upload progress
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            const percent = Math.round((e.loaded / e.total) * 100);
                            fileObj.progress = percent;
                            self.updateFileProgress(fileObj.id, percent);
                        }
                    }, false);
                    
                    return xhr;
                },
                success: function(response) {
                    if (response.success && response.data && response.data.file) {
                        fileObj.status = 'success';
                        fileObj.progress = 100;
                        fileObj.metadata = response.data.file;
                        // Add category to metadata if available
                        if (fileObj.category) {
                            fileObj.metadata.category = fileObj.category;
                            // If category is array, use first one for display
                            if (Array.isArray(fileObj.category) && fileObj.category.length > 0) {
                                fileObj.metadata.category_name = self.getCategoryName(fileObj.category[0]);
                            } else if (!Array.isArray(fileObj.category)) {
                                fileObj.metadata.category_name = self.getCategoryName(fileObj.category);
                            }
                        }
                        // Show info message
                        self.showInfoMessage('success', `Soubor "${fileObj.name}" byl úspěšně nahrán`);
                        
                        // After successful upload, refresh existing files if needed
                        setTimeout(() => {
                            self.renderFileList();
                        }, 100);
                    } else {
                        fileObj.status = 'error';
                        fileObj.error = response.data?.message || 'Nepodařilo se nahrát soubor';
                        self.showInfoMessage('error', fileObj.error);
                    }
                    self.renderFileList();
                    self.updateStatusBar();
                },
                error: function(xhr, status, error) {
                    fileObj.status = 'error';
                    fileObj.error = error || 'Chyba při nahrávání';
                    self.showInfoMessage('error', fileObj.error);
                    self.renderFileList();
                    self.updateStatusBar();
                }
            });
        }
        
        /**
         * Update file progress
         * 
         * @since 2.0.0
         * @param {string} fileId - File ID
         * @param {number} percent - Progress percentage
         * @return {void}
         */
        updateFileProgress(fileId, percent) {
            const $fileItem = this.$fileList.find(`[data-file-id="${fileId}"]`);
            const $progressBar = $fileItem.find('.saw-file-progress-bar');
            const $progressFill = $progressBar.find('.saw-file-progress-fill');
            
            $progressFill.css('width', percent + '%');
        }
        
        /**
         * Render file list
         * 
         * @since 2.0.0
         * @return {void}
         */
        renderFileList() {
            if (this.files.length === 0) {
                this.$fileList.empty();
                return;
            }
            
            let html = '';
            
            this.files.forEach(fileObj => {
                const fileIcon = this.getFileIcon(fileObj.type, fileObj.metadata?.extension);
                const fileSize = this.formatFileSize(fileObj.size);
                const statusIcon = this.getStatusIcon(fileObj.status);
                const statusClass = 'status-' + fileObj.status;
                
                // Show category badge if available
                let categoryBadge = '';
                if (fileObj.metadata && fileObj.metadata.category_name) {
                    categoryBadge = `<span class="saw-file-category">${this.escapeHtml(fileObj.metadata.category_name)}</span>`;
                } else if (fileObj.category && this.options.categoryConfig && this.options.categoryConfig.options) {
                    const categoryOption = this.options.categoryConfig.options.find(opt => opt.id == fileObj.category);
                    if (categoryOption) {
                        categoryBadge = `<span class="saw-file-category">${this.escapeHtml(categoryOption.name)}</span>`;
                    }
                }
                
                html += `
                    <div class="saw-file-item ${statusClass}" data-file-id="${fileObj.id}">
                        <div class="saw-file-item-row">
                            <span class="saw-file-icon">${fileIcon}</span>
                            <div class="saw-file-item-row-main">
                                ${categoryBadge ? `<div class="saw-file-item-row">${categoryBadge}</div>` : ''}
                                <div class="saw-file-item-row">
                                    <span class="saw-file-name">${this.escapeHtml(fileObj.name)}</span>
                                    <span class="saw-file-size">${fileSize}</span>
                                </div>
                            </div>
                            <div class="saw-file-status">
                                ${this.getStatusContent(fileObj)}
                            </div>
                            <div class="saw-file-item-row-actions">
                                ${this.getActionButtons(fileObj)}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            this.$fileList.html(html);
        }
        
        /**
         * Get status content (progress bar or icon)
         * 
         * @since 2.0.0
         * @param {object} fileObj - File object
         * @return {string} HTML
         */
        getStatusContent(fileObj) {
            if (fileObj.status === 'uploading') {
                return `
                    <div class="saw-file-progress-bar">
                        <div class="saw-file-progress-fill" style="width: ${fileObj.progress}%"></div>
                    </div>
                `;
            }
            
            if (fileObj.status === 'waiting_category') {
                return `
                    <div class="saw-file-status-waiting">
                        <span class="saw-file-status-icon">⏸️</span>
                        <span class="saw-file-status-text">Čeká na kategorii</span>
                    </div>
                `;
            }
            
            return `<span class="saw-file-status-icon">${this.getStatusIcon(fileObj.status)}</span>`;
        }
        
        /**
         * Get action buttons
         * 
         * @since 2.0.0
         * @param {object} fileObj - File object
         * @return {string} HTML
         */
        getActionButtons(fileObj) {
            let buttons = '';
            
            if (fileObj.status === 'error') {
                buttons += `<button type="button" class="saw-file-retry" data-file-id="${fileObj.id}">Zkusit znovu</button>`;
            }
            
            // View button for files with URL
            if (fileObj.status === 'success' && fileObj.metadata && fileObj.metadata.url) {
                buttons += `<button type="button" class="saw-file-view" data-file-id="${fileObj.id}" title="Zobrazit soubor">👁️</button>`;
            }
            
            buttons += `<button type="button" class="saw-file-remove" data-file-id="${fileObj.id}" title="Smazat soubor">🗑️</button>`;
            
            return buttons;
        }
        
        /**
         * Get status icon
         * 
         * @since 2.0.0
         * @param {string} status - File status
         * @return {string} Icon
         */
        getStatusIcon(status) {
            switch (status) {
                case 'pending':
                    return '⏳';
                case 'uploading':
                    return '⏳';
                case 'success':
                    return '✅';
                case 'error':
                    return '❌';
                default:
                    return '⏳';
            }
        }
        
        /**
         * Get file icon
         * 
         * @since 2.0.0
         * @param {string} type - MIME type
         * @param {string} extension - File extension
         * @return {string} Icon
         */
        getFileIcon(type, extension) {
            const ext = extension || (type ? type.split('/').pop() : '');
            
            if (ext === 'pdf' || type === 'application/pdf') {
                return '📄';
            } else if (['doc', 'docx', 'odt', 'pages', 'rtf'].includes(ext) || type?.includes('word') || type?.includes('document')) {
                return '📝';
            } else if (['xls', 'xlsx', 'ods', 'numbers'].includes(ext) || type?.includes('excel') || type?.includes('spreadsheet')) {
                return '📊';
            } else if (['ppt', 'pptx', 'odp', 'key'].includes(ext) || type?.includes('powerpoint') || type?.includes('presentation')) {
                return '📽️';
            } else if (ext === 'txt' || type === 'text/plain') {
                return '📃';
            } else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext) || type?.includes('image')) {
                return '🖼️';
            } else {
                return '📎';
            }
        }
        
        /**
         * Update status bar
         * 
         * @since 2.0.0
         * @return {void}
         */
        updateStatusBar() {
            const total = this.files.length;
            const uploaded = this.files.filter(f => f.status === 'success').length;
            const uploading = this.files.filter(f => f.status === 'uploading').length;
            const errors = this.files.filter(f => f.status === 'error').length;
            
            if (total === 0) {
                this.$statusBar.hide();
                if (this.$infoBar && this.$infoBar.length) {
                    this.lastMessage = null;
                    this.updateInfoBar();
                }
                return;
            }
            
            const percent = total > 0 ? Math.round((uploaded / total) * 100) : 0;
            
            const statusText = `${uploaded}/${total} souborů • ${percent}%`;
            
            this.$statusBar.html(`
                <div class="saw-status-text">${statusText}</div>
                <div class="saw-status-progress-bar">
                    <div class="saw-status-progress-fill" style="width: ${percent}%"></div>
                </div>
            `).show();
            
            // Update info bar if there are errors
            if (this.$infoBar && this.$infoBar.length) {
                if (errors > 0) {
                    const errorFiles = this.files.filter(f => f.status === 'error');
                    if (errorFiles.length > 0 && errorFiles[0].error) {
                        this.showInfoMessage('error', errorFiles[0].error);
                    }
                } else if (uploading > 0) {
                    this.showInfoMessage('info', 'Nahrávání souborů...');
                } else if (uploaded > 0 && uploaded === total) {
                    this.showInfoMessage('success', `Všechny soubory (${uploaded}) byly úspěšně nahrány`);
                } else {
                    // Clear info bar if no special status
                    this.lastMessage = null;
                    this.updateInfoBar();
                }
            }
        }
        
        /**
         * Remove file
         * 
         * @since 2.0.0
         * @param {string} fileId - File ID
         * @return {void}
         */
        removeFile(fileId) {
            this.files = this.files.filter(f => f.id !== fileId);
            this.renderFileList();
            this.updateStatusBar();
            
            // If max_files is 1 and we removed a file, allow new uploads
            if (this.options.maxFiles === 1 && this.files.length === 0) {
                // Reset the file input to allow re-selection
                const $input = this.$container.find('.saw-file-input');
                if ($input.length) {
                    $input.val('');
                }
            }
        }
        
        /**
         * Delete file physically from server
         * 
         * @since 2.0.0
         * @param {number|string} dbId - Database ID of the file
         * @param {string} fileId - Frontend file ID
         * @return {void}
         */
        deleteFile(dbId, fileId) {
            const self = this;
            const fileObj = this.files.find(f => f.id === fileId);
            
            if (!fileObj) {
                return;
            }
            
            // Show loading state
            fileObj.status = 'deleting';
            this.renderFileList();
            
            $.ajax({
                url: this.options.uploadUrl,
                type: 'POST',
                data: {
                    action: 'saw_delete_file',
                    nonce: sawFileUpload.nonce,
                    file_id: dbId,
                    file_path: fileObj.metadata ? fileObj.metadata.path : null,
                    context: this.options.context,
                },
                success: function(response) {
                    if (response.success) {
                        self.removeFile(fileId);
                        self.showInfoMessage('success', 'Soubor byl úspěšně smazán');
                    } else {
                        fileObj.status = 'success'; // Revert status
                        self.renderFileList();
                        self.showInfoMessage('error', response.data?.message || 'Chyba při mazání souboru');
                    }
                },
                error: function(xhr, status, error) {
                    fileObj.status = 'success'; // Revert status
                    self.renderFileList();
                    self.showInfoMessage('error', 'Chyba při mazání souboru: ' + error);
                }
            });
        }
        
        /**
         * Retry upload
         * 
         * @since 2.0.0
         * @param {string} fileId - File ID
         * @return {void}
         */
        retryUpload(fileId) {
            const fileObj = this.files.find(f => f.id === fileId);
            if (fileObj) {
                this.uploadFile(fileObj);
            }
        }
        
        /**
         * Show validation error
         * 
         * @since 2.0.0
         * @param {string} message - Error message
         * @return {void}
         */
        showValidationError(message) {
            this.$validationError
                .html(`<span class="saw-validation-icon">⚠️</span> ${message}`)
                .show();
        }
        
        /**
         * Hide validation error
         * 
         * @since 2.0.0
         * @return {void}
         */
        hideValidationError() {
            this.$validationError.hide();
        }
        
        /**
         * Show info message in info bar
         * 
         * @since 2.0.0
         * @param {string} type - Message type (success, error, warning, info)
         * @param {string} message - Message text
         * @return {void}
         */
        showInfoMessage(type, message) {
            this.lastMessage = { type, message };
            this.updateInfoBar();
        }
        
        /**
         * Update info bar with last message
         * 
         * @since 2.0.0
         * @return {void}
         */
        updateInfoBar() {
            if (!this.$infoBar || !this.$infoBar.length) {
                return; // Info bar not initialized yet
            }
            
            if (!this.lastMessage) {
                this.$infoBar.hide();
                return;
            }
            
            const iconMap = {
                success: '✓',
                error: '×',
                warning: '!',
                info: 'i'
            };
            
            const icon = iconMap[this.lastMessage.type] || '•';
            const typeClass = 'saw-info-' + this.lastMessage.type;
            
            this.$infoBar
                .removeClass('saw-info-success saw-info-error saw-info-warning saw-info-info')
                .addClass(typeClass)
                .html(`
                    <span class="saw-info-icon">${icon}</span>
                    <span class="saw-info-text">${this.escapeHtml(this.lastMessage.message)}</span>
                `)
                .show();
            
            // Auto-hide success messages after 3 seconds
            if (this.lastMessage.type === 'success') {
                setTimeout(() => {
                    if (this.lastMessage && this.lastMessage.type === 'success') {
                        this.lastMessage = null;
                        this.updateInfoBar();
                    }
                }, 3000);
            }
        }
        
        /**
         * Format file size
         * 
         * @since 2.0.0
         * @param {number} bytes - File size in bytes
         * @return {string} Formatted size
         */
        formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
        
        /**
         * Escape HTML
         * 
         * @since 2.0.0
         * @param {string} text - Text to escape
         * @return {string} Escaped text
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        /**
         * Get uploaded files metadata
         * 
         * Returns array of file metadata for form submission.
         * 
         * @since 2.0.0
         * @return {Array} Array of file metadata
         */
        getUploadedFiles() {
            return this.files
                .filter(f => f.status === 'success' && f.metadata && !f.isExisting) // Only newly uploaded files
                .map(f => {
                    const metadata = Object.assign({}, f.metadata);
                    // Add category if available
                    if (f.category) {
                        metadata.category = f.category;
                    }
                    return metadata;
                });
        }
        
        /**
         * Get selected category value
         * 
         * @since 2.0.0
         * @return {string|array|null} Category ID(s) or null
         */
        getSelectedCategory() {
            if (!this.$categorySelect || !this.$categorySelect.length) {
                return null;
            }
            
            if (this.$categorySelect.is('select[multiple]')) {
                return this.$categorySelect.val() || []; // Return array for multiple
            } else {
                return this.$categorySelect.val() || null; // Return single value
            }
        }
        
        /**
         * Get category name by ID
         * 
         * @since 2.0.0
         * @param {string|number} categoryId - Category ID
         * @return {string} Category name or empty string
         */
        getCategoryName(categoryId) {
            if (!this.options.categoryConfig || !this.options.categoryConfig.options) {
                return '';
            }
            
            const category = this.options.categoryConfig.options.find(opt => opt.id == categoryId);
            return category ? category.name : '';
        }
    }
    
    // Expose to global scope
    window.SAWModernFileUpload = SAWModernFileUpload;
    
    /**
     * Initialize all file upload components
     * 
     * @since 2.0.0
     */
    $(document).ready(function() {
        $('.saw-file-upload-modern-container').each(function() {
            const $container = $(this);
            const options = $container.data('options') || {};
            
            // Store instance for later access
            $container.data('saw-file-upload-instance', new SAWModernFileUpload($container, options));
        });
    });
    
    // Re-initialize after AJAX page loads
    $(document).on('saw:page-loaded saw:module-reinit', function() {
        $('.saw-file-upload-modern-container').each(function() {
            const $container = $(this);
            
            // Skip if already initialized
            if ($container.data('saw-file-upload-instance')) {
                return;
            }
            
            const options = $container.data('options') || {};
            $container.data('saw-file-upload-instance', new SAWModernFileUpload($container, options));
        });
    });
    
})(jQuery);
