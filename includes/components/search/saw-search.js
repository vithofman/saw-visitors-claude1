(function($) {
    'use strict';

    class SAWSearchComponent {
        constructor($input) {
            this.$input = $input;
            this.$wrapper = $input.closest('.saw-search-wrapper');
            this.$clearBtn = this.$wrapper.find('.saw-search-clear');
            this.$submitBtn = this.$wrapper.find('.saw-search-submit');
            this.entity = $input.data('entity');
            this.ajaxAction = $input.data('ajax-action');
            this.ajaxEnabled = $input.data('ajax-enabled') === 1;
            this.searchTimeout = null;
            
            this.init();
        }
        
        init() {
            this.bindEvents();
        }
        
        bindEvents() {
            this.$input.on('input', (e) => this.handleInput(e));
            this.$input.on('keydown', (e) => this.handleKeydown(e));
            this.$clearBtn.on('click', () => this.handleClear());
            this.$submitBtn.on('click', () => this.handleSubmit());
        }
        
        handleInput(e) {
            const value = this.$input.val().trim();
            
            this.toggleClearButton(value);
            
            if (this.ajaxEnabled) {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.performSearch(value);
                }, 300);
            }
        }
        
        handleKeydown(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.handleSubmit();
            }
            
            if (e.key === 'Escape') {
                this.handleClear();
            }
        }
        
        handleSubmit() {
            const value = this.$input.val().trim();
            
            if (this.ajaxEnabled) {
                this.performSearch(value);
            } else {
                this.submitForm();
            }
        }
        
        handleClear() {
            this.$input.val('').trigger('input').focus();
            this.toggleClearButton('');
            
            if (this.ajaxEnabled) {
                this.performSearch('');
            } else {
                this.submitForm();
            }
        }
        
        toggleClearButton(value) {
            if (value) {
                this.$clearBtn.fadeIn(150);
            } else {
                this.$clearBtn.fadeOut(150);
            }
        }
        
        performSearch(query) {
            $(document).trigger('saw:search:start', {
                entity: this.entity,
                query: query
            });
            
            $.ajax({
                url: sawGlobal.ajaxurl,
                type: 'GET',
                data: {
                    action: this.ajaxAction,
                    entity: this.entity,
                    s: query,
                    nonce: sawGlobal.nonce
                },
                beforeSend: () => {
                    this.$input.addClass('saw-search-loading');
                    this.$submitBtn.prop('disabled', true);
                },
                success: (response) => {
                    if (response.success) {
                        $(document).trigger('saw:search:success', {
                            entity: this.entity,
                            query: query,
                            data: response.data
                        });
                    } else {
                        $(document).trigger('saw:search:error', {
                            entity: this.entity,
                            query: query,
                            message: response.data?.message || 'Chyba při vyhledávání'
                        });
                    }
                },
                error: (xhr) => {
                    $(document).trigger('saw:search:error', {
                        entity: this.entity,
                        query: query,
                        message: 'Chyba serveru'
                    });
                },
                complete: () => {
                    this.$input.removeClass('saw-search-loading');
                    this.$submitBtn.prop('disabled', false);
                }
            });
        }
        
        submitForm() {
            const $form = this.$input.closest('form');
            if ($form.length) {
                $form.submit();
            } else {
                const query = this.$input.val().trim();
                const url = new URL(window.location.href);
                
                if (query) {
                    url.searchParams.set('s', query);
                } else {
                    url.searchParams.delete('s');
                }
                
                url.searchParams.delete('paged');
                window.location.href = url.toString();
            }
        }
    }

    $(document).ready(function() {
        $('.saw-search-input').each(function() {
            new SAWSearchComponent($(this));
        });
    });

})(jQuery);