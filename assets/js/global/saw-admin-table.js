/**
 * SAW Admin Table - Global Component JavaScript
 * 
 * Universal table component functionality for any entity
 * Provides search, sort, delete, and alert handling
 * 
 * @package SAW_Visitors
 * @since   4.6.1
 * @version FIXED - Syntax error corrected (missing comma)
 */

(function($) {
    'use strict';
    
    const SawAdminTable = {
        
        /**
         * Initialize all table functionality
         */
        init: function() {
            this.bindSearchInput();
            this.bindSearchClear();
            this.bindSortHeaders();
            this.bindDeleteButtons();
            this.bindAlertClose();
            this.bindClickableRows();
            
            console.log('SAW Admin Table: Initialized');
        },
        
        /**
         * Bind search input with debouncing
         */
        bindSearchInput: function() {
            let searchTimeout = null;
            
            $(document).on('input', '.saw-search-input', function() {
                clearTimeout(searchTimeout);
                
                const $input = $(this);
                const searchTerm = $input.val().trim();
                const entity = $input.data('entity');
                const ajaxEnabled = $input.data('ajax-enabled') === 1;
                
                const $clearBtn = $('#saw-search-clear');
                if (searchTerm) {
                    $clearBtn.show();
                } else {
                    $clearBtn.hide();
                }
                
                if (!ajaxEnabled) {
                    return;
                }
                
                searchTimeout = setTimeout(function() {
                    SawAdminTable.performSearch($input, entity, searchTerm);
                }, 500);
            });
            
            $(document).on('keypress', '.saw-search-input', function(e) {
                if (e.which === 13) {
                    const ajaxEnabled = $(this).data('ajax-enabled') === 1;
                    if (!ajaxEnabled) {
                        const searchTerm = $(this).val().trim();
                        const currentUrl = new URL(window.location.href);
                        if (searchTerm) {
                            currentUrl.searchParams.set('s', searchTerm);
                        } else {
                            currentUrl.searchParams.delete('s');
                        }
                        currentUrl.searchParams.delete('paged');
                        window.location.href = currentUrl.toString();
                    }
                }
            });
        },
        
        /**
         * Perform AJAX search request
         */
        performSearch: function($input, entity, searchTerm) {
            console.log('SAW Admin Table: Performing search', {
                entity: entity,
                search: searchTerm
            });
            
            $(document).trigger('saw:admin-table:search', {
                entity: entity,
                action: $input.data('ajax-action'),
                search: searchTerm
            });
        },
        
        /**
         * Bind clear button functionality
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
                    SawAdminTable.performSearch($input, entity, '');
                } else {
                    const currentUrl = new URL(window.location.href);
                    currentUrl.searchParams.delete('s');
                    currentUrl.searchParams.delete('paged');
                    window.location.href = currentUrl.toString();
                }
            });
        },
        
        /**
         * Bind sortable column headers
         */
        bindSortHeaders: function() {
            $(document).on('click', '.saw-table-sortable thead th.saw-sortable a', function(e) {
                const $link = $(this);
                const $th = $link.closest('th');
                const $input = $('.saw-search-input');
                
                if ($input.length === 0) {
                    console.log('SAW Admin Table: No search input found, allowing normal link behavior');
                    return;
                }
                
                const ajaxEnabled = $input.data('ajax-enabled') === 1;
                
                console.log('SAW Admin Table: Sort clicked', {
                    ajaxEnabled: ajaxEnabled,
                    href: $link.attr('href')
                });
                
                if (ajaxEnabled) {
                    e.preventDefault();
                    
                    const column = $link.data('column');
                    const entity = $input.data('entity');
                    
                    let newOrder = 'ASC';
                    if ($th.find('.saw-sort-asc').length) {
                        newOrder = 'DESC';
                    }
                    
                    console.log('SAW Admin Table: Sorting via AJAX', {
                        entity: entity,
                        column: column,
                        order: newOrder
                    });
                    
                    $(document).trigger('saw:admin-table:sort', {
                        entity: entity,
                        action: $input.data('ajax-action'),
                        column: column,
                        order: newOrder
                    });
                } else {
                    console.log('SAW Admin Table: AJAX not enabled, following link normally');
                }
            });
        },
        
        /**
         * Bind delete button functionality
         */
        bindDeleteButtons: function() {
            $(document).on('click', '[class*="saw-delete-"]', function(e) {
                e.preventDefault();
                
                const $btn = $(this);
                const classes = $btn.attr('class').split(' ');
                let entity = '';
                
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
        },
        
        /**
         * Bind klikatelné řádky pro otevření modalu
         */
        bindClickableRows: function() {
            $(document).on('click', '.saw-row-clickable', function(e) {
                if ($(e.target).closest('.saw-action-buttons').length > 0) {
                    return;
                }
                
                const $row = $(this);
                const entity = $row.data('entity');
                const customerId = $row.data('id');
                
                console.log('SAW Admin Table: Row clicked', {
                    entity: entity,
                    id: customerId
                });
                
                $(document).trigger('saw:table:row-click', {
                    entity: entity,
                    id: customerId,
                    rowData: $row.data('row-data')
                });
            });
        }
    };
    
    $(document).ready(function() {
        SawAdminTable.init();
    });
    
    $(document).on('saw:scripts-reinitialized', function() {
        console.log('🔄 SAW Admin Table: Reinitializing after navigation...');
        SawAdminTable.init();
    });
    
    window.SawAdminTable = SawAdminTable;
    
})(jQuery);