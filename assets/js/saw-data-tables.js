/**
 * SAW Data Tables - Universal JavaScript for AJAX tables
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 */

class SAWDataTable {
    constructor(tableId, config = {}) {
        this.tableId = tableId;
        this.config = Object.assign({
            ajaxAction: 'saw_search_' + tableId,
            perPage: 20,
            currentSearch: '',
            currentOrderBy: 'id',
            currentOrder: 'DESC',
            searchDebounce: 500
        }, config);
        
        // Current state
        this.currentPage = 1;
        this.currentSearch = this.config.currentSearch;
        this.currentOrderBy = this.config.currentOrderBy;
        this.currentOrder = this.config.currentOrder;
        this.searchTimeout = null;
        
        // DOM elements
        this.$container = jQuery('#saw-' + tableId + '-container');
        this.$loading = jQuery('#saw-' + tableId + '-loading');
        this.$count = jQuery('#saw-' + tableId + '-count');
        this.$tbody = jQuery('#saw-' + tableId + '-tbody');
        this.$searchInput = jQuery('#saw-' + tableId + '-search');
        this.$searchClear = jQuery('#saw-' + tableId + '-search-clear');
        this.$searchSpinner = jQuery('.saw-search-spinner');
        this.$pagination = jQuery('#saw-' + tableId + '-pagination');
        
        // Initialize
        this.init();
        
        console.log('SAWDataTable initialized:', tableId, this.config);
    }
    
    /**
     * Initialize event handlers
     */
    init() {
        this.initSearch();
        this.initSorting();
        this.initPagination();
    }
    
    /**
     * Initialize search functionality
     */
    initSearch() {
        const self = this;
        
        // Search input with debounce
        this.$searchInput.on('input', function() {
            const query = jQuery(this).val().trim();
            
            // Show/hide clear button
            self.$searchClear.css('display', query ? 'flex' : 'none');
            
            // Show spinner
            self.$searchSpinner.show();
            
            // Debounce search
            clearTimeout(self.searchTimeout);
            self.searchTimeout = setTimeout(function() {
                self.currentSearch = query;
                self.currentPage = 1;
                console.log('Search query:', query);
                self.performSearch();
            }, self.config.searchDebounce);
        });
        
        // Clear button
        this.$searchClear.on('click', function() {
            console.log('Clear button clicked');
            self.$searchInput.val('').trigger('input').focus();
        });
        
        // Prevent form submission on Enter
        this.$searchInput.on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(self.searchTimeout);
                self.currentSearch = jQuery(this).val().trim();
                self.currentPage = 1;
                console.log('Enter pressed, immediate search');
                self.performSearch();
            }
        });
    }
    
    /**
     * Initialize table sorting
     */
    initSorting() {
        const self = this;
        
        jQuery(document).on('click', '.saw-sortable a', function(e) {
            e.preventDefault();
            
            const $th = jQuery(this).closest('th');
            const column = $th.data('column');
            
            console.log('Sort clicked on column:', column);
            
            // Toggle order if same column
            if (self.currentOrderBy === column) {
                self.currentOrder = self.currentOrder === 'ASC' ? 'DESC' : 'ASC';
            } else {
                self.currentOrderBy = column;
                self.currentOrder = 'ASC';
            }
            
            self.currentPage = 1;
            self.performSearch();
        });
    }
    
    /**
     * Initialize pagination
     */
    initPagination() {
        const self = this;
        
        jQuery(document).on('click', '#saw-' + this.tableId + '-pagination .saw-pagination-btn:not(.disabled):not(.active)', function(e) {
            e.preventDefault();
            
            const page = parseInt(jQuery(this).data('page'));
            console.log('Pagination clicked, page:', page);
            
            if (page && page !== self.currentPage) {
                self.currentPage = page;
                self.performSearch();
                
                // Scroll to top
                jQuery('html, body').animate({
                    scrollTop: self.$container.offset().top - 100
                }, 400);
            }
        });
    }
    
    /**
     * Perform AJAX search
     */
    performSearch() {
        const self = this;
        
        console.log('Performing AJAX search:', {
            search: self.currentSearch,
            page: self.currentPage,
            orderby: self.currentOrderBy,
            order: self.currentOrder
        });
        
        // Show loading
        self.$loading.fadeIn(200);
        self.$searchSpinner.show();
        
        // AJAX request
        jQuery.ajax({
            url: sawDataTables.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: self.config.ajaxAction,
                nonce: sawDataTables.nonce,
                search: self.currentSearch,
                page: self.currentPage,
                orderby: self.currentOrderBy,
                order: self.currentOrder
            },
            success: function(response) {
                console.log('AJAX Response:', response);
                
                if (response.success && response.data) {
                    self.updateTable(response.data);
                } else {
                    console.error('AJAX error:', response.data ? response.data.message : 'Unknown error');
                    self.showError(response.data ? response.data.message : 'Došlo k chybě při načítání dat.');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX request failed:', status, error);
                self.showError('Došlo k chybě při komunikaci se serverem.');
            },
            complete: function() {
                // Hide loading
                self.$loading.fadeOut(200);
                self.$searchSpinner.hide();
            }
        });
    }
    
    /**
     * Update table with new data
     */
    updateTable(data) {
        console.log('Updating table with data:', data);
        
        // Update count
        if (this.$count.length) {
            this.$count.text(data.total);
        }
        
        // Check if we have items
        if (!data.items || data.items.length === 0) {
            this.showEmptyState();
            return;
        }
        
        // Update tbody
        if (this.$tbody.length) {
            const rowsHTML = this.buildRowsHTML(data.items);
            this.$tbody.html(rowsHTML);
            console.log('Table updated');
        } else {
            // Rebuild entire table if tbody doesn't exist
            this.rebuildTable(data);
        }
        
        // Update pagination
        this.updatePagination(data.current_page, data.total_pages);
    }
    
    /**
     * Build rows HTML (must be implemented by specific table)
     * Override this method in specific table implementations
     */
    buildRowsHTML(items) {
        console.warn('buildRowsHTML not implemented for table:', this.tableId);
        return '';
    }
    
    /**
     * Rebuild entire table
     */
    rebuildTable(data) {
        console.log('Rebuilding entire table');
        
        const tableHTML = this.buildTableHTML(data);
        this.$container.html(tableHTML);
        this.$tbody = jQuery('#saw-' + this.tableId + '-tbody');
    }
    
    /**
     * Build table HTML (must be implemented by specific table)
     * Override this method in specific table implementations
     */
    buildTableHTML(data) {
        console.warn('buildTableHTML not implemented for table:', this.tableId);
        return '<p>Tabulka není dostupná.</p>';
    }
    
    /**
     * Show empty state
     */
    showEmptyState() {
        const isSearch = this.currentSearch !== '';
        const emptyHTML = `
            <div class="saw-empty-state">
                <span class="dashicons dashicons-list-view"></span>
                <h3>Žádné záznamy</h3>
                <p>${isSearch ? 'Nebyli nalezeni žádné záznamy odpovídající hledanému výrazu.' : 'Zatím nemáte žádné záznamy.'}</p>
            </div>
        `;
        
        this.$container.html(emptyHTML);
        
        // Hide pagination
        if (this.$pagination.length) {
            this.$pagination.hide();
        }
    }
    
    /**
     * Show error message
     */
    showError(message) {
        const errorHTML = `
            <div class="saw-empty-state">
                <span class="dashicons dashicons-warning" style="color: #dc2626;"></span>
                <h3>Chyba</h3>
                <p>${message}</p>
            </div>
        `;
        
        this.$container.html(errorHTML);
    }
    
    /**
     * Update pagination
     */
    updatePagination(currentPage, totalPages) {
        if (!this.$pagination.length) {
            return;
        }
        
        if (totalPages <= 1) {
            this.$pagination.hide();
            return;
        }
        
        this.$pagination.show();
        
        // Update Previous button
        const $prev = this.$pagination.find('.saw-pagination-prev');
        if (currentPage <= 1) {
            $prev.addClass('disabled').attr('disabled', true);
        } else {
            $prev.removeClass('disabled').removeAttr('disabled').data('page', currentPage - 1);
        }
        
        // Update Next button
        const $next = this.$pagination.find('.saw-pagination-next');
        if (currentPage >= totalPages) {
            $next.addClass('disabled').attr('disabled', true);
        } else {
            $next.removeClass('disabled').removeAttr('disabled').data('page', currentPage + 1);
        }
        
        // Update page buttons
        const $pages = this.$pagination.find('.saw-pagination-pages');
        $pages.html(this.buildPaginationPages(currentPage, totalPages));
    }
    
    /**
     * Build pagination pages HTML
     */
    buildPaginationPages(currentPage, totalPages) {
        let html = '';
        
        const start = Math.max(1, currentPage - 2);
        const end = Math.min(totalPages, currentPage + 2);
        
        // First page
        if (start > 1) {
            html += '<button type="button" class="saw-pagination-btn" data-page="1">1</button>';
            if (start > 2) {
                html += '<span class="saw-pagination-dots">...</span>';
            }
        }
        
        // Page range
        for (let i = start; i <= end; i++) {
            const active = i === currentPage ? 'active' : '';
            html += `<button type="button" class="saw-pagination-btn ${active}" data-page="${i}">${i}</button>`;
        }
        
        // Last page
        if (end < totalPages) {
            if (end < totalPages - 1) {
                html += '<span class="saw-pagination-dots">...</span>';
            }
            html += `<button type="button" class="saw-pagination-btn" data-page="${totalPages}">${totalPages}</button>`;
        }
        
        return html;
    }
    
    /**
     * Get sort icon HTML
     */
    getSortIcon(column) {
        if (this.currentOrderBy !== column) {
            return '<span class="saw-sort-icon">⇅</span>';
        }
        
        return this.currentOrder === 'ASC'
            ? '<span class="saw-sort-icon saw-sort-asc">▲</span>'
            : '<span class="saw-sort-icon saw-sort-desc">▼</span>';
    }
}

// Make SAWDataTable available globally
window.SAWDataTable = SAWDataTable;