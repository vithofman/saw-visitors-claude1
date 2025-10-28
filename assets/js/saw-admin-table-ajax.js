/**
 * SAW Admin Table - AJAX Handler (FIXED)
 * 
 * ✅ OPRAVENO:
 * - Správně detekuje AJAX enabled mode
 * - Event listenery pro search, sort, pagination
 * - Správná nonce validace
 * - Debug console.log statements
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
        isAjaxEnabled: false,
        
        /**
         * Initialize
         */
        init: function() {
            console.log('🔧 SAW Admin Table AJAX: Starting initialization...');
            
            // Check if AJAX is enabled for this page
            const $searchInput = $('.saw-search-input');
            if ($searchInput.length > 0) {
                this.isAjaxEnabled = $searchInput.data('ajax-enabled') === 1;
                console.log('🔧 AJAX enabled:', this.isAjaxEnabled);
            }
            
            // Only bind if AJAX is enabled
            if (!this.isAjaxEnabled) {
                console.log('⚠️ SAW Admin Table AJAX: AJAX not enabled, skipping initialization');
                return;
            }
            
            // Initialize state from URL
            this.initFromURL();
            
            // Bind events
            this.bindSearchEvent();
            this.bindSortEvent();
            this.bindDeleteEvent();
            this.bindPaginationEvent();
            
            console.log('✅ SAW Admin Table AJAX: Initialized successfully');
            console.log('   - Current search:', this.currentSearch);
            console.log('   - Current page:', this.currentPage);
            console.log('   - Current orderby:', this.currentOrderBy);
            console.log('   - Current order:', this.currentOrder);
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
         * Listen for search event from saw-admin-table.js
         */
        bindSearchEvent: function() {
            $(document).on('saw:admin-table:search', (e, data) => {
                console.log('🔍 SAW Admin Table AJAX: Search event received', data);
                this.handleSearch(data);
            });
        },
        
        /**
         * Handle search AJAX
         */
        handleSearch: function(data) {
            console.log('🔍 SAW Admin Table AJAX: Handling search', data);
            
            this.currentSearch = data.search;
            this.currentPage = 1; // Reset to page 1
            
            this.performRequest(data.entity, data.action);
        },
        
        /**
         * Listen for sort event from saw-admin-table.js
         */
        bindSortEvent: function() {
            $(document).on('saw:admin-table:sort', (e, data) => {
                console.log('📊 SAW Admin Table AJAX: Sort event received', data);
                this.handleSort(data);
            });
        },
        
        /**
         * Handle sort AJAX
         */
        handleSort: function(data) {
            console.log('📊 SAW Admin Table AJAX: Handling sort', data);
            
            this.currentOrderBy = data.column;
            this.currentOrder = data.order;
            this.currentPage = 1; // Reset to page 1
            
            this.performRequest(data.entity, data.action);
        },
        
        /**
         * Listen for pagination clicks
         */
        bindPaginationEvent: function() {
            $(document).on('click', '[id*="-pagination"] a.saw-pagination-link', function(e) {
                const $link = $(this);
                const $pagination = $link.closest('[id*="-pagination"]');
                const entity = $pagination.attr('id').replace('saw-', '').replace('-pagination', '');
                const $input = $('.saw-search-input[data-entity="' + entity + '"]');
                
                // Only handle if AJAX is enabled
                if ($input.length === 0 || $input.data('ajax-enabled') !== 1) {
                    return; // Let normal link work
                }
                
                e.preventDefault();
                
                const page = parseInt($link.data('page'));
                
                console.log('📄 SAW Admin Table AJAX: Pagination clicked', {
                    entity: entity,
                    page: page
                });
                
                SawAdminTableAjax.currentPage = page;
                SawAdminTableAjax.performRequest(entity, $input.data('ajax-action'));
            });
        },
        
        /**
         * Perform AJAX request
         */
        performRequest: function(entity, action) {
            // Validate inputs
            if (!entity || !action) {
                console.error('❌ SAW Admin Table AJAX: Missing entity or action', {entity, action});
                return;
            }
            
            // Check if sawAdminTableAjax exists
            if (typeof sawAdminTableAjax === 'undefined') {
                console.error('❌ SAW Admin Table AJAX: sawAdminTableAjax object not found!');
                console.error('   This means wp_localize_script was not called properly.');
                return;
            }
            
            const $container = $('#saw-' + entity + '-container');
            const $loading = $('#saw-' + entity + '-loading');
            const $spinner = $('.saw-search-spinner');
            
            console.log('🚀 SAW Admin Table AJAX: Performing request', {
                entity: entity,
                action: action,
                search: this.currentSearch,
                page: this.currentPage,
                orderby: this.currentOrderBy,
                order: this.currentOrder,
                ajaxurl: sawAdminTableAjax.ajaxurl,
                nonce: sawAdminTableAjax.nonce
            });
            
            // Show loading
            if ($loading.length > 0) {
                $loading.fadeIn(200);
            }
            if ($spinner.length > 0) {
                $spinner.show();
            }
            
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
                success: (response) => {
                    console.log('✅ SAW Admin Table AJAX: Success response', response);
                    
                    if (response.success) {
                        // Pro nyní jen reload page
                        // V budoucnu můžeme udělat pure JS update
                        console.log('✅ Reloading page with new parameters...');
                        this.updateURL();
                        window.location.reload();
                    } else {
                        console.error('❌ SAW Admin Table AJAX: Error response', response.data);
                        alert('Chyba: ' + (response.data.message || 'Neznámá chyba'));
                    }
                },
                error: (xhr, status, error) => {
                    console.error('❌ SAW Admin Table AJAX: Request failed', {xhr, status, error});
                    console.error('Response text:', xhr.responseText);
                    alert('Došlo k chybě při komunikaci se serverem.');
                },
                complete: () => {
                    // Hide loading
                    if ($loading.length > 0) {
                        $loading.fadeOut(200);
                    }
                    if ($spinner.length > 0) {
                        $spinner.hide();
                    }
                }
            });
        },
        
        /**
         * Update URL without reload
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
            
            console.log('🔗 Updating URL to:', url.toString());
            window.history.pushState({}, '', url);
        },
        
        /**
         * Listen for delete event
         */
        bindDeleteEvent: function() {
            $(document).on('saw:admin-table:delete', (e, data) => {
                console.log('🗑️ SAW Admin Table AJAX: Delete event received', data);
                this.handleDelete(data);
            });
        },
        
        /**
         * Handle delete AJAX
         */
        handleDelete: function(data) {
            console.log('🗑️ SAW Admin Table AJAX: Handling delete', data);
            
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
                        console.log('✅ SAW Admin Table AJAX: Delete success');
                        
                        // Fade out row
                        $row.fadeOut(300, function() {
                            $(this).remove();
                            
                            // Reload if no more rows
                            const $tbody = $row.closest('tbody');
                            if ($tbody.find('tr').length === 0) {
                                window.location.reload();
                            }
                        });
                    } else {
                        console.error('❌ SAW Admin Table AJAX: Delete failed', response.data);
                        alert('Chyba při mazání: ' + (response.data.message || 'Neznámá chyba'));
                        $button.prop('disabled', false).css('opacity', 1);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ SAW Admin Table AJAX: Delete request failed', error);
                    alert('Došlo k chybě při mazání.');
                    $button.prop('disabled', false).css('opacity', 1);
                }
            });
        }
    };
    
    // Initialize when ready
    $(document).ready(function() {
        console.log('📦 SAW Admin Table AJAX: DOM ready, initializing...');
        SawAdminTableAjax.init();
    });
    
})(jQuery);