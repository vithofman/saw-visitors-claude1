/**
 * SAW Select-Create Component
 * 
 * @package     SAW_Visitors
 * @subpackage  JS/Components
 * @version     1.3.0 - ALWAYS removes required from select
 * @since       1.0.0
 */

(function($) {
    'use strict';
    
    function initSearchableSelects() {
        $('.saw-select-create-select').each(function() {
            const $select = $(this);
            
            if ($select.data('searchable-initialized')) {
                // Even if initialized, ensure required is removed
                $select.prop('required', false).removeAttr('required');
                return;
            }
            
            const optionCount = $select.find('option').length;
            if (optionCount <= 2) {
                // Still remove required for small selects
                $select.prop('required', false).removeAttr('required');
                return;
            }
            
            const $wrapper = $select.closest('.saw-select-create-wrapper');
            const fieldId = $select.attr('id');
            const fieldName = $select.data('field-name') || $select.attr('name') || fieldId.replace('saw-select-', '');
            const placeholder = $select.find('option[value=""]').text() || 'Hledat...';
            
            console.log('[SelectCreate] Initializing:', fieldName, '| fieldId:', fieldId);
            
            // ============================================
            // CRITICAL: Remove name and required from select
            // ============================================
            $select.removeAttr('name');
            $select.prop('required', false).removeAttr('required');
            
            // Find or create hidden input
            let $hiddenInput = $('#' + fieldId + '-hidden');
            
            if (!$hiddenInput.length) {
                $hiddenInput = $wrapper.find('input[type="hidden"][name="' + fieldName + '"]');
            }
            
            if (!$hiddenInput.length) {
                console.log('[SelectCreate] Creating hidden input for:', fieldName);
                $hiddenInput = $('<input>', {
                    type: 'hidden',
                    name: fieldName,
                    id: fieldId + '-hidden',
                    value: $select.val() || ''
                });
                $select.before($hiddenInput);
            }
            
            console.log('[SelectCreate] Hidden input:', $hiddenInput.attr('id'), '| Current value:', $hiddenInput.val());
            
            const options = [];
            $select.find('option').each(function() {
                options.push({
                    value: $(this).attr('value'),
                    text: $(this).text(),
                    selected: $(this).prop('selected')
                });
            });
            
            const $searchInput = $('<input>', {
                type: 'text',
                class: 'saw-input saw-select-search-input',
                id: fieldId + '-search',
                placeholder: placeholder,
                autocomplete: 'off'
            });
            
            const $dropdown = $('<div>', {
                class: 'saw-select-search-dropdown',
                id: fieldId + '-dropdown'
            });
            
            // Set initial value
            const currentValue = $hiddenInput.val();
            if (currentValue) {
                const opt = options.find(o => o.value == currentValue);
                if (opt) {
                    $searchInput.val(opt.text);
                    console.log('[SelectCreate] Initial value set from hidden:', currentValue, '->', opt.text);
                }
            } else {
                const opt = options.find(o => o.selected && o.value !== '');
                if (opt) {
                    $searchInput.val(opt.text);
                    $hiddenInput.val(opt.value);
                    console.log('[SelectCreate] Initial value set from selected option:', opt.value);
                }
            }
            
            $select.hide();
            $select.before($searchInput);
            $wrapper.append($dropdown);
            
            function buildDropdown(filterText) {
                $dropdown.empty();
                const currentVal = $hiddenInput.val();
                
                const filtered = options.filter(opt => {
                    if (opt.value === '') return true;
                    if (!filterText) return true;
                    return opt.text.toLowerCase().includes(filterText.toLowerCase());
                });
                
                if (filtered.length === 0) {
                    $dropdown.append($('<div>', {
                        class: 'saw-select-search-no-results',
                        text: 'Žádné výsledky'
                    }));
                    return;
                }
                
                filtered.forEach(opt => {
                    const $item = $('<div>', {
                        class: 'saw-select-search-item' + (opt.value == currentVal ? ' selected' : ''),
                        'data-value': opt.value,
                        text: opt.text || placeholder
                    });
                    
                    $item.on('click', function() {
                        console.log('[SelectCreate] Item clicked:', opt.text, '| Value:', opt.value);
                        
                        // Set search input text
                        $searchInput.val(opt.value ? opt.text : '');
                        
                        // SET HIDDEN INPUT VALUE - THIS IS CRITICAL
                        $hiddenInput.val(opt.value);
                        
                        console.log('[SelectCreate] Hidden input after click:', $hiddenInput.attr('id'), '=', $hiddenInput.val());
                        
                        $dropdown.hide();
                        $hiddenInput.trigger('change');
                        
                        $dropdown.find('.saw-select-search-item').removeClass('selected');
                        $item.addClass('selected');
                    });
                    
                    $dropdown.append($item);
                });
            }
            
            $searchInput.on('focus', function() {
                buildDropdown($(this).val());
                $dropdown.show();
            });
            
            $searchInput.on('input', function() {
                buildDropdown($(this).val());
                $dropdown.show();
                
                const match = options.find(o => o.text.toLowerCase() === $(this).val().toLowerCase());
                if (!match) {
                    $hiddenInput.val('');
                }
            });
            
            $(document).on('click', function(e) {
                if (!$(e.target).closest($wrapper).length) {
                    $dropdown.hide();
                }
            });
            
            $searchInput.on('keydown', function(e) {
                const $items = $dropdown.find('.saw-select-search-item:visible');
                const $current = $dropdown.find('.saw-select-search-item.highlighted');
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if ($current.length) {
                        $current.removeClass('highlighted');
                        $items.eq(Math.min($items.index($current) + 1, $items.length - 1)).addClass('highlighted');
                    } else {
                        $items.first().addClass('highlighted');
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if ($current.length) {
                        $current.removeClass('highlighted');
                        $items.eq(Math.max($items.index($current) - 1, 0)).addClass('highlighted');
                    }
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    $dropdown.find('.saw-select-search-item.highlighted').trigger('click');
                } else if (e.key === 'Escape') {
                    $dropdown.hide();
                    $searchInput.blur();
                }
            });
            
            $select.data('searchable-initialized', true);
            
            console.log('[SelectCreate] ✅ Initialized:', fieldName);
        });
    }
    
    $(document).ready(initSearchableSelects);
    $(document).on('saw:page-loaded saw:sidebar-loaded saw:content-loaded', function() {
        setTimeout(initSearchableSelects, 50);
    });
    
})(jQuery);