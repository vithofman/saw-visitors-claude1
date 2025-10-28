/**
 * SAW Admin Table - AJAX Handler
 * 
 * Kopíruje funkcionalitu z saw-customers-ajax.js
 * Zpracovává AJAX requesty pro admin tabulky
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 */

(function($) {
    'use strict';
    
    const SawAdminTableAjax = {
        
        // Current state
        currentSearch: '',
        currentPage: 1,
        currentOrderBy: '',
        currentOrder: 'ASC',
        
        /**
         * Initialize
         */
        init: function() {
            this.bindSearchEvent();
            this.bindSortEvent();
            this.bindDeleteEvent();
            this.bindPaginationEvent();
            
            // Initialize from URL params
            this.initFromURL();
            
            console.log('SAW Admin Table AJAX: Initialized');
        },
        
        /**
         * Initialize from URL parameters
         */
        initFromURL: function() {
            const urlParams = new URLSearchParams(window.location.search);
            this.currentSearch = urlParams.get('s') || '';
            this.currentPage = parseInt(urlParams.get('paged')) || 1;
            this.currentOrderBy = urlParams.get('orderby') || '';
            this.currentOrder = urlParams.get('order') || 'ASC';
        },
        
        /**
         * Listen for search event
         */
        bindSearchEvent: function() {
            $(document).on('saw:admin-table:search', function(e, data) {
                SawAdminTableAjax.handleSearch(data);
            });
        },
        
        /**
         * Handle search AJAX
         */
        handleSearch: function(data) {
            console.log('SAW Admin Table AJAX: Handling search', data);
            
            this.currentSearch = data.search;
            this.currentPage = 1; // Reset to page 1
            
            this.performRequest(data.entity, data.action);
        },
        
        /**
         * Listen for sort event
         */
        bindSortEvent: function() {
            $(document).on('saw:admin-table:sort', function(e, data) {
                SawAdminTableAjax.handleSort(data);
            });
        },
        
        /**
         * Handle sort AJAX
         */
        handleSort: function(data) {
            console.log('SAW Admin Table AJAX: Handling sort', data);
            
            this.currentOrderBy = data.column;
            this.currentOrder = data.order;
            this.currentPage = 1; // Reset to page 1
            
            this.performRequest(data.entity, data.action);
        },
        
        /**
         * Listen for pagination event
         */
        bindPaginationEvent: function() {
            $(document).on('click', '[id*="-pagination"] a.saw-pagination-link', function(e) {
                const $link = $(this);
                const $pagination = $link.closest('[id*="-pagination"]');
                const entity = $pagination.attr('id').replace('saw-', '').replace('-pagination', '');
                const $input = $('.saw-search-input[data-entity="' + entity + '"]');
                const ajaxEnabled = $input.data('ajax-enabled') === 1;
                
                if (ajaxEnabled) {
                    e.preventDefault();
                    
                    const page = parseInt($link.data('page'));
                    
                    console.log('SAW Admin Table AJAX: Pagination clicked', {
                        entity: entity,
                        page: page
                    });
                    
                    SawAdminTableAjax.currentPage = page;
                    SawAdminTableAjax.performRequest(entity, $input.data('ajax-action'));
                }
            });
        },
        
        /**
         * Perform AJAX request
         */
        performRequest: function(entity, action) {
            const $container = $('#saw-' + entity + '-container');
            const $loading = $('#saw-' + entity + '-loading');
            const $spinner = $('.saw-search-spinner');
            
            console.log('SAW Admin Table AJAX: Performing request', {
                entity: entity,
                action: action,
                search: this.currentSearch,
                page: this.currentPage,
                orderby: this.currentOrderBy,
                order: this.currentOrder
            });
            
            // Show loading
            $loading.fadeIn(200);
            $spinner.show();
            
            $.ajax({
                url: sawAdminTableAjax.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: action,
                    nonce: sawAdminTableAjax.nonce,
                    entity: entity,
                    search: this.currentSearch,
                    page: this.currentPage,
                    orderby: this.currentOrderBy,
                    order: this.currentOrder
                },
                success: function(response) {
                    console.log('SAW Admin Table AJAX: Success', response);
                    
                    if (response.success) {
                        SawAdminTableAjax.updateTable(entity, response.data);
                        SawAdminTableAjax.updateURL();
                    } else {
                        console.error('SAW Admin Table AJAX: Error', response.data);
                        alert('Chyba: ' + (response.data.message || 'Neznámá chyba'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('SAW Admin Table AJAX: Request failed', error);
                    alert('Došlo k chybě při komunikaci se serverem.');
                },
                complete: function() {
                    $loading.fadeOut(200);
                    $spinner.hide();
                }
            });
        },
        
        /**
         * Update table HTML
         */
        updateTable: function(entity, data) {
            console.log('SAW Admin Table AJAX: Updating table', entity, data);
            
            // Pro nyní jen reload page (v budoucnu můžeme udělat pure JS update)
            window.location.reload();
        },
        
        /**
         * Update URL bez reloadu
         */
        updateURL: function() {
            const url = new URL(window.location.href);
            
            if (this.currentSearch) {
                url.searchParams.set('s', this.currentSearch);
            } else {
                url.searchParams.delete('s');
            }
            
            if (this.currentPage > 1) {
                url.searchParams.set('paged', this.currentPage);
            } else {
                url.searchParams.delete('paged');
            }
            
            if (this.currentOrderBy) {
                url.searchParams.set('orderby', this.currentOrderBy);
                url.searchParams.set('order', this.currentOrder);
            } else {
                url.searchParams.delete('orderby');
                url.searchParams.delete('order');
            }
            
            window.history.pushState({}, '', url);
        },
        
        /**
         * Listen for delete event
         */
        bindDeleteEvent: function() {
            $(document).on('saw:admin-table:delete', function(e, data) {
                SawAdminTableAjax.handleDelete(data);
            });
        },
        
        /**
         * Handle delete AJAX
         */
        handleDelete: function(data) {
            console.log('SAW Admin Table AJAX: Handling delete', data);
            
            const $button = data.$button;
            const $row = $button.closest('tr');
            
            $.ajax({
                url: sawAdminTableAjax.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'saw_admin_table_delete',
                    nonce: sawAdminTableAjax.nonce,
                    entity: data.entity,
                    id: data.id
                },
                beforeSend: function() {
                    $button.prop('disabled', true).css('opacity', 0.5);
                },
                success: function(response) {
                    if (response.success) {
                        console.log('SAW Admin Table AJAX: Delete success');
                        
                        // Fade out row
                        $row.fadeOut(300, function() {
                            $(this).remove();
                            
                            // Update count
                            const $count = $('#saw-' + data.entity + '-count');
                            const currentCount = parseInt($count.text());
                            $count.text(currentCount - 1);
                            
                            // If no more rows, reload page
                            const remainingRows = $('#saw-' + data.entity + '-tbody tr').length;
                            if (remainingRows === 0) {
                                window.location.reload();
                            }
                        });
                    } else {
                        console.error('SAW Admin Table AJAX: Delete error', response.data);
                        alert('Chyba při mazání: ' + (response.data.message || 'Neznámá chyba'));
                        $button.prop('disabled', false).css('opacity', 1);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('SAW Admin Table AJAX: Delete request failed', error);
                    alert('Došlo k chybě při komunikaci se serverem.');
                    $button.prop('disabled', false).css('opacity', 1);
                }
            });
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        SawAdminTableAjax.init();
    });
    
    // Export to global scope
    window.SawAdminTableAjax = SawAdminTableAjax;
    
})(jQuery);