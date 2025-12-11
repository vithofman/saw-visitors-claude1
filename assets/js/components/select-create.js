/**
 * SAW Select-Create Component
 * 
 * Adds searchable functionality to select dropdowns.
 * Converts standard select to searchable input with dropdown.
 * 
 * @package     SAW_Visitors
 * @subpackage  JS/Components
 * @version     1.0.1 - FIXED: Remove name attribute from original select to prevent duplicate POST values
 * @since       1.0.0
 */

(function($) {
    'use strict';
    
    /**
     * Initialize searchable selects
     */
    function initSearchableSelects() {
        $('.saw-select-create-select').each(function() {
            const $select = $(this);
            
            // Skip if already initialized
            if ($select.data('searchable-initialized')) {
                return;
            }
            
            // Skip if has only 1-2 options (no need for search)
            const optionCount = $select.find('option').length;
            if (optionCount <= 2) {
                return;
            }
            
            const $wrapper = $select.closest('.saw-select-create-wrapper');
            const fieldId = $select.attr('id');
            const fieldName = $select.attr('name');
            const placeholder = $select.find('option[value=""]').text() || 'Hledat...';
            const isRequired = $select.prop('required');
            
            // Get all options
            const options = [];
            $select.find('option').each(function() {
                const $option = $(this);
                options.push({
                    value: $option.attr('value'),
                    text: $option.text(),
                    selected: $option.prop('selected')
                });
            });
            
            // Create search input
            const $searchInput = $('<input>', {
                type: 'text',
                class: 'saw-input saw-select-search-input',
                id: fieldId + '-search',
                placeholder: placeholder,
                autocomplete: 'off',
                required: isRequired
            });
            
            // Create dropdown container
            const $dropdown = $('<div>', {
                class: 'saw-select-search-dropdown',
                id: fieldId + '-dropdown'
            });
            
            // Create hidden input to store selected value
            const $hiddenInput = $('<input>', {
                type: 'hidden',
                name: fieldName,
                id: fieldId + '-hidden',
                value: $select.val() || ''
            });
            
            // Set initial value
            const selectedOption = options.find(opt => opt.selected && opt.value !== '');
            if (selectedOption) {
                $searchInput.val(selectedOption.text);
                $hiddenInput.val(selectedOption.value);
            }
            
            // CRITICAL FIX: Remove name attribute from original select
            // This prevents duplicate POST values when form is submitted.
            // Without this fix, both the hidden input AND the original select
            // send the same field name, and PHP takes the last one (empty select).
            $select.removeAttr('name');
            
            // Hide original select
            $select.hide();
            
            // Insert elements
            $select.before($searchInput);
            $select.before($hiddenInput);
            $wrapper.append($dropdown);
            
            // Build dropdown items
            function buildDropdown(filterText = '') {
                $dropdown.empty();
                
                const filtered = options.filter(opt => {
                    if (opt.value === '') {
                        return true; // Always show empty option
                    }
                    if (!filterText) {
                        return true;
                    }
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
                        class: 'saw-select-search-item' + (opt.selected ? ' selected' : ''),
                        'data-value': opt.value,
                        text: opt.text || placeholder
                    });
                    
                    $item.on('click', function() {
                        $searchInput.val(opt.text);
                        $hiddenInput.val(opt.value);
                        $dropdown.hide();
                        
                        // Trigger change event
                        $hiddenInput.trigger('change');
                        
                        // Update visual state
                        $dropdown.find('.saw-select-search-item').removeClass('selected');
                        $item.addClass('selected');
                    });
                    
                    $dropdown.append($item);
                });
            }
            
            // Show/hide dropdown
            $searchInput.on('focus', function() {
                buildDropdown($(this).val());
                $dropdown.show();
            });
            
            $searchInput.on('input', function() {
                const filterText = $(this).val();
                buildDropdown(filterText);
                $dropdown.show();
                
                // Clear hidden input if search doesn't match any option
                const matchingOption = options.find(opt => opt.text.toLowerCase() === filterText.toLowerCase());
                if (!matchingOption) {
                    $hiddenInput.val('');
                }
            });
            
            // Hide dropdown on outside click
            $(document).on('click', function(e) {
                if (!$(e.target).closest($wrapper).length) {
                    $dropdown.hide();
                }
            });
            
            // Handle keyboard navigation
            $searchInput.on('keydown', function(e) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    const $items = $dropdown.find('.saw-select-search-item:visible');
                    const $current = $dropdown.find('.saw-select-search-item.highlighted');
                    if ($current.length) {
                        const index = $items.index($current);
                        $current.removeClass('highlighted');
                        if (index < $items.length - 1) {
                            $items.eq(index + 1).addClass('highlighted');
                        }
                    } else if ($items.length > 0) {
                        $items.first().addClass('highlighted');
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    const $items = $dropdown.find('.saw-select-search-item:visible');
                    const $current = $dropdown.find('.saw-select-search-item.highlighted');
                    if ($current.length) {
                        const index = $items.index($current);
                        $current.removeClass('highlighted');
                        if (index > 0) {
                            $items.eq(index - 1).addClass('highlighted');
                        }
                    }
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    const $highlighted = $dropdown.find('.saw-select-search-item.highlighted');
                    if ($highlighted.length) {
                        $highlighted.trigger('click');
                    }
                } else if (e.key === 'Escape') {
                    $dropdown.hide();
                    $searchInput.blur();
                }
            });
            
            // Mark as initialized
            $select.data('searchable-initialized', true);
        });
    }
    
    // Initialize on document ready
    $(document).ready(function() {
        initSearchableSelects();
    });
    
    // Re-initialize after AJAX loads (for dynamic content)
    $(document).on('saw:content-loaded', function() {
        initSearchableSelects();
    });
    
})(jQuery);