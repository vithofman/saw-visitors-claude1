/**
 * SAW Select-Create Component
 * 
 * JavaScript for inline create functionality in select dropdowns.
 * Handles nested sidebar loading, z-index management, and dropdown updates.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/SelectCreate
 * @version     1.2.0 - FIXED: Close handler with stopImmediatePropagation
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
                    
                    console.error('[Select-Create] AJAX error:', status, error);
                    console.error('[Select-Create] Response:', xhr.responseText);
                    alert('Chyba při komunikaci se serverem');
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
            console.log('[Select-Create] Handling success', data, targetField);
            
            const $select = $(`select[name="${targetField}"]`);
            
            if (!$select.length) {
                console.error('[Select-Create] Target select not found:', targetField);
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
            
            console.log('[Select-Create] Option added successfully');
        },
        
        closeNested: function($nested) {
            if (!$nested || !$nested.length) return;
            
            console.log('[Select-Create] Closing nested, keeping parent');
            
            // Zavři nested wrapper
            $nested.removeClass('active');
            
            setTimeout(() => {
                $nested.remove();
                
                // Reaktivuj parent sidebar
                const $parent = $('.saw-sidebar-wrapper').not('[data-is-nested="1"]').first();
                if ($parent.length) {
                    console.log('[Select-Create] Reactivating parent sidebar');
                    $parent.addClass('active');
                    
                    // Zajisti že je viditelný
                    const $parentInner = $parent.find('.saw-sidebar');
                    if ($parentInner.length && !$parentInner.hasClass('saw-sidebar-active')) {
                        $parentInner.addClass('saw-sidebar-active');
                    }
                }
            }, 300);
        }
    };
    
    $(document).ready(function() {
        $('.saw-inline-create-btn').each(function() {
            new SAWSelectCreateComponent($(this));
        });
    });
    
    // ✅ CRITICAL: Close handler s stopImmediatePropagation
    // Zastaví admin-table.js handler, který by zavřel všechny sidebary
    $(document).on('click', '.saw-sidebar-wrapper[data-is-nested="1"] .saw-sidebar-close', function(e) {
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
    $(document).on('keydown', function(e) {
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