/**
 * SAW Admin Table - Globální JavaScript
 * 
 * Kopíruje funkcionalitu z saw-customers.js
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 */

(function($) {
    'use strict';
    
    const SawAdminTable = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindSearchInput();
            this.bindSearchClear();
            this.bindSortHeaders();
            this.bindDeleteButtons();
            this.bindAlertClose();
            
            console.log('SAW Admin Table: Initialized');
        },
        
        /**
         * Bind search input (debounced)
         */
        bindSearchInput: function() {
            let searchTimeout = null;
            
            $(document).on('input', '.saw-search-input', function() {
                clearTimeout(searchTimeout);
                
                const $input = $(this);
                const searchTerm = $input.val().trim();
                const entity = $input.data('entity');
                const ajaxEnabled = $input.data('ajax-enabled') === 1;
                
                // Show/hide clear button
                const $clearBtn = $('#saw-search-clear');
                if (searchTerm) {
                    $clearBtn.show();
                } else {
                    $clearBtn.hide();
                }
                
                // If AJAX not enabled, don't do anything (page reload on submit)
                if (!ajaxEnabled) {
                    return;
                }
                
                // Debounce 500ms
                searchTimeout = setTimeout(function() {
                    SawAdminTable.performSearch($input, entity, searchTerm);
                }, 500);
            });
            
            // Submit on Enter (for non-AJAX mode)
            $(document).on('keypress', '.saw-search-input', function(e) {
                if (e.which === 13) { // Enter
                    const ajaxEnabled = $(this).data('ajax-enabled') === 1;
                    if (!ajaxEnabled) {
                        // Trigger page reload with search parameter
                        const searchTerm = $(this).val().trim();
                        const currentUrl = new URL(window.location.href);
                        if (searchTerm) {
                            currentUrl.searchParams.set('s', searchTerm);
                        } else {
                            currentUrl.searchParams.delete('s');
                        }
                        currentUrl.searchParams.delete('paged'); // Reset to page 1
                        window.location.href = currentUrl.toString();
                    }
                }
            });
        },
        
        /**
         * Perform AJAX search
         */
        performSearch: function($input, entity, searchTerm) {
            console.log('SAW Admin Table: Performing search', {
                entity: entity,
                search: searchTerm
            });
            
            // Trigger custom event pro AJAX handler
            $(document).trigger('saw:admin-table:search', {
                entity: entity,
                action: $input.data('ajax-action'),
                search: searchTerm
            });
        },
        
        /**
         * Bind clear button
         */
        bindSearchClear: function() {
            $(document).on('click', '#saw-search-clear', function() {
                const $input = $(this).siblings('.saw-search-input');
                const entity = $input.data('entity');
                const ajaxEnabled = $input.data('ajax-enabled') === 1;
                
                $input.val('').focus();
                $(this).hide();
                
                console.log('SAW Admin Table: Search cleared');
                
                if (ajaxEnabled) {
                    // Trigger AJAX search with empty term
                    SawAdminTable.performSearch($input, entity, '');
                } else {
                    // Reload page without search param
                    const currentUrl = new URL(window.location.href);
                    currentUrl.searchParams.delete('s');
                    currentUrl.searchParams.delete('paged');
                    window.location.href = currentUrl.toString();
                }
            });
        },
        
        /**
         * Bind sort headers (AJAX mode)
         */
        bindSortHeaders: function() {
            $(document).on('click', '.saw-table-sortable thead th.saw-sortable a', function(e) {
                const $link = $(this);
                const $th = $link.closest('th');
                const $input = $('.saw-search-input');
                const ajaxEnabled = $input.data('ajax-enabled') === 1;
                
                // If AJAX enabled, prevent default and trigger AJAX sort
                if (ajaxEnabled) {
                    e.preventDefault();
                    
                    const column = $link.data('column');
                    const entity = $input.data('entity');
                    
                    // Determine new order
                    let newOrder = 'ASC';
                    if ($th.find('.saw-sort-asc').length) {
                        newOrder = 'DESC';
                    }
                    
                    console.log('SAW Admin Table: Sorting', {
                        entity: entity,
                        column: column,
                        order: newOrder
                    });
                    
                    // Trigger custom event pro AJAX handler
                    $(document).trigger('saw:admin-table:sort', {
                        entity: entity,
                        action: $input.data('ajax-action'),
                        column: column,
                        order: newOrder
                    });
                }
                // Else allow default link behavior (page reload)
            });
        },
        
        /**
         * Bind delete buttons
         */
        bindDeleteButtons: function() {
            $(document).on('click', '[class*="saw-delete-"]', function(e) {
                e.preventDefault();
                
                const $btn = $(this);
                const classes = $btn.attr('class').split(' ');
                let entity = '';
                
                // Extract entity from class (saw-delete-customers -> customers)
                for (let i = 0; i < classes.length; i++) {
                    if (classes[i].startsWith('saw-delete-')) {
                        entity = classes[i].replace('saw-delete-', '');
                        break;
                    }
                }
                
                const id = $btn.data(entity + '-id');
                const name = $btn.data(entity + '-name');
                
                if (!confirm('Opravdu chcete smazat "' + name + '"?\n\nTato akce je nevratná!')) {
                    return;
                }
                
                console.log('SAW Admin Table: Delete clicked', {
                    entity: entity,
                    id: id,
                    name: name
                });
                
                // Trigger custom event pro AJAX handler
                $(document).trigger('saw:admin-table:delete', {
                    entity: entity,
                    id: id,
                    name: name,
                    $button: $btn
                });
            });
        },
        
        /**
         * Bind alert close buttons
         */
        bindAlertClose: function() {
            $(document).on('click', '.saw-alert-close', function() {
                $(this).closest('.saw-alert').fadeOut(300);
            });
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        SawAdminTable.init();
    });
    
    // Export to global scope
    window.SawAdminTable = SawAdminTable;
    
})(jQuery);