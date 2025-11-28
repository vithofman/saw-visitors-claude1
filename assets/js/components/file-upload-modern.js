/**
 * Modern File Upload Component - Version 3.0.1
 * 
 * FIX: Better error handling pro SyntaxError
 * FIX: Response validation
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/FileUpload
 * @version     3.0.1
 * @since       3.0.0
 */

(function($) {
    'use strict';
    
    /**
     * SVG Icons Library
     * Professional SVG icons replacing emoji for better consistency
     */
    const SAWIcons = {
        // Upload icon
        upload: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
        </svg>`,
        
        // Info icon
        info: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>`,
        
        // File type icons
        document: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
        </svg>`,
        
        pdf: `<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 2l5 5h-5V4zM8 18v-1h2v1H8zm0-3v-1h8v1H8zm0-3v-1h8v1H8z"/>
        </svg>`,
        
        image: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>`,
        
        excel: `<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 2l5 5h-5V4zm-3 10l-2 3h2l2-3-2-3H8l2 3z"/>
        </svg>`,
        
        word: `<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 2l5 5h-5V4zM8 12h8v2H8v-2zm0 4h8v2H8v-2z"/>
        </svg>`,
        
        // Action icons
        trash: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
        </svg>`,
        
        retry: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>`,
        
        close: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>`,
        
        // Status icons
        check: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>`,
        
        exclamation: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>`,
        
        spinner: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity="0.25"/>
            <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" opacity="0.75"/>
        </svg>`
    };
    
    /**
     * Get file icon by filename
     * 
     * @param {string} filename - File name
     * @return {string} SVG icon HTML
     */
    function getFileIcon(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        
        // Images
        if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'].includes(ext)) {
            return SAWIcons.image;
        }
        
        // PDFs
        if (ext === 'pdf') {
            return SAWIcons.pdf;
        }
        
        // Word documents
        if (['doc', 'docx', 'odt', 'pages', 'rtf'].includes(ext)) {
            return SAWIcons.word;
        }
        
        // Excel spreadsheets
        if (['xls', 'xlsx', 'ods', 'numbers', 'csv'].includes(ext)) {
            return SAWIcons.excel;
        }
        
        // Default document icon
        return SAWIcons.document;
    }
    
    /**
     * SAW Modern File Upload Class
     * 
     * Manages file upload interactions including validation, preview,
     * progress tracking, and drag-and-drop support.
     * 
     * @class
     * @since 3.0.1
     */
    class SAWModernFileUpload {
        /**
         * Constructor
         * 
         * @since 3.0.1
         * @param {jQuery} $container - The component container element
         * @param {object} options - Configuration options
         */
        constructor($container, options = {}) {
            this.$container = $container;
            this.options = $.extend({
                multiple: false,
                accept: '',
                maxSize: 0,
                maxFiles: 0,
                uploadUrl: sawFileUpload.ajaxurl,
                context: 'documents',
                name: '',
                id: '',
                existingFiles: [],
                categoryConfig: {},
            }, options);
            
            this.files = [];
            this.uploading = false;
            this.uploadQueue = [];
            this.lastMessage = null;
            
            this.init();
        }
        
        /**
         * Initialize component
         * 
         * @since 3.0.1
         * @return {void}
         */
        init() {
            // Load existing files
            if (this.options.existingFiles && Array.isArray(this.options.existingFiles)) {
                this.options.existingFiles.forEach(fileData => {
                    const fileObj = {
                        id: 'existing_' + (fileData.id || Date.now() + Math.random()),
                        file: null,
                        name: fileData.name || (fileData.path ? fileData.path.split('/').pop() : 'Unknown'),
                        size: fileData.size || 0,
                        type: fileData.type || 'application/octet-stream',
                        status: 'success',
                        progress: 100,
                        error: null,
                        metadata: fileData,
                        isExisting: true,
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
         * @since 3.0.1
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
            
            // Category select change
            this.$container.on('change', '.saw-category-select-input', function() {
                const waitingFiles = self.files.filter(f => f.status === 'waiting_category');
                if (waitingFiles.length > 0) {
                    const $select = $(this);
                    const categoryValue = $select.is('select[multiple]') 
                        ? $select.val() || []
                        : $select.val() || null;
                    
                    if (categoryValue && (Array.isArray(categoryValue) ? categoryValue.length > 0 : true)) {
                        waitingFiles.forEach(fileObj => {
                            fileObj.status = 'uploading';
                            fileObj.category = {
                                id: Array.isArray(categoryValue) ? categoryValue : categoryValue,
                                name: self.getCategoryName(Array.isArray(categoryValue) ? categoryValue[0] : categoryValue)
                            };
                            self.renderFileItem(fileObj);
                            self.uploadFile(fileObj);
                        });
                    }
                }
            });
            
            // Remove file
            this.$container.on('click', '.saw-file-remove', function(e) {
                e.preventDefault();
                const fileId = $(this).data('file-id');
                self.removeFile(fileId);
            });
            
            // Retry upload
            this.$container.on('click', '.saw-file-retry', function(e) {
                e.preventDefault();
                const fileId = $(this).data('file-id');
                self.retryUpload(fileId);
            });
            
            // Keyboard support
            this.$container.on('keydown', '.saw-upload-zone', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).find('.saw-file-input').trigger('click');
                }
            });
            
            this.$container.on('keydown', '.saw-file-remove, .saw-file-retry', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).trigger('click');
                }
            });
        }
        
        /**
         * Render component HTML
         * 
         * @since 3.0.1
         * @return {void}
         */
        render() {
            const multiple = this.options.multiple ? 'multiple' : '';
            const accept = this.options.accept ? `accept="${this.options.accept}"` : '';
            const inputId = this.options.id || `saw-upload-${Date.now()}`;
            
            // Category select HTML
            let categorySelectHtml = '';
            if (this.options.categoryConfig && this.options.categoryConfig.enabled) {
                const config = this.options.categoryConfig;
                const categoryLabel = config.label || 'Kategorie';
                const categoryOptions = config.options || [];
                const isMultiple = config.multiple ? 'multiple' : '';
                
                if (categoryOptions.length > 0) {
                    categorySelectHtml = `
                        <div class="saw-upload-category-row">
                            <label class="saw-upload-category-label">${this.escapeHtml(categoryLabel)}</label>
                            <div class="saw-category-select-wrapper">
                                <select 
                                    class="saw-category-select-input" 
                                    ${isMultiple}                                    
                                    aria-label="${this.escapeHtml(categoryLabel)}"
                                >
                                    <option value="">-- Vyberte ${categoryLabel.toLowerCase()} --</option>
                                    ${categoryOptions.map(opt => `
                                        <option value="${opt.id}">${this.escapeHtml(opt.name)}</option>
                                    `).join('')}
                                </select>
                            </div>
                        </div>
                    `;
                }
            }
            
            const html = `
                <div class="saw-file-upload-modern" role="region" aria-label="Nahrávání souborů">
                    <div class="saw-upload-zone" role="button" tabindex="0" aria-label="Oblast pro přetažení souborů">
                        <input 
                            type="file" 
                            id="${inputId}"
                            name="${this.options.name}"
                            class="saw-file-input"
                            ${multiple}
                            ${accept}
                            aria-label="Vybrat soubory k nahrání"
                            style="display: none;"
                        >
                        <div class="saw-upload-zone-content">
                            <div class="saw-upload-zone-row saw-upload-zone-row-main">
                                <div class="saw-upload-icon" aria-hidden="true">${SAWIcons.upload}</div>
                                <span class="saw-upload-text">${sawFileUpload.strings.drag_drop}</span>
                                <label for="${inputId}" class="saw-upload-button">
                                    ${sawFileUpload.strings.select_files}
                                </label>
                                <span class="saw-upload-info" title="Povolené formáty: ${this.options.accept || 'Všechny'}" aria-label="Informace o povolených formátech">${SAWIcons.info}</span>
                            </div>
                            ${categorySelectHtml}
                        </div>
                    </div>
                    <div class="saw-file-list" role="list" aria-label="Seznam nahraných souborů"></div>
                    <div class="saw-status-bar" style="display: none;" role="status" aria-live="polite"></div>
                    <div class="saw-info-bar" style="display: none;" role="status" aria-live="polite"></div>
                </div>
            `;
            
            this.$container.html(html);
            this.$categorySelect = this.$container.find('.saw-category-select-input');
            this.renderFileList();
        }
        
        /**
         * Render file list
         * 
         * @since 3.0.1
         * @return {void}
         */
        renderFileList() {
            const $list = this.$container.find('.saw-file-list');
            $list.empty();
            
            this.files.forEach(fileObj => {
                this.renderFileItem(fileObj);
            });
        }
        
        /**
         * Render single file item
         * 
         * @since 3.0.1
         * @param {Object} fileObj - File object
         * @return {void}
         */
        renderFileItem(fileObj) {
            const $list = this.$container.find('.saw-file-list');
            
            let $item = $list.find(`[data-file-id="${fileObj.id}"]`);
            if (!$item.length) {
                $item = $('<div class="saw-file-item" role="listitem"></div>');
                $item.attr('data-file-id', fileObj.id);
                $list.append($item);
            }
            
            const fileIcon = getFileIcon(fileObj.name);
            
            // Status
            let statusClass = '';
            let statusIcon = '';
            let statusText = '';
            
            if (fileObj.status === 'uploading') {
                statusClass = 'uploading';
                statusIcon = SAWIcons.spinner;
                statusText = 'Nahrávání...';
            } else if (fileObj.status === 'success') {
                statusClass = 'success';
                statusIcon = SAWIcons.check;
                statusText = 'Nahráno';
            } else if (fileObj.status === 'error') {
                statusClass = 'error';
                statusIcon = SAWIcons.exclamation;
                statusText = fileObj.error || 'Chyba';
            } else if (fileObj.status === 'waiting_category') {
                statusClass = 'uploading';
                statusIcon = SAWIcons.info;
                statusText = 'Čeká na kategorii';
            }
            
            // Category badge
            let categoryHtml = '';
            if (fileObj.category && fileObj.category.name) {
                categoryHtml = `<span class="saw-file-category">${this.escapeHtml(fileObj.category.name)}</span>`;
            }
            
            const fileSize = this.formatFileSize(fileObj.size);
            
            // Actions
            let actionsHtml = '';
            if (fileObj.status === 'success' || fileObj.status === 'error') {
                actionsHtml = `
                    <div class="saw-file-actions">
                        ${fileObj.status === 'error' ? `
                            <button 
                                type="button" 
                                class="saw-file-retry" 
                                data-file-id="${fileObj.id}" 
                                title="Zkusit znovu"
                                aria-label="Zkusit znovu nahrát ${this.escapeHtml(fileObj.name)}"
                            >
                                ${SAWIcons.retry}
                            </button>
                        ` : ''}
                        <button 
                            type="button" 
                            class="saw-file-remove" 
                            data-file-id="${fileObj.id}" 
                            title="Odstranit"
                            aria-label="Odstranit soubor ${this.escapeHtml(fileObj.name)}"
                        >
                            ${SAWIcons.trash}
                        </button>
                    </div>
                `;
            }
            
            // Progress bar
            let progressHtml = '';
            if (fileObj.status === 'uploading' && fileObj.progress !== undefined) {
                progressHtml = `
                    <div class="saw-file-progress" role="progressbar" aria-valuenow="${fileObj.progress}" aria-valuemin="0" aria-valuemax="100">
                        <div class="saw-file-progress-bar" style="width: ${fileObj.progress}%"></div>
                    </div>
                `;
            }
            
            const html = `
                <div class="saw-file-icon-container" aria-hidden="true">${fileIcon}</div>
                <div class="saw-file-info">
                    <div class="saw-file-name" title="${this.escapeHtml(fileObj.name)}">${this.escapeHtml(fileObj.name)}</div>
                    <div class="saw-file-meta">
                        <span class="saw-file-size">${fileSize}</span>
                        ${categoryHtml}
                    </div>
                    ${progressHtml}
                </div>
                ${statusIcon ? `
                    <div class="saw-file-status ${statusClass}">
                        ${statusIcon}
                        <span>${statusText}</span>
                    </div>
                ` : ''}
                ${actionsHtml}
            `;
            
            $item.html(html);
            $item.removeClass('uploading success error').addClass(statusClass);
            $item.attr('aria-label', `${fileObj.name}, ${statusText}`);
        }
        
        /**
         * Handle file selection
         * 
         * @since 3.0.1
         * @param {Array} files - Array of File objects
         * @return {void}
         */
        handleFileSelect(files) {
            if (!files || files.length === 0) {
                return;
            }
            
            // Check max files limit
            if (this.options.maxFiles > 0) {
                const currentCount = this.files.filter(f => !f.isExisting).length;
                const allowed = this.options.maxFiles - currentCount;
                
                if (allowed <= 0) {
                    this.showToast(`Maximální počet souborů (${this.options.maxFiles}) byl dosažen`, 'error');
                    return;
                }
                
                if (files.length > allowed) {
                    this.showToast(`Můžete nahrát pouze ${allowed} souborů`, 'error');
                    files = files.slice(0, allowed);
                }
            }
            
            // For single file mode, remove existing file
            if (this.options.maxFiles === 1 && this.files.length > 0) {
                const oldFile = this.files[0];
                if (oldFile.status === 'success' && oldFile.metadata) {
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
         * @since 3.0.1
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
                    } else if (type.includes('/*')) {
                        const baseType = type.split('/')[0];
                        return file.type.startsWith(baseType + '/');
                    } else {
                        return file.type === type;
                    }
                });
                
                if (!isAllowed) {
                    return {
                        valid: false,
                        error: `Soubor "${file.name}" není podporovaný typ`
                    };
                }
            }
            
            return { valid: true };
        }
        
        /**
         * Add file to upload
         * 
         * @since 3.0.1
         * @param {File} file - File object
         * @return {void}
         */
        addFile(file) {
            const fileObj = {
                id: 'file_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
                file: file,
                name: file.name,
                size: file.size,
                type: file.type,
                status: 'uploading',
                progress: 0,
                error: null,
                metadata: null,
                category: null
            };
            
            this.files.push(fileObj);
            this.renderFileItem(fileObj);
            
            // Check if category is required
            if (this.options.categoryConfig && this.options.categoryConfig.enabled && this.options.categoryConfig.required) {
                const categoryValue = this.getSelectedCategory();
                if (!categoryValue || (Array.isArray(categoryValue) && categoryValue.length === 0)) {
                    fileObj.status = 'waiting_category';
                    this.renderFileItem(fileObj);
                    this.showInfo('Vyberte kategorii pro pokračování nahrávání');
                    return;
                }
            }
            
            // Get category if selected
            if (this.$categorySelect && this.$categorySelect.length) {
                const categoryValue = this.getSelectedCategory();
                if (categoryValue) {
                    fileObj.category = {
                        id: Array.isArray(categoryValue) ? categoryValue : categoryValue,
                        name: this.getCategoryName(Array.isArray(categoryValue) ? categoryValue[0] : categoryValue)
                    };
                }
            }
            
            this.uploadFile(fileObj);
        }
        
        /**
         * Upload file via AJAX - FIX: Better error handling
         * 
         * @since 3.0.1
         * @param {object} fileObj - File object
         * @return {void}
         */
        uploadFile(fileObj) {
            const formData = new FormData();
            formData.append('file', fileObj.file);
            formData.append('action', 'saw_upload_file');
            formData.append('nonce', sawFileUpload.nonce);
            formData.append('context', this.options.context);
            
            if (this.options.maxSize > 0) {
                formData.append('max_size', this.options.maxSize);
            }
            
            if (this.options.accept) {
                formData.append('accept', this.options.accept);
            }
            
            if (fileObj.category) {
                formData.append('category', JSON.stringify(fileObj.category));
            }
            
            const self = this;
            
            $.ajax({
                url: this.options.uploadUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json', // FIX: Explicitly expect JSON
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            const percent = Math.round((e.loaded / e.total) * 100);
                            fileObj.progress = percent;
                            self.renderFileItem(fileObj);
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    if (response && response.success) {
                        fileObj.status = 'success';
                        fileObj.progress = 100;
                        fileObj.metadata = response.data.file;
                        self.renderFileItem(fileObj);
                        self.showToast(sawFileUpload.strings.success, 'success');
                    } else {
                        fileObj.status = 'error';
                        fileObj.error = (response && response.data && response.data.message) || sawFileUpload.strings.error;
                        self.renderFileItem(fileObj);
                        // self.showToast(fileObj.error, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    // FIX: Better error message
                    let errorMessage = 'Chyba serveru';
                    
                    if (xhr.responseText) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            errorMessage = response.data?.message || errorMessage;
                        } catch (e) {
                            // Response is not JSON - server error
                            errorMessage = 'Server vrátil neplatnou odpověď. Zkontrolujte PHP error log.';
                            console.error('Server response:', xhr.responseText.substring(0, 500));
                        }
                    }
                    
                    fileObj.status = 'error';
                    fileObj.error = errorMessage;
                    self.renderFileItem(fileObj);
                    // self.showToast(errorMessage, 'error');
                }
            });
        }
        
        /**
         * Remove file
         * 
         * @since 3.0.1
         * @param {string} fileId - File ID
         * @return {void}
         */
        removeFile(fileId) {
            const fileObj = this.files.find(f => f.id === fileId);
            if (!fileObj) return;
            
            if (fileObj.metadata && fileObj.metadata.url) {
                this.deleteFile(fileObj.metadata.id, fileId);
            } else {
                this.files = this.files.filter(f => f.id !== fileId);
                this.$container.find(`[data-file-id="${fileId}"]`).remove();
            }
        }
        
        /**
         * Delete file from server
         * 
         * @since 3.0.1
         * @param {number} dbId - Database ID
         * @param {string} fileId - Local file ID
         * @return {void}
         */
        deleteFile(dbId, fileId) {
            const fileObj = this.files.find(f => f.id === fileId);
            if (!fileObj) return;
            
            const formData = new FormData();
            formData.append('action', 'saw_delete_file');
            formData.append('nonce', sawFileUpload.nonce);
            formData.append('file_url', fileObj.metadata.url);
            formData.append('file_path', fileObj.metadata.path);
            formData.append('file_id', dbId);
            formData.append('context', this.options.context);
            
            const self = this;
            
            $.ajax({
                url: this.options.uploadUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        self.files = self.files.filter(f => f.id !== fileId);
                        self.$container.find(`[data-file-id="${fileId}"]`).remove();
                        self.showToast('Soubor byl smazán', 'success');
                    } else {
                        self.showToast(response.data.message || 'Chyba při mazání', 'error');
                    }
                },
                error: function() {
                    self.showToast('Chyba serveru při mazání', 'error');
                }
            });
        }
        
        /**
         * Retry upload
         * 
         * @since 3.0.1
         * @param {string} fileId - File ID
         * @return {void}
         */
        retryUpload(fileId) {
            const fileObj = this.files.find(f => f.id === fileId);
            if (!fileObj || !fileObj.file) return;
            
            fileObj.status = 'uploading';
            fileObj.progress = 0;
            fileObj.error = null;
            this.renderFileItem(fileObj);
            this.uploadFile(fileObj);
        }
        
        /**
 * Show toast notification
 * 
 * @param {string} message - Message text
 * @param {string} type - Type: 'success', 'error', 'info'
 * @param {number} duration - Duration in ms
 * @return {void}
 */
showToast(message, type = 'info', duration = 5000) { // ← ZMĚŇ z 3000 na 5000
    let $container = $('.saw-toast-container');
    if (!$container.length) {
        $container = $('<div class="saw-toast-container" aria-live="polite" aria-atomic="true"></div>');
        $('body').append($container);
    }
    
    let icon = SAWIcons.info;
    let title = 'Informace';
    
    if (type === 'success') {
        icon = SAWIcons.check;
        title = 'Úspěch';
    } else if (type === 'error') {
        icon = SAWIcons.exclamation;
        title = 'Chyba';
    }
    
    const toastId = 'toast-' + Date.now();
    
    // ZKONTROLUJ jestli stejná zpráva už není zobrazená
    const existingToast = $container.find('.saw-toast-message:contains("' + message + '")');
    if (existingToast.length > 0) {
        return; // ← ZABRAŇ DUPLICITÁM
    }
    
    const $toast = $(`
        <div class="saw-toast ${type}" id="${toastId}" role="alert">
            <div class="saw-toast-icon" aria-hidden="true">${icon}</div>
            <div class="saw-toast-content">
                <div class="saw-toast-title">${title}</div>
                <div class="saw-toast-message">${this.escapeHtml(message)}</div>
            </div>
            <button type="button" class="saw-toast-close" aria-label="Zavřít notifikaci">
                ${SAWIcons.close}
            </button>
        </div>
    `);
    
    $container.append($toast);
    
    $toast.find('.saw-toast-close').on('click', function() {
        removeToast($toast);
    });
    
    if (duration > 0) {
        setTimeout(() => {
            removeToast($toast);
        }, duration);
    }
    
    function removeToast($el) {
        $el.addClass('removing');
        setTimeout(() => {
            $el.remove();
            if ($container.find('.saw-toast').length === 0) {
                $container.remove();
            }
        }, 300);
    }
}
        
        /**
         * Show validation error
         * 
         * @since 3.0.1
         * @param {string} message - Error message
         * @return {void}
         */
        showValidationError(message) {
            this.hideValidationError();
            
            const $error = $(`
                <div class="saw-validation-error">
                    <span class="saw-validation-icon">${SAWIcons.exclamation}</span>
                    <span>${message}</span>
                </div>
            `);
            
            this.$container.find('.saw-upload-zone').after($error);
        }
        
        /**
         * Hide validation error
         * 
         * @since 3.0.1
         * @return {void}
         */
        hideValidationError() {
            this.$container.find('.saw-validation-error').remove();
        }
        
        /**
         * Show info message
         * 
         * @since 3.0.1
         * @param {string} message - Info message
         * @return {void}
         */
        showInfo(message) {
            const $infoBar = this.$container.find('.saw-info-bar');
            $infoBar.html(`
                ${SAWIcons.info}
                <span>${this.escapeHtml(message)}</span>
            `).show();
            this.lastMessage = message;
        }
        
        /**
         * Hide info message
         * 
         * @since 3.0.1
         * @return {void}
         */
        hideInfo() {
            this.$container.find('.saw-info-bar').hide();
            this.lastMessage = null;
        }
        
        /**
         * Get uploaded files
         * 
         * @since 3.0.1
         * @return {Array} Array of file metadata
         */
        getUploadedFiles() {
            return this.files
                .filter(f => f.status === 'success' && f.metadata && !f.isExisting)
                .map(f => {
                    const metadata = Object.assign({}, f.metadata);
                    if (f.category) {
                        metadata.category = f.category;
                    }
                    return metadata;
                });
        }
        
        /**
         * Get selected category value
         * 
         * @since 3.0.1
         * @return {string|array|null} Category ID(s) or null
         */
        getSelectedCategory() {
            if (!this.$categorySelect || !this.$categorySelect.length) {
                return null;
            }
            
            if (this.$categorySelect.is('select[multiple]')) {
                return this.$categorySelect.val() || [];
            } else {
                return this.$categorySelect.val() || null;
            }
        }
        
        /**
         * Get category name by ID
         * 
         * @since 3.0.1
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
        
        /**
         * Format file size
         * 
         * @since 3.0.1
         * @param {number} bytes - File size in bytes
         * @return {string} Formatted size
         */
        formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
        }
        
        /**
         * Escape HTML
         * 
         * @since 3.0.1
         * @param {string} text - Text to escape
         * @return {string} Escaped text
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }
    
    // Expose to global scope
    window.SAWModernFileUpload = SAWModernFileUpload;
    
    /**
     * Initialize all file upload components
     * 
     * @since 3.0.1
     */
    $(document).ready(function() {
        $('.saw-file-upload-modern-container').each(function() {
            const $container = $(this);
            const options = $container.data('options') || {};
            
            $container.data('saw-file-upload-instance', new SAWModernFileUpload($container, options));
        });
    });
    
    // Re-initialize after AJAX page loads
    $(document).on('saw:page-loaded saw:module-reinit', function() {
        $('.saw-file-upload-modern-container').each(function() {
            const $container = $(this);
            
            if ($container.data('saw-file-upload-instance')) {
                return;
            }
            
            const options = $container.data('options') || {};
            $container.data('saw-file-upload-instance', new SAWModernFileUpload($container, options));
        });
    });
    
})(jQuery);