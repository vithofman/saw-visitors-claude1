(function($) {
    'use strict';

    class SAWSelectboxComponent {
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
        
        init() {
            this.bindEvents();
            
            if (this.ajaxEnabled && !this.isLoaded) {
                this.loadOptions();
            }
        }
        
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
        
        toggle() {
            if (this.$container.hasClass('open')) {
                this.close();
            } else {
                this.open();
            }
        }
        
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
        
        close() {
            this.$container.removeClass('open');
            
            if (this.searchable) {
                this.$search.val('');
                this.handleSearch('');
            }
        }
        
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
        
        handleSearch(query) {
            query = query.toLowerCase().trim();
            
            this.$options.find('.saw-selectbox-option').each(function() {
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
                    console.error('Selectbox AJAX error:', xhr.status, xhr.responseText);
                    this.$options.html('<div class="saw-selectbox-empty">Chyba serveru (' + xhr.status + ')</div>');
                }
            });
        }
        
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

    $(document).ready(function() {
        $('.saw-selectbox-component').each(function() {
            new SAWSelectboxComponent($(this));
        });
    });

})(jQuery);