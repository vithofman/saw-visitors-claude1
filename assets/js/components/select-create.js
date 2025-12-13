/**
 * SAW Select-Create Component - FIXED v2.0
 * 
 * CRITICAL FIXES:
 * 1. Uses single hidden input with class .saw-select-create-value
 * 2. No race conditions with visits.js
 * 3. Explicit value synchronization
 * 4. Works correctly for both CREATE and EDIT modes
 * 
 * @package     SAW_Visitors
 * @subpackage  JS/Components
 * @version     2.0.0 - COMPLETELY REWRITTEN
 */

(function($) {
    'use strict';
    
    /**
     * Initialize all searchable select components
     */
    function initSearchableSelects() {
        console.log('[SelectCreate v2.0] Initializing...');
        
        $('.saw-select-create-select').each(function() {
            const $select = $(this);
            
            // Skip if already initialized
            if ($select.data('sc-initialized')) {
                console.log('[SelectCreate v2.0] Already initialized:', $select.attr('id'));
                return;
            }
            
            const $wrapper = $select.closest('.saw-select-create-wrapper');
            const fieldName = $select.data('field-name') || $select.attr('id').replace('saw-select-', '');
            const placeholder = $select.data('placeholder') || 'Hledat...';
            const fieldId = $select.attr('id');
            
            console.log('[SelectCreate v2.0] Processing:', fieldName);
            
            // Skip small selects (< 3 options)
            const optionCount = $select.find('option').length;
            if (optionCount <= 2) {
                console.log('[SelectCreate v2.0] Skipping (too few options):', fieldName);
                $select.data('sc-initialized', true);
                return;
            }
            
            // ============================================
            // CRITICAL: Find the hidden value input
            // ============================================
            const $hiddenInput = $wrapper.find('.saw-select-create-value');
            
            if (!$hiddenInput.length) {
                console.error('[SelectCreate v2.0] CRITICAL: Hidden input not found for:', fieldName);
                return;
            }
            
            console.log('[SelectCreate v2.0] Hidden input found:', $hiddenInput.attr('id'), '| Value:', $hiddenInput.val());
            
            // ============================================
            // Build options array from select
            // ============================================
            const options = [];
            $select.find('option').each(function() {
                const $opt = $(this);
                options.push({
                    value: $opt.attr('value') || '',
                    text: $opt.text().trim(),
                    selected: $opt.prop('selected')
                });
            });
            
            // ============================================
            // Create search input
            // ============================================
            const $searchInput = $('<input>', {
                type: 'text',
                class: 'saw-input saw-select-search-input',
                id: fieldId + '-search',
                placeholder: placeholder,
                autocomplete: 'off'
            });
            
            // ============================================
            // Create dropdown
            // ============================================
            const $dropdown = $('<div>', {
                class: 'saw-select-search-dropdown',
                id: fieldId + '-dropdown'
            }).hide();
            
            // ============================================
            // Set initial value in search input
            // ============================================
            const currentValue = $hiddenInput.val();
            if (currentValue) {
                const selectedOption = options.find(o => String(o.value) === String(currentValue));
                if (selectedOption) {
                    $searchInput.val(selectedOption.text);
                    console.log('[SelectCreate v2.0] Initial value set:', currentValue, '->', selectedOption.text);
                }
            }
            
            // ============================================
            // Hide original select, insert search input
            // ============================================
            $select.hide();
            $select.after($searchInput);
            $wrapper.append($dropdown);
            
            // ============================================
            // Build dropdown function
            // ============================================
            function buildDropdown(filterText) {
                $dropdown.empty();
                
                const currentVal = String($hiddenInput.val() || '');
                
                const filtered = options.filter(opt => {
                    if (opt.value === '') return true; // Always show placeholder
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
                    const isSelected = String(opt.value) === currentVal && opt.value !== '';
                    
                    const $item = $('<div>', {
                        class: 'saw-select-search-item' + (isSelected ? ' selected' : ''),
                        'data-value': opt.value,
                        text: opt.value === '' ? placeholder : opt.text
                    });
                    
                    // ============================================
                    // CRITICAL: Click handler - sets hidden input
                    // ============================================
                    $item.on('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        const clickedValue = $(this).data('value');
                        const clickedText = $(this).text();
                        
                        console.log('[SelectCreate v2.0] Item clicked:', clickedText, '| Value:', clickedValue);
                        
                        // Set search input text
                        $searchInput.val(clickedValue ? clickedText : '');
                        
                        // ============================================
                        // CRITICAL: Set hidden input value
                        // ============================================
                        $hiddenInput.val(clickedValue);
                        
                        console.log('[SelectCreate v2.0] Hidden input after click:', 
                            $hiddenInput.attr('name'), '=', $hiddenInput.val());
                        
                        // Update visual state
                        $dropdown.find('.saw-select-search-item').removeClass('selected');
                        if (clickedValue) {
                            $(this).addClass('selected');
                        }
                        
                        // Hide dropdown
                        $dropdown.hide();
                        
                        // Trigger change event for other listeners
                        $hiddenInput.trigger('change');
                        
                        // Also trigger custom event for visits.js
                        $(document).trigger('saw:company-selected', {
                            fieldName: fieldName,
                            value: clickedValue,
                            text: clickedText
                        });
                    });
                    
                    $dropdown.append($item);
                });
            }
            
            // ============================================
            // Focus handler - show dropdown
            // ============================================
            $searchInput.on('focus', function() {
                buildDropdown($(this).val());
                $dropdown.show();
            });
            
            // ============================================
            // Input handler - filter and update
            // ============================================
            $searchInput.on('input', function() {
                const searchText = $(this).val();
                buildDropdown(searchText);
                $dropdown.show();
                
                // If no exact match, clear hidden input
                const exactMatch = options.find(o => 
                    o.value !== '' && 
                    o.text.toLowerCase() === searchText.toLowerCase()
                );
                
                if (!exactMatch && searchText) {
                    // User is typing but no match yet - don't clear
                    // This allows partial typing
                } else if (!searchText) {
                    // Empty search = clear value
                    $hiddenInput.val('');
                    console.log('[SelectCreate v2.0] Search cleared, hidden input cleared');
                }
            });
            
            // ============================================
            // Keyboard navigation
            // ============================================
            $searchInput.on('keydown', function(e) {
                const $items = $dropdown.find('.saw-select-search-item:visible');
                const $current = $dropdown.find('.saw-select-search-item.highlighted');
                
                switch(e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        if (!$dropdown.is(':visible')) {
                            $dropdown.show();
                            buildDropdown($(this).val());
                        }
                        if ($current.length) {
                            $current.removeClass('highlighted');
                            const nextIndex = Math.min($items.index($current) + 1, $items.length - 1);
                            $items.eq(nextIndex).addClass('highlighted');
                        } else {
                            $items.first().addClass('highlighted');
                        }
                        break;
                        
                    case 'ArrowUp':
                        e.preventDefault();
                        if ($current.length) {
                            $current.removeClass('highlighted');
                            const prevIndex = Math.max($items.index($current) - 1, 0);
                            $items.eq(prevIndex).addClass('highlighted');
                        }
                        break;
                        
                    case 'Enter':
                        e.preventDefault();
                        const $highlighted = $dropdown.find('.saw-select-search-item.highlighted');
                        if ($highlighted.length) {
                            $highlighted.trigger('click');
                        }
                        break;
                        
                    case 'Escape':
                        $dropdown.hide();
                        $searchInput.blur();
                        break;
                        
                    case 'Tab':
                        $dropdown.hide();
                        break;
                }
            });
            
            // ============================================
            // Click outside - close dropdown
            // ============================================
            $(document).on('click.selectcreate-' + fieldName, function(e) {
                if (!$(e.target).closest($wrapper).length) {
                    $dropdown.hide();
                }
            });
            
            // Mark as initialized
            $select.data('sc-initialized', true);
            
            console.log('[SelectCreate v2.0] ✅ Initialized:', fieldName, '| Current value:', $hiddenInput.val());
        });
    }
    
    /**
     * Handle inline create success - add new option to dropdown
     */
    window.SAWSelectCreate = window.SAWSelectCreate || {};
    
    window.SAWSelectCreate.handleInlineSuccess = function(data, targetField) {
        console.log('[SelectCreate v2.0] Inline create success:', data, '| Target:', targetField);
        
        if (!data || !data.id || !targetField) {
            console.error('[SelectCreate v2.0] Invalid inline create data');
            return;
        }
        
        const $wrapper = $('[data-field-name="' + targetField + '"]').closest('.saw-select-create-wrapper');
        const $select = $wrapper.find('.saw-select-create-select');
        const $hiddenInput = $wrapper.find('.saw-select-create-value');
        const $searchInput = $wrapper.find('.saw-select-search-input');
        
        // Add new option to original select
        const displayName = data.display_name || data.name || 'Nový záznam #' + data.id;
        $select.append($('<option>', {
            value: data.id,
            text: displayName,
            selected: true
        }));
        
        // Set values
        $hiddenInput.val(data.id);
        $searchInput.val(displayName);
        
        console.log('[SelectCreate v2.0] New option added:', data.id, '->', displayName);
        
        // Close nested sidebar
        if (window.SAWSidebar && typeof window.SAWSidebar.closeNested === 'function') {
            window.SAWSidebar.closeNested();
        } else {
            // Fallback - close any nested sidebar
            $('.saw-sidebar-wrapper[data-is-nested="1"]').last().remove();
        }
        
        // Trigger change
        $hiddenInput.trigger('change');
    };
    
    /**
     * API: Get current value
     */
    window.SAWSelectCreate.getValue = function(fieldName) {
        const $hidden = $('[name="' + fieldName + '"].saw-select-create-value');
        return $hidden.length ? $hidden.val() : null;
    };
    
    /**
     * API: Set value programmatically
     */
    window.SAWSelectCreate.setValue = function(fieldName, value, text) {
        const $wrapper = $('[data-field-name="' + fieldName + '"]').closest('.saw-select-create-wrapper');
        const $hidden = $wrapper.find('.saw-select-create-value');
        const $search = $wrapper.find('.saw-select-search-input');
        
        $hidden.val(value);
        if (text) {
            $search.val(text);
        }
        
        console.log('[SelectCreate v2.0] Value set via API:', fieldName, '=', value);
    };
    
    /**
     * API: Clear value
     */
    window.SAWSelectCreate.clearValue = function(fieldName) {
        window.SAWSelectCreate.setValue(fieldName, '', '');
    };
    
    // ============================================
    // Initialize on various events
    // ============================================
    $(document).ready(function() {
        console.log('[SelectCreate v2.0] Document ready');
        initSearchableSelects();
    });
    
    // Re-initialize on AJAX content load
    $(document).on('saw:page-loaded saw:sidebar-loaded saw:content-loaded', function(e) {
        console.log('[SelectCreate v2.0] Content event:', e.type);
        setTimeout(initSearchableSelects, 100);
    });
    
    // Re-initialize when sidebar opens
    $(document).on('saw:sidebar-opened', function() {
        console.log('[SelectCreate v2.0] Sidebar opened');
        setTimeout(initSearchableSelects, 150);
    });
    
})(jQuery);
