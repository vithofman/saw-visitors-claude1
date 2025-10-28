/**
 * SAW Customers AJAX - Live Search & Sorting
 * @package SAW_Visitors
 * @version 4.6.1
 */

(function($) {
    'use strict';
    
    let searchTimeout = null;
    let currentSearch = '';
    let currentPage = 1;
    let currentOrderBy = 'name';
    let currentOrder = 'ASC';
    
    $(document).ready(function() {
        initSearchInput();
        initSorting();
        initPagination();
    });
    
    /**
     * Initialize search input with debounce
     */
    function initSearchInput() {
        const $searchInput = $('#saw-customers-search');
        const $clearBtn = $('#saw-search-clear');
        
        if (!$searchInput.length) return;
        
        // Live search with debounce
        $searchInput.on('input', function() {
            const searchValue = $(this).val().trim();
            
            // Show/hide clear button
            if (searchValue.length > 0) {
                $clearBtn.show();
            } else {
                $clearBtn.hide();
            }
            
            // Clear previous timeout
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            
            // Set new timeout (500ms debounce)
            searchTimeout = setTimeout(function() {
                currentSearch = searchValue;
                currentPage = 1; // Reset to page 1
                performSearch();
            }, 500);
        });
        
        // Clear button
        $clearBtn.on('click', function() {
            $searchInput.val('').trigger('input').focus();
        });
        
        // Prevent form submission on Enter
        $searchInput.on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(searchTimeout);
                currentSearch = $(this).val().trim();
                currentPage = 1;
                performSearch();
            }
        });
    }
    
    /**
     * Initialize table sorting
     */
    function initSorting() {
        $(document).on('click', '.saw-sortable a', function(e) {
            e.preventDefault();
            
            const $th = $(this).closest('th');
            const column = $th.data('column');
            
            // Toggle order if same column
            if (currentOrderBy === column) {
                currentOrder = currentOrder === 'ASC' ? 'DESC' : 'ASC';
            } else {
                currentOrderBy = column;
                currentOrder = 'ASC';
            }
            
            currentPage = 1; // Reset to page 1
            performSearch();
        });
    }
    
    /**
     * Initialize pagination
     */
    function initPagination() {
        $(document).on('click', '#saw-customers-pagination a.saw-pagination-link', function(e) {
            e.preventDefault();
            
            const page = parseInt($(this).data('page'));
            if (page && page > 0) {
                currentPage = page;
                performSearch();
            }
        });
    }
    
    /**
     * Perform AJAX search
     */
    function performSearch() {
        const $container = $('#saw-customers-container');
        const $loading = $('#saw-customers-loading');
        const $spinner = $('.saw-search-spinner');
        
        // Show loading
        $loading.fadeIn(200);
        $spinner.show();
        
        $.ajax({
            url: sawCustomersAjax.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'saw_search_customers',
                nonce: sawCustomersAjax.nonce,
                search: currentSearch,
                page: currentPage,
                orderby: currentOrderBy,
                order: currentOrder
            },
            success: function(response) {
                if (response.success) {
                    updateCustomersTable(response.data);
                    updatePagination(response.data);
                    updateCustomersCount(response.data.total_customers);
                    
                    // Update URL without reload
                    updateURL();
                } else {
                    showError(response.data.message || 'Chyba při načítání zákazníků.');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                showError('Došlo k chybě při komunikaci se serverem.');
            },
            complete: function() {
                $loading.fadeOut(200);
                $spinner.hide();
            }
        });
    }
    
    /**
     * Update customers table
     */
    function updateCustomersTable(data) {
        const $tbody = $('#saw-customers-tbody');
        const $container = $('#saw-customers-container');
        
        if (!data.customers || data.customers.length === 0) {
            // Show empty state
            const emptyMessage = currentSearch 
                ? 'Nebyli nalezeni žádní zákazníci odpovídající hledanému výrazu.'
                : 'Zatím nemáte žádné zákazníky. Klikněte na tlačítko výše pro přidání prvního zákazníka.';
            
            $container.html(`
                <div class="saw-empty-state">
                    <span class="dashicons dashicons-groups"></span>
                    <h3>Žádní zákazníci</h3>
                    <p>${emptyMessage}</p>
                </div>
            `);
            return;
        }
        
        // Build rows HTML
        let rowsHTML = '';
        data.customers.forEach(function(customer) {
            const logoHTML = customer.logo_url_full 
                ? `<img src="${escapeHtml(customer.logo_url_full)}" alt="${escapeHtml(customer.name)}" class="saw-customer-logo">`
                : '<div class="saw-customer-logo-placeholder"><span class="dashicons dashicons-building"></span></div>';
            
            const notesHTML = customer.notes 
                ? `<br><small class="saw-text-muted">${escapeHtml(wpTrimWords(customer.notes, 10))}</small>`
                : '';
            
            const addressHTML = customer.address 
                ? `<small>${nl2br(escapeHtml(customer.address))}</small>`
                : '<span class="saw-text-muted">—</span>';
            
            const icoHTML = customer.ico || '—';
            const colorHTML = customer.primary_color || '#1e40af';
            
            rowsHTML += `
                <tr data-customer-id="${customer.id}">
                    <td>${logoHTML}</td>
                    <td>
                        <strong>${escapeHtml(customer.name)}</strong>
                        ${notesHTML}
                    </td>
                    <td>${escapeHtml(icoHTML)}</td>
                    <td>${addressHTML}</td>
                    <td>
                        <div class="saw-color-preview" style="background-color: ${escapeHtml(colorHTML)}">
                            <span>${escapeHtml(colorHTML)}</span>
                        </div>
                    </td>
                    <td class="saw-text-center">
                        <div class="saw-actions">
                            <a href="/admin/settings/customers/edit/${customer.id}/" 
                               class="saw-btn saw-btn-sm saw-btn-secondary" 
                               title="Upravit">
                                <span class="dashicons dashicons-edit"></span>
                            </a>
                            <button type="button" 
                                    class="saw-btn saw-btn-sm saw-btn-danger saw-delete-customer" 
                                    data-customer-id="${customer.id}" 
                                    data-customer-name="${escapeHtml(customer.name)}" 
                                    title="Smazat">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
        
        // Update tbody
        if ($tbody.length) {
            $tbody.html(rowsHTML);
        } else {
            // Rebuild entire table if it doesn't exist
            const tableHTML = `
                <div class="saw-table-responsive">
                    <table class="saw-table saw-table-sortable">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Logo</th>
                                <th class="saw-sortable" data-column="name">
                                    <a href="#">Název ${getSortIcon('name')}</a>
                                </th>
                                <th class="saw-sortable" data-column="ico" style="width: 120px;">
                                    <a href="#">IČO ${getSortIcon('ico')}</a>
                                </th>
                                <th>Adresa</th>
                                <th style="width: 100px;">Barva</th>
                                <th style="width: 140px;" class="saw-text-center">Akce</th>
                            </tr>
                        </thead>
                        <tbody id="saw-customers-tbody">
                            ${rowsHTML}
                        </tbody>
                    </table>
                </div>
            `;
            
            $container.html(tableHTML);
        }
        
        // Update sort icons
        updateSortIcons();
    }
    
    /**
     * Update pagination
     */
    function updatePagination(data) {
        if (data.total_pages <= 1) {
            $('#saw-customers-pagination').remove();
            return;
        }
        
        let paginationHTML = '<div class="saw-pagination" id="saw-customers-pagination">';
        
        // Previous button
        if (data.current_page > 1) {
            paginationHTML += `<a href="#" class="saw-pagination-link saw-pagination-prev" data-page="${data.current_page - 1}">&laquo; Předchozí</a>`;
        }
        
        // Page numbers
        for (let i = 1; i <= data.total_pages; i++) {
            if (i === data.current_page) {
                paginationHTML += `<span class="saw-pagination-link saw-pagination-active" data-page="${i}">${i}</span>`;
            } else {
                paginationHTML += `<a href="#" class="saw-pagination-link" data-page="${i}">${i}</a>`;
            }
        }
        
        // Next button
        if (data.current_page < data.total_pages) {
            paginationHTML += `<a href="#" class="saw-pagination-link saw-pagination-next" data-page="${data.current_page + 1}">Další &raquo;</a>`;
        }
        
        paginationHTML += '</div>';
        
        // Update or append pagination
        const $existingPagination = $('#saw-customers-pagination');
        if ($existingPagination.length) {
            $existingPagination.replaceWith(paginationHTML);
        } else {
            $('.saw-table-responsive').after(paginationHTML);
        }
    }
    
    /**
     * Update customers count
     */
    function updateCustomersCount(count) {
        $('#saw-customers-count').text(count);
    }
    
    /**
     * Update sort icons
     */
    function updateSortIcons() {
        $('.saw-sortable a').each(function() {
            const $link = $(this);
            const $th = $link.closest('th');
            const column = $th.data('column');
            
            $link.html($link.text().split(' ')[0] + ' ' + getSortIcon(column));
        });
    }
    
    /**
     * Get sort icon HTML
     */
    function getSortIcon(column) {
        if (currentOrderBy !== column) {
            return '<span class="saw-sort-icon">⇅</span>';
        }
        
        return currentOrder === 'ASC' 
            ? '<span class="saw-sort-icon saw-sort-asc">▲</span>' 
            : '<span class="saw-sort-icon saw-sort-desc">▼</span>';
    }
    
    /**
     * Update URL without page reload
     */
    function updateURL() {
        const params = new URLSearchParams(window.location.search);
        
        if (currentSearch) {
            params.set('s', currentSearch);
        } else {
            params.delete('s');
        }
        
        if (currentPage > 1) {
            params.set('paged', currentPage);
        } else {
            params.delete('paged');
        }
        
        params.set('orderby', currentOrderBy);
        params.set('order', currentOrder);
        
        const newURL = window.location.pathname + '?' + params.toString();
        window.history.replaceState({}, '', newURL);
    }
    
    /**
     * Show error message
     */
    function showError(message) {
        const alertHTML = `
            <div class="saw-alert saw-alert-error">
                ${escapeHtml(message)}
                <button type="button" class="saw-alert-close">&times;</button>
            </div>
        `;
        
        $('.saw-page-header').after(alertHTML);
        
        setTimeout(function() {
            $('.saw-alert').fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    /**
     * Helper: Escape HTML
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    /**
     * Helper: nl2br
     */
    function nl2br(str) {
        return String(str).replace(/\n/g, '<br>');
    }
    
    /**
     * Helper: WordPress wp_trim_words equivalent
     */
    function wpTrimWords(text, numWords) {
        const words = String(text).split(/\s+/);
        if (words.length <= numWords) {
            return text;
        }
        return words.slice(0, numWords).join(' ') + '...';
    }
    
})(jQuery);