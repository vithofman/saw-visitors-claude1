/**
 * SAW Forms Components - Consolidated
 * 
 * All form-related JavaScript components including:
 * - Selectbox (custom select with search and AJAX)
 * - Select-Create (inline create in select dropdowns)
 * - Color Picker (color input with preview)
 * - File Upload (file selection with drag-and-drop)
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

(function ($) {
    'use strict';

    /* ============================================
       SELECTBOX COMPONENT
       ============================================ */

    /**
     * SAW Selectbox Component Class
     * 
     * Manages selectbox interactions including dropdown toggle, option selection,
     * search filtering, and AJAX option loading.
     * 
     * @class
     * @since 4.6.1
     */
    class SAWSelectboxComponent {
        /**
         * Constructor
         * 
         * Initializes the selectbox component with DOM elements and configuration.
         * 
         * @since 4.6.1
         * @param {jQuery} $container - Selectbox container element
         */
        constructor($container) {
            this.$container = $container;
            this.$trigger = $container.find('.saw-selectbox-trigger');
            this.$dropdown = $container.find('.saw-selectbox-dropdown');
            this.$options = $container.find('.saw-selectbox-options');
            this.$search = $container.find('.saw-selectbox-search-input');
            this.$valueInput = $container.find('.saw-selectbox-value');

            this.id = $container.data('id');
            this.ajaxEnabled = $container.data('ajax-enabled') === 1;
            this.ajaxAction = $container.data('ajax-action');
            this.searchable = $container.data('searchable') === 1;
            this.onChange = $container.data('on-change');
            this.showIcons = $container.data('show-icons') === 1;

            this.searchTimeout = null;
            this.isLoaded = false;

            this.init();
        }

        /**
         * Initialize component
         * 
         * Sets up event bindings and loads options if AJAX enabled.
         * 
         * @since 4.6.1
         * @return {void}
         */
        init() {
            this.bindEvents();

            if (this.ajaxEnabled && !this.isLoaded) {
                this.loadOptions();
            }
        }

        /**
         * Bind event handlers
         * 
         * Attaches event listeners for trigger, options, search, and keyboard interactions.
         * 
         * @since 4.6.1
         * @return {void}
         */
        bindEvents() {
            this.$trigger.on('click', (e) => {
                e.stopPropagation();
                this.toggle();
            });

            this.$options.on('click', '.saw-selectbox-option', (e) => {
                const $option = $(e.currentTarget);
                this.selectOption($option);
            });

            if (this.searchable) {
                this.$search.on('input', (e) => {
                    this.handleSearch(e.target.value);
                });

                this.$search.on('keydown', (e) => {
                    if (e.key === 'Escape') {
                        this.close();
                    }
                });
            }

            $(document).on('click', (e) => {
                if (!this.$container.is(e.target) && this.$container.has(e.target).length === 0) {
                    this.close();
                }
            });

            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && this.$container.hasClass('open')) {
                    this.close();
                }
            });
        }

        /**
         * Toggle dropdown
         * 
         * Opens dropdown if closed, closes if open.
         * 
         * @since 4.6.1
         * @return {void}
         */
        toggle() {
            if (this.$container.hasClass('open')) {
                this.close();
            } else {
                this.open();
            }
        }

        /**
         * Open dropdown
         * 
         * Displays the dropdown and focuses search input if searchable.
         * 
         * @since 4.6.1
         * @return {void}
         */
        open() {
            this.$container.addClass('open');

            if (this.searchable) {
                setTimeout(() => {
                    this.$search.focus();
                }, 50);
            }

            if (this.ajaxEnabled && !this.isLoaded) {
                this.loadOptions();
            }
        }

        /**
         * Close dropdown
         * 
         * Hides the dropdown and clears search input.
         * 
         * @since 4.6.1
         * @return {void}
         */
        close() {
            this.$container.removeClass('open');

            if (this.searchable) {
                this.$search.val('');
                this.handleSearch('');
            }
        }

        /**
         * Select option
         * 
         * Updates trigger text, hidden input value, and triggers change handler.
         * 
         * @since 4.6.1
         * @param {jQuery} $option - Selected option element
         * @return {void}
         */
        selectOption($option) {
            const value = $option.data('value');
            const label = $option.find('.saw-selectbox-option-label').text();

            this.$options.find('.saw-selectbox-option').removeClass('active').find('.saw-selectbox-option-check').remove();

            $option.addClass('active').prepend('<span class="saw-selectbox-option-check">✓</span>');

            this.$trigger.find('.saw-selectbox-trigger-text').text(label);

            this.$valueInput.val(value).trigger('change');

            this.close();

            this.handleChange(value);
        }

        /**
         * Handle change event
         * 
         * Triggers custom event and handles redirect or callback if configured.
         * 
         * @since 4.6.1
         * @param {string|number} value - Selected value
         * @return {void}
         */
        handleChange(value) {
            $(document).trigger('saw:selectbox:change', {
                id: this.id,
                value: value
            });

            if (this.onChange === 'redirect') {
                const url = new URL(window.location.href);
                const inputName = this.$valueInput.attr('name');

                if (value) {
                    url.searchParams.set(inputName, value);
                } else {
                    url.searchParams.delete(inputName);
                }

                url.searchParams.delete('paged');
                window.location.href = url.toString();
            } else if (this.onChange && typeof window[this.onChange] === 'function') {
                window[this.onChange](value);
            }
        }

        /**
         * Handle search input
         * 
         * Filters options based on search query.
         * 
         * @since 4.6.1
         * @param {string} query - Search query string
         * @return {void}
         */
        handleSearch(query) {
            query = query.toLowerCase().trim();

            this.$options.find('.saw-selectbox-option').each(function () {
                const $option = $(this);
                const label = $option.find('.saw-selectbox-option-label').text().toLowerCase();
                const meta = $option.find('.saw-selectbox-option-meta').text().toLowerCase();
                const searchText = label + ' ' + meta;

                if (query === '' || searchText.includes(query)) {
                    $option.removeClass('hidden');
                } else {
                    $option.addClass('hidden');
                }
            });

            const hasVisibleOptions = this.$options.find('.saw-selectbox-option:not(.hidden)').length > 0;

            if (!hasVisibleOptions && query !== '') {
                if (this.$options.find('.saw-selectbox-empty').length === 0) {
                    this.$options.append('<div class="saw-selectbox-empty">Nic nenalezeno</div>');
                }
            } else {
                this.$options.find('.saw-selectbox-empty').remove();
            }
        }

        /**
         * Load options via AJAX
         * 
         * Fetches options from server and renders them.
         * 
         * @since 4.6.1
         * @param {string} query - Optional search query
         * @return {void}
         */
        loadOptions(query = '') {
            if (!this.ajaxEnabled || !this.ajaxAction) {
                return;
            }

            this.$options.html('<div class="saw-selectbox-loading"><div class="spinner is-active"></div><div>Načítám...</div></div>');

            const ajaxurl = (typeof sawGlobal !== 'undefined' && sawGlobal.ajaxurl) ? sawGlobal.ajaxurl : '/wp-admin/admin-ajax.php';

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: this.ajaxAction,
                    s: query
                },
                success: (response) => {
                    if (response.success && response.data) {
                        this.renderOptions(response.data);
                        this.isLoaded = true;
                    } else {
                        this.$options.html('<div class="saw-selectbox-empty">Chyba načítání</div>');
                    }
                },
                error: (xhr, status, error) => {
                    this.$options.html('<div class="saw-selectbox-empty">Chyba serveru (' + xhr.status + ')</div>');
                }
            });
        }

        /**
         * Render options HTML
         * 
         * Generates HTML for options list from data array.
         * 
         * @since 4.6.1
         * @param {Array} options - Array of option objects
         * @return {void}
         */
        renderOptions(options) {
            const currentValue = this.$valueInput.val();
            let html = '';

            if (Array.isArray(options)) {
                options.forEach(option => {
                    const value = option.value || '';
                    const label = option.label || '';
                    const icon = option.icon || '';
                    const meta = option.meta || '';
                    const isActive = (currentValue == value);

                    html += '<div class="saw-selectbox-option ' + (isActive ? 'active' : '') + '" data-value="' + this.escapeHtml(value) + '"';
                    if (icon && this.showIcons) {
                        html += ' data-icon="' + this.escapeHtml(icon) + '"';
                    }
                    html += '>';

                    if (isActive) {
                        html += '<span class="saw-selectbox-option-check">✓</span>';
                    }

                    if (icon && this.showIcons) {
                        html += '<img src="' + this.escapeHtml(icon) + '" alt="' + this.escapeHtml(label) + '" class="saw-selectbox-option-icon">';
                    }

                    html += '<div class="saw-selectbox-option-content">';
                    html += '<div class="saw-selectbox-option-label">' + this.escapeHtml(label) + '</div>';
                    if (meta) {
                        html += '<div class="saw-selectbox-option-meta">' + this.escapeHtml(meta) + '</div>';
                    }
                    html += '</div>';
                    html += '</div>';
                });
            }

            if (html === '') {
                html = '<div class="saw-selectbox-empty">Žádné možnosti</div>';
            }

            this.$options.html(html);
        }

        /**
         * Escape HTML entities
         * 
         * Prevents XSS by escaping special characters.
         * 
         * @since 4.6.1
         * @param {string} text - Text to escape
         * @return {string} Escaped text
         */
        escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }
    }

    /* ============================================
       SELECT-CREATE COMPONENT
       ============================================ */

    /**
     * SAW Select-Create Component Class
     * 
     * Handles inline create functionality in select dropdowns.
     * Handles nested sidebar loading, z-index management, and dropdown updates.
     * 
     * @class
     * @since 13.0.0
     */
    class SAWSelectCreateComponent {

        constructor($button) {
            this.$button = $button;
            this.field = $button.data('field');
            this.module = $button.data('module');
            this.prefill = $button.data('prefill') || {};

            this.init();
        }

        init() {
            this.$button.on('click', (e) => {
                e.preventDefault();
                this.openNestedSidebar();
            });
        }

        openNestedSidebar() {
            this.$button.addClass('loading').prop('disabled', true);

            const zIndex = this.calculateZIndex();
            const self = this;

            $.ajax({
                url: sawGlobal.ajaxurl,
                type: 'POST',
                data: {
                    action: 'saw_load_nested_sidebar',
                    target_module: this.module,
                    prefill: this.prefill,
                    nonce: sawGlobal.nonce
                },
                success: function (response) {
                    self.$button.removeClass('loading').prop('disabled', false);

                    if (!response.success) {
                        alert(response.data?.message || 'Chyba při načítání formuláře');
                        return;
                    }

                    const htmlContent = response.data.html;

                    // Vytvoř wrapper s nested atributy
                    const $wrapper = $('<div class="saw-sidebar-wrapper active"></div>');
                    $wrapper.attr('data-is-nested', '1');
                    $wrapper.attr('data-target-field', self.field);
                    $wrapper.css('z-index', zIndex);

                    // Vlož HTML do wrapperu
                    $wrapper.html(htmlContent);

                    // Najdi vnitřní .saw-sidebar a označ ho také
                    const $sidebar = $wrapper.find('.saw-sidebar');
                    if ($sidebar.length) {
                        $sidebar.attr('data-is-nested', '1');
                    }

                    // Append to body
                    $('body').append($wrapper);

                    // Keep parent sidebar active but visually de-emphasize it
                    const $parent = $('.saw-sidebar-wrapper').not('[data-is-nested="1"]').first();
                    if ($parent.length) {
                        $parent.addClass('has-nested');
                    }

                    // Trigger animation and re-initialize forms
                    setTimeout(() => {
                        $wrapper.addClass('saw-sidebar-active');
                        
                        // Trigger initialization for any forms in the nested sidebar
                        // Use multiple delays to ensure DOM is fully ready and scripts are loaded
                        setTimeout(() => {
                            console.log('[Select-Create] Triggering saw:page-loaded event');
                            $(document).trigger('saw:page-loaded');
                            
                            // Also manually trigger initialization for companies form if present
                            if ($wrapper.find('.saw-company-form').length) {
                                console.log('[Select-Create] Manually initializing companies form');
                                setTimeout(() => {
                                    if (typeof SAW_Companies !== 'undefined' && SAW_Companies.init) {
                                        $('.saw-company-form').removeData('saw-initialized');
                                        SAW_Companies.init();
                                    }
                                }, 100);
                            }
                        }, 200);
                    }, 10);
                },
                error: function (xhr, status, error) {
                    self.$button.removeClass('loading').prop('disabled', false);

                    console.error('[Select-Create] AJAX error:', status, error);
                    console.error('[Select-Create] Response:', xhr.responseText);
                    alert('Chyba při komunikaci se serverem');
                }
            });
        }

        calculateZIndex() {
            // Count all sidebar wrappers (including parent and existing nested)
            const wrapperCount = $('.saw-sidebar-wrapper').length;
            const nestedCount = $('.saw-sidebar-wrapper[data-is-nested="1"]').length;
            
            // Parent sidebar has z-index: 100001
            // Nested sidebars must be above parent
            const parentZIndex = 100001;
            const increment = 100;
            
            // Calculate z-index: parent + increment for each nested level
            // First nested: 100002, second nested: 100003, etc.
            return parentZIndex + 1 + (nestedCount * increment);
        }
    }

    /* ============================================
       GLOBAL INLINE CREATE HANDLER
       ============================================ */

    /**
     * Initialize global inline create form handlers
     * 
     * Universal handler that works for ALL modules with inline create.
     * Detects _ajax_inline_create field and submits via AJAX.
     * 
     * @since 13.0.0
     */
    function initInlineCreateHandlers() {
        console.log('[Forms] Initializing global inline create handlers');
        
        // Remove any existing handler to prevent duplicates
        $(document).off('submit.saw-inline-create-global', 'form');
        
        // Attach universal handler with high priority
        // Use event delegation on document to catch all forms, including AJAX-loaded ones
        $(document).on('submit.saw-inline-create-global', 'form', function(e) {
            const $form = $(this);
            
            // Check if this is an inline create form - PRIMARY INDICATOR
            const $ajaxInlineCreate = $form.find('input[name="_ajax_inline_create"][value="1"]');
            if (!$ajaxInlineCreate.length) {
                // Not an inline create form, allow normal submission
                return;
            }
            
            console.log('[Inline-Create] Detected inline create form submission');
            
            // Prevent default submission
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            // Extract module name from form
            let moduleName = null;
            
            // 1. Check data-module attribute (most reliable)
            moduleName = $form.attr('data-module') || $form.closest('.saw-sidebar').attr('data-module');
            
            // 2. Extract from form class pattern: .saw-{module}-form
            if (!moduleName) {
                const formClass = $form.attr('class') || '';
                const match = formClass.match(/saw-(\w+)-form/);
                if (match && match[1]) {
                    moduleName = match[1];
                }
            }
            
            // 3. Extract from sidebar data-entity
            if (!moduleName) {
                const $sidebar = $form.closest('.saw-sidebar');
                moduleName = $sidebar.attr('data-entity');
            }
            
            if (!moduleName) {
                console.error('[Inline-Create] Cannot determine module name from form');
                alert('Chyba: Nelze určit modul. Zkuste obnovit stránku.');
                return false;
            }
            
            console.log('[Inline-Create] Module detected:', moduleName);
            
            // Get target field from nested wrapper
            const $wrapper = $('.saw-sidebar-wrapper[data-is-nested="1"]').last();
            const targetField = $wrapper.attr('data-target-field');
            
            console.log('[Inline-Create] Target field:', targetField);
            
            // Get nonce and AJAX URL from sawGlobal (always available)
            const nonce = (typeof window.sawGlobal !== 'undefined' && window.sawGlobal.nonce) 
                ? window.sawGlobal.nonce 
                : '';
            const ajaxurl = (typeof window.sawGlobal !== 'undefined' && window.sawGlobal.ajaxurl) 
                ? window.sawGlobal.ajaxurl 
                : '/wp-admin/admin-ajax.php';
            
            if (!nonce) {
                console.error('[Inline-Create] No nonce available');
                alert('Chyba: Nelze ověřit požadavek. Zkuste obnovit stránku.');
                return false;
            }
            
            // Construct AJAX action: saw_inline_create_{module}
            const action = 'saw_inline_create_' + moduleName;
            console.log('[Inline-Create] AJAX action:', action);
            
            // Check if module has custom handler (e.g., SAW_Companies.handleNestedSubmit)
            // Handle both singular and plural module names
            // Convert "companies" -> "Companies", "company" -> "Company"
            const capitalizedModule = moduleName.charAt(0).toUpperCase() + moduleName.slice(1);
            const moduleObjectName = 'SAW_' + capitalizedModule;
            const moduleObject = window[moduleObjectName];
            
            console.log('[Inline-Create] Looking for module object:', moduleObjectName);
            
            if (moduleObject && typeof moduleObject.handleNestedSubmit === 'function') {
                console.log('[Inline-Create] Using module-specific handler:', moduleObjectName);
                const result = moduleObject.handleNestedSubmit($form);
                return result === false ? false : undefined;
            }
            
            // Generic AJAX submission
            console.log('[Inline-Create] Using generic AJAX submission');
            
            const formData = $form.serialize() + '&action=' + encodeURIComponent(action) + '&nonce=' + encodeURIComponent(nonce);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function (response) {
                    console.log('[Inline-Create] AJAX response:', response);
                    
                    if (response.success) {
                        console.log('[Inline-Create] Success! Calling handleInlineSuccess');
                        
                        // Call global handler to update select and close nested sidebar
                        if (window.SAWSelectCreate && window.SAWSelectCreate.handleInlineSuccess) {
                            window.SAWSelectCreate.handleInlineSuccess(response.data, targetField);
                        } else {
                            console.error('[Inline-Create] SAWSelectCreate not available!');
                            alert('Záznam byl vytvořen, ale nepodařilo se aktualizovat formulář.');
                        }
                    } else {
                        alert(response.data?.message || 'Chyba při ukládání záznamu');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('[Inline-Create] AJAX error:', error);
                    console.error('[Inline-Create] Status:', status);
                    console.error('[Inline-Create] Response:', xhr.responseText);
                    console.error('[Inline-Create] Status code:', xhr.status);
                    
                    if (xhr.status === 403) {
                        alert('Chyba: Oprávnění zamítnuto. Možná problém s nonce. Zkuste obnovit stránku.');
                    } else if (xhr.status === 0) {
                        alert('Chyba: Nelze se připojit k serveru. Zkontrolujte připojení.');
                    } else {
                        const errorMsg = xhr.responseJSON?.data?.message || error;
                        alert('Chyba při komunikaci se serverem: ' + errorMsg);
                    }
                }
            });
            
            return false;
        });
    }
    
    // Initialize immediately when script loads (before DOM ready)
    // This ensures handler is attached before any forms are loaded
    if (typeof jQuery !== 'undefined') {
        initInlineCreateHandlers();
    }
    
    // Also initialize on document ready as backup
    $(document).ready(function() {
        initInlineCreateHandlers();
    });
    
    // Re-initialize when new content is loaded via AJAX
    $(document).on('saw:page-loaded', function() {
        console.log('[Forms] saw:page-loaded triggered, re-initializing inline create handlers');
        initInlineCreateHandlers();
    });

    window.SAWSelectCreate = {

        handleInlineSuccess: function (data, targetField) {
            console.log('[Select-Create] Handling success', data, targetField);

            if (!data || !data.id || !targetField) {
                console.error('[Select-Create] Invalid data or targetField:', data, targetField);
                return;
            }

            // Find select - search in all sidebars, prioritizing parent
            const $parent = $('.saw-sidebar-wrapper').not('[data-is-nested="1"]').first();
            let $select = null;
            
            console.log('[Select-Create] Looking for select with name:', targetField);
            console.log('[Select-Create] Parent sidebar found:', $parent.length);
            
            // First, try to find in parent sidebar
            if ($parent.length) {
                $select = $parent.find('select[name="' + targetField + '"]');
                console.log('[Select-Create] Searching in parent sidebar, found:', $select.length);
                if ($select.length) {
                    console.log('[Select-Create] Select found in parent sidebar');
                }
            }
            
            // Fallback: search globally but exclude nested sidebars
            if (!$select || !$select.length) {
                // Search all selects, but exclude those inside nested sidebars
                $('select[name="' + targetField + '"]').each(function() {
                    const $this = $(this);
                    const $nestedParent = $this.closest('.saw-sidebar-wrapper[data-is-nested="1"]');
                    if (!$nestedParent.length) {
                        if (!$select || !$select.length) {
                            $select = $this;
                        }
                    }
                });
                console.log('[Select-Create] Global search (excluding nested), found:', $select ? $select.length : 0);
            }

            if (!$select || !$select.length) {
                console.error('[Select-Create] Target select not found:', targetField);
                console.error('[Select-Create] Available selects:', $('select').map(function() { return $(this).attr('name'); }).get());
                alert('Select pole "' + targetField + '" nebylo nalezeno. Formulář bude aktualizován po zavření.');
                
                // Still close nested sidebar even if select not found
                const $nested = $('.saw-sidebar-wrapper[data-is-nested="1"]').last();
                if ($nested.length) {
                    this.closeNested($nested);
                }
                return;
            }

            console.log('[Select-Create] Found select, adding option:', data.id, data.name);

            // Check if option already exists
            const existingOption = $select.find(`option[value="${data.id}"]`);
            if (existingOption.length) {
                console.log('[Select-Create] Option already exists, selecting it');
                $select.val(data.id).trigger('change');
            } else {
                // Add new option
                const $option = $('<option>', {
                    value: data.id,
                    text: data.name || 'Firma #' + data.id,
                    selected: true
                });

                $select.append($option);
                $select.val(data.id).trigger('change');
                console.log('[Select-Create] Added new option with value:', data.id, 'text:', data.name);
            }

            // Visual feedback
            $select.addClass('saw-field-updated');
            setTimeout(() => {
                $select.removeClass('saw-field-updated');
            }, 2000);

            // Close nested sidebar immediately
            const $nested = $('.saw-sidebar-wrapper[data-is-nested="1"]').last();
            if ($nested.length) {
                this.closeNested($nested);
            }

            console.log('[Select-Create] Option added successfully');
        },

        closeNested: function ($nested) {
            if (!$nested || !$nested.length) {
                console.warn('[Select-Create] No nested sidebar to close');
                return;
            }

            console.log('[Select-Create] Closing nested, keeping parent');

            // Remove active class and trigger close animation
            $nested.removeClass('active');
            $nested.removeClass('saw-sidebar-active');

            setTimeout(() => {
                // Remove nested from DOM
                $nested.remove();

                // Reaktivuj parent sidebar
                const $parent = $('.saw-sidebar-wrapper').not('[data-is-nested="1"]').first();
                if ($parent.length) {
                    console.log('[Select-Create] Reactivating parent sidebar');
                    
                    // Remove has-nested class to restore full interactivity
                    $parent.removeClass('has-nested');
                    
                    // Ensure parent is active and visible
                    $parent.addClass('active');
                    
                    // Ensure inner sidebar is active
                    const $parentInner = $parent.find('.saw-sidebar');
                    if ($parentInner.length) {
                        $parentInner.addClass('saw-sidebar-active');
                    }
                    
                    // Trigger custom event for any listeners
                    $parent.trigger('saw:sidebar:reactivated');
                } else {
                    console.warn('[Select-Create] No parent sidebar found to reactivate');
                }
            }, 300);
        }
    };

    /* ============================================
       COLOR PICKER COMPONENT
       ============================================ */

    /**
     * SAW Color Picker Class
     *
     * Manages color picker component with live preview and external target sync.
     *
     * @since 1.0.0
     */
    class SAWColorPicker {

        /**
         * Constructor
         *
         * Initializes color picker component with DOM references.
         *
         * @since 1.0.0
         * @param {jQuery} $component Color picker component wrapper element
         */
        constructor($component) {
            this.$component = $component;
            this.$colorInput = $component.find('.saw-color-picker');
            this.$valueInput = $component.find('.saw-color-value');
            this.$previewBadge = $component.find('.saw-badge');

            this.targetId = this.$colorInput.data('target-id');
            this.$externalTarget = this.targetId ? $('#' + this.targetId) : null;

            this.init();
        }

        /**
         * Initialize component
         *
         * Sets up event listeners for color input changes.
         *
         * @since 1.0.0
         * @return {void}
         */
        init() {
            this.bindEvents();
        }

        /**
         * Bind event listeners
         *
         * Attaches input event handler to color picker element.
         *
         * @since 1.0.0
         * @return {void}
         */
        bindEvents() {
            // Color picker change
            this.$colorInput.on('input', (e) => {
                this.handleColorChange(e.target.value);
            });
        }

        /**
         * Handle color change
         *
         * Updates all related elements when color is changed.
         * Note: Uses inline styles for dynamic color values (legitimate use case).
         *
         * @since 1.0.0
         * @param {string} color Hex color value from color picker
         * @return {void}
         */
        handleColorChange(color) {
            const upperColor = color.toUpperCase();

            // Update value input
            this.$valueInput.val(upperColor);

            // Update preview badge if exists
            if (this.$previewBadge.length) {
                this.$previewBadge.css('background-color', color);
            }

            // Update external target if specified
            if (this.$externalTarget && this.$externalTarget.length) {
                this.$externalTarget.css('background-color', color);
            }
        }
    }

    /* ============================================
       FILE UPLOAD COMPONENT
       ============================================ */

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

    /* ============================================
       INITIALIZATION
       ============================================ */

    /**
     * Initialize all form components
     * 
     * @since 1.0.0
     */
    function initFormComponents() {
        console.log('[Forms] Initializing form components...');

        // Initialize selectbox components
        $('.saw-selectbox-component').each(function () {
            if (!$(this).data('saw-initialized')) {
                new SAWSelectboxComponent($(this));
                $(this).data('saw-initialized', true);
            }
        });

        // Initialize select-create components
        $('.saw-inline-create-btn').each(function () {
            if (!$(this).data('saw-initialized')) {
                new SAWSelectCreateComponent($(this));
                $(this).data('saw-initialized', true);
            }
        });

        // Initialize color picker components
        $('.saw-color-picker-component').each(function () {
            if (!$(this).data('saw-initialized')) {
                new SAWColorPicker($(this));
                $(this).data('saw-initialized', true);
            }
        });

        // Initialize file upload components
        $('.saw-file-upload-component').each(function () {
            if (!$(this).data('saw-initialized')) {
                new SAWFileUpload($(this));
                $(this).data('saw-initialized', true);
            }
        });
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function () {
        initFormComponents();
    });

    /**
     * Re-initialize on AJAX page load
     */
    $(document).on('saw:page-loaded', function () {
        console.log('[Forms] Re-initializing after AJAX load');
        initFormComponents();
    });

    /* ============================================
       SELECT-CREATE EVENT HANDLERS
       ============================================ */

    // ✅ CRITICAL: Close handler s stopImmediatePropagation
    // Zastaví admin-table.js handler, který by zavřel všechny sidebary
    $(document).on('click', '.saw-sidebar-wrapper[data-is-nested="1"] .saw-sidebar-close', function (e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation(); // ← ZASTAVÍ další handlery!

        console.log('[Select-Create] Close button clicked on nested sidebar');

        const $nested = $(this).closest('.saw-sidebar-wrapper[data-is-nested="1"]');

        if ($nested.length) {
            window.SAWSelectCreate.closeNested($nested);
        } else {
            console.warn('[Select-Create] Nested wrapper not found');
        }
    });

    // ESC key handler
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            const $nested = $('.saw-sidebar-wrapper[data-is-nested="1"]').last();
            if ($nested.length) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();

                console.log('[Select-Create] ESC pressed on nested sidebar');
                window.SAWSelectCreate.closeNested($nested);
            }
        }
    });

})(jQuery);

