/**
 * SAW Select-Create Component
 * 
 * JavaScript for inline create functionality in select dropdowns.
 * Handles nested sidebar loading, z-index management, and dropdown updates.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/SelectCreate
 * @version     1.1.0 - FIXED: Nested sidebar wrapper positioning
 * @since       13.0.0
 * @author      SAW Visitors Team
 */

(function($) {
    'use strict';

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
                success: function(response) {
                    self.$button.removeClass('loading').prop('disabled', false);
                    
                    if (!response.success) {
                        alert(response.data?.message || 'Chyba při načítání formuláře');
                        return;
                    }
                    
                    // ✅ CRITICAL: Wrap v .saw-sidebar-wrapper pro správné stylování
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
                    
                    // Trigger animation
                    setTimeout(() => {
                        $wrapper.addClass('saw-sidebar-active');
                    }, 10);
                },
                error: function(xhr, status, error) {
                    self.$button.removeClass('loading').prop('disabled', false);
                    
                    console.error('AJAX error:', status, error);
                    console.error('Response:', xhr.responseText);
                    alert('Chyba při komunikaci se serverem\n\n' + xhr.responseText);
                }
            });
        }
        
        calculateZIndex() {
            const sidebarCount = $('.saw-sidebar').length;
            const baseZIndex = 1000;
            const increment = 100;
            
            return baseZIndex + (sidebarCount * increment);
        }
    }
    
    window.SAWSelectCreate = {
        
        handleInlineSuccess: function(data, targetField) {
            console.log('SAWSelectCreate: Handling success', data, targetField);
            
            const $select = $(`select[name="${targetField}"]`);
            
            if (!$select.length) {
                console.error('SAWSelectCreate: Target select not found:', targetField);
                return;
            }
            
            const $option = $('<option>', {
                value: data.id,
                text: data.name,
                selected: true
            });
            
            $select.append($option);
            $select.trigger('change');
            
            $select.addClass('saw-field-updated');
            setTimeout(() => {
                $select.removeClass('saw-field-updated');
            }, 2000);
            
            const $nested = $('.saw-sidebar-wrapper[data-is-nested="1"]').last();
            this.closeNested($nested);
            
            console.log('SAWSelectCreate: Option added successfully');
        },
        
        closeNested: function($nested) {
            if (!$nested || !$nested.length) {
                return;
            }
            
            $nested.removeClass('saw-sidebar-active');
            
            setTimeout(() => {
                $nested.remove();
            }, 300);
        }
    };
    
    $(document).ready(function() {
        $('.saw-inline-create-btn').each(function() {
            new SAWSelectCreateComponent($(this));
        });
    });
    
    $(document).on('click', '.saw-sidebar-wrapper[data-is-nested="1"] .saw-sidebar-close', function(e) {
        e.preventDefault();
        
        const $nested = $(this).closest('.saw-sidebar-wrapper[data-is-nested="1"]');
        window.SAWSelectCreate.closeNested($nested);
    });
    
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            const $nested = $('.saw-sidebar-wrapper[data-is-nested="1"]').last();
            if ($nested.length) {
                window.SAWSelectCreate.closeNested($nested);
            }
        }
    });
    
})(jQuery);