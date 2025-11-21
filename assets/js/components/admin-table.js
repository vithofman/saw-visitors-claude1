/**
 * Admin Table Component JavaScript
 *
 * Handles clickable table rows with full page reload navigation.
 * Uses View Transition API for smooth page transitions.
 * Preserves scroll position and active row state.
 *
 * @package    SAW_Visitors
 * @subpackage Components
 * @version    6.0.0 - REFACTORED: Full page reload with View Transition
 * @since      1.0.0
 */

(function ($) {
    'use strict';

    /**
     * Update active row in table
     *
     * @param {number} id Item ID
     * @param {boolean} scrollToRow Whether to scroll active row into view
     * @return {void}
     */
    function updateActiveRow(id, scrollToRow) {
        if (typeof scrollToRow === 'undefined') {
            scrollToRow = false;
        }

        $('.saw-admin-table tbody tr').removeClass('saw-row-active');
        const $activeRow = $('.saw-admin-table tbody tr[data-id="' + id + '"]');

        if (!$activeRow.length) {
            console.warn('⚠️ Active row not found for ID:', id);
            return;
        }

        $activeRow.addClass('saw-row-active');
        console.log('✨ Active row updated:', id);

        // Scroll active row into view
        if (scrollToRow) {
            setTimeout(function () {
                const $scrollContainer = $('.saw-table-panel');

                if (!$scrollContainer.length) {
                    console.warn('⚠️ Scroll container (.saw-table-panel) not found');
                    return;
                }

                const containerTop = $scrollContainer.offset().top;
                const containerScrollTop = $scrollContainer.scrollTop();
                const rowTop = $activeRow.offset().top;
                const rowHeight = $activeRow.outerHeight();
                const containerHeight = $scrollContainer.outerHeight();

                // Calculate if row is visible
                const rowRelativeTop = rowTop - containerTop;
                const isRowVisible = (rowRelativeTop >= 0) && (rowRelativeTop + rowHeight <= containerHeight);

                if (!isRowVisible) {
                    // Scroll so row is centered in viewport
                    const targetScrollTop = containerScrollTop + rowRelativeTop - (containerHeight / 2) + (rowHeight / 2);
                    $scrollContainer.animate({ scrollTop: targetScrollTop }, 300);
                    console.log('📜 Scrolled active row into view');
                } else {
                    console.log('✅ Active row already visible');
                }
            }, 100);
        }
    }

    // Export updateActiveRow as global function
    window.updateActiveRow = updateActiveRow;

    /**
     * Parse URL to extract entity, id and mode
     *
     * @param {string} url URL to parse
     * @return {object} Parsed data
     */
    function parseUrl(url) {
        const path = url.split('/').filter(function (p) { return p; });

        let entity = null;
        let id = 0;
        let mode = 'detail';

        // Find entity - can be /admin/entity/ OR /admin/settings/entity/
        for (let i = 0; i < path.length; i++) {
            // Check for /admin/settings/customers/ pattern
            if (path[i] === 'settings' && path[i + 1]) {
                entity = path[i + 1];
                break;
            }
            // Check for /admin/branches/ pattern (without settings)
            if (path[i] === 'admin' && path[i + 1] && path[i + 1] !== 'settings') {
                entity = path[i + 1];
                break;
            }
        }

        // Find ID (first numeric value after entity)
        for (let i = 0; i < path.length; i++) {
            if (!isNaN(path[i]) && parseInt(path[i]) > 0) {
                id = parseInt(path[i]);

                // Check if next segment is 'edit'
                if (path[i + 1] === 'edit') {
                    mode = 'edit';
                }
                break;
            }
        }

        return { entity: entity, id: id, mode: mode };
    }

    /**
     * Save table state before navigation
     * 
     * @param {string} entity - Entity name
     * @param {number} rowId - Active row ID
     * @return {void}
     */
    function saveTableState(entity, rowId) {
        if (!window.stateManager || !entity) {
            return;
        }

        const $tablePanel = $('.saw-table-panel');
        const scrollTop = $tablePanel.length ? $tablePanel.scrollTop() : 0;

        window.stateManager.saveTableState(entity, {
            scrollTop: scrollTop,
            activeRowId: rowId
        });

        console.log('💾 Table state saved:', { entity, scrollTop, activeRowId: rowId });
    }

    /**
     * Restore table state after page load
     * 
     * @param {string} entity - Entity name
     * @return {void}
     */
    function restoreTableState(entity) {
        if (!window.stateManager || !entity) {
            return;
        }

        const state = window.stateManager.restoreTableState(entity);
        
        if (state) {
            // Restore scroll position
            const $tablePanel = $('.saw-table-panel');
            if ($tablePanel.length && state.scrollTop) {
                setTimeout(function() {
                    $tablePanel.scrollTop(state.scrollTop);
                    console.log('📜 Table scroll position restored:', state.scrollTop);
                }, 100);
            }

            // Restore active row
            if (state.activeRowId) {
                setTimeout(function() {
                    updateActiveRow(state.activeRowId, false);
                    console.log('✨ Active row restored:', state.activeRowId);
                }, 200);
            }
        }
    }

    /**
     * Set active row on page load from URL
     * 
     * This ensures that when user opens a detail URL directly 
     * (e.g., /admin/companies/24/), the corresponding 
     * table row is highlighted.
     *
     * @return {void}
     */
    function setActiveRowFromUrl() {
        const currentUrl = window.location.pathname;
        const parsed = parseUrl(currentUrl);

        if (parsed.id && parsed.entity) {
            console.log('🎯 Setting active row from URL:', parsed);

            // Wait for table to be fully rendered
            setTimeout(function () {
                updateActiveRow(parsed.id, true);
            }, 200);
        }
    }

    /**
     * Initialize admin table component
     *
     * @return {void}
     */
    function initAdminTable() {
        // Get entity from table data attribute
        const $table = $('.saw-admin-table');
        const entity = $table.data('entity');

        // Restore table state if we're on a list page
        if (entity && window.location.pathname.indexOf('/' + entity + '/') === -1 || 
            window.location.pathname.split('/').filter(p => p && !isNaN(p)).length === 0) {
            // We're on a list page (not detail/edit)
            restoreTableState(entity);
        }

        // Set active row from URL (if we're on a detail page)
        // But prioritize sidebar data-current-id if available
        const $sidebar = $('.saw-sidebar[data-current-id]');
        if ($sidebar.length && $sidebar.data('current-id')) {
            // Sidebar has current ID, will be handled by setActiveRowFromSidebar
            console.log('ℹ️ Sidebar has current-id, will set active row from sidebar');
        } else {
            // No sidebar, try URL
            setActiveRowFromUrl();
        }
        
        // Also set active row from sidebar if it exists (runs after initAdminTable)
        setActiveRowFromSidebar();

        // Clickable table rows - intercept clicks
        document.addEventListener('click', function (e) {
            // Find if we clicked inside a table row
            const $row = $(e.target).closest('.saw-admin-table tbody tr');

            if (!$row.length) {
                return; // Not in a table row
            }

            // Ignore clicks on action buttons and form inputs
            if ($(e.target).closest('.saw-action-buttons, button, input, select, a[href]').length) {
                return;
            }

            const itemId = $row.data('id');
            if (!itemId) {
                return;
            }

            // Stop event propagation to prevent link interceptor from handling it
            e.stopPropagation();
            e.stopImmediatePropagation();
            e.preventDefault();

            console.log('🔴 ADMIN TABLE: ROW CLICKED!', { id: itemId });

            // Modal mode (backward compatible)
            if ($row.attr('data-clickable-row')) {
                const modalId = $row.data('modal');

                if (modalId && typeof SAWModal !== 'undefined') {
                    console.log('📲 Opening modal:', modalId);
                    SAWModal.open(modalId, {
                        id: itemId,
                        nonce: (window.sawGlobal && window.sawGlobal.nonce) || window.sawAjaxNonce
                    });
                }
                return;
            }

            // Full page reload mode - get detail URL
            const detailUrl = $row.data('detail-url');

            if (detailUrl) {
                console.log('📊 Detail URL found:', detailUrl);

                const parsed = parseUrl(detailUrl);
                console.log('📊 Parsed URL:', parsed);

                if (parsed.entity && parsed.id) {
                    // Save table state before navigation
                    saveTableState(parsed.entity, itemId);

                    // Navigate with view transition
                    if (window.viewTransition) {
                        window.viewTransition.navigateTo(detailUrl);
                    } else {
                        // Fallback if view transition not available
                        window.location.href = detailUrl;
                    }
                } else {
                    console.warn('⚠️ Could not parse URL, falling back to full reload');
                    window.location.href = detailUrl;
                }
            }
        }, true); // Use capture phase

        // Floating button (Add New) - navigate to create form
        $(document).on('click', '.saw-floating-button', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const $btn = $(this);
            const createUrl = $btn.attr('href');

            if (!createUrl || createUrl === '#') {
                console.warn('⚠️ No create URL found');
                return;
            }

            // Extract entity from URL
            const parsed = parseUrl(createUrl);
            if (parsed.entity) {
                // Save table state before navigation
                saveTableState(parsed.entity, null);

                // Navigate with view transition
                if (window.viewTransition) {
                    window.viewTransition.navigateTo(createUrl);
                } else {
                    window.location.href = createUrl;
                }
            } else {
                console.warn('⚠️ Could not parse create URL, falling back to full reload');
                window.location.href = createUrl;
            }
        });

        console.log('✅ Admin Table initialized v6.0.0 - Full page reload with View Transition');
    }

    /**
     * Toggle action dropdown menu
     * 
     * @param {HTMLElement} button - The trigger button
     * @param {Event} event - Click event
     * @return {void}
     */
    window.toggleActionMenu = function(button, event) {
        if (event) {
            event.stopPropagation();
        }
        
        const dropdown = button.closest('.saw-action-dropdown');
        if (!dropdown) {
            return;
        }
        
        const row = dropdown.closest('tr');
        const isActive = dropdown.classList.contains('active');
        
        // Close all other dropdowns and remove row classes
        document.querySelectorAll('.saw-action-dropdown.active').forEach(function(el) {
            if (el !== dropdown) {
                el.classList.remove('active');
                const otherRow = el.closest('tr');
                if (otherRow) {
                    otherRow.classList.remove('saw-row-with-active-dropdown');
                }
            }
        });
        
        // Toggle current dropdown
        if (!isActive) {
            dropdown.classList.add('active');
            if (row) {
                row.classList.add('saw-row-with-active-dropdown');
            }
        } else {
            dropdown.classList.remove('active');
            if (row) {
                row.classList.remove('saw-row-with-active-dropdown');
            }
        }
    };
    
    /**
     * Toggle filters dropdown menu
     * 
     * @param {HTMLElement} button - The trigger button
     * @param {Event} event - Click event
     * @return {void}
     */
    window.toggleFiltersMenu = function(button, event) {
        if (event) {
            event.stopPropagation();
        }
        
        const wrapper = button.closest('.saw-filters-dropdown-wrapper');
        if (!wrapper) {
            return;
        }
        
        const isActive = wrapper.classList.contains('active');
        
        // Close all other filter dropdowns
        document.querySelectorAll('.saw-filters-dropdown-wrapper.active').forEach(function(el) {
            if (el !== wrapper) {
                el.classList.remove('active');
            }
        });
        
        // Close action dropdowns when opening filters
        document.querySelectorAll('.saw-action-dropdown.active').forEach(function(el) {
            el.classList.remove('active');
            const row = el.closest('tr');
            if (row) {
                row.classList.remove('saw-row-with-active-dropdown');
            }
        });
        
        // Toggle current dropdown
        if (!isActive) {
            wrapper.classList.add('active');
        } else {
            wrapper.classList.remove('active');
        }
    };
    
    /**
     * Close dropdowns when clicking outside
     */
    document.addEventListener('click', function(e) {
        // Close action dropdowns
        if (!e.target.closest('.saw-action-dropdown')) {
            document.querySelectorAll('.saw-action-dropdown.active').forEach(function(el) {
                el.classList.remove('active');
                const row = el.closest('tr');
                if (row) {
                    row.classList.remove('saw-row-with-active-dropdown');
                }
            });
        }
        
        // Close filter dropdowns
        if (!e.target.closest('.saw-filters-dropdown-wrapper')) {
            document.querySelectorAll('.saw-filters-dropdown-wrapper.active').forEach(function(el) {
                el.classList.remove('active');
            });
        }
    });

    // REMOVED: initGrouping(), saveGroupState(), restoreGroupStates()
    // Grouping functionality replaced by tabs navigation in v7.1.0
    
    /**
     * Get current entity from URL or data attribute
     * 
     * @since 7.0.0
     * @return {string} Entity name
     */
    function getCurrentEntity() {
        const $table = $('.saw-admin-table');
        if ($table.length && $table.data('entity')) {
            return $table.data('entity');
        }
        
        const path = window.location.pathname.split('/').filter(function(p) { return p; });
        for (let i = 0; i < path.length; i++) {
            if (path[i] === 'admin' && path[i + 1]) {
                return path[i + 1];
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Initialize sidebar navigation (prev/next buttons)
     * 
     * @since 7.0.0
     */
    function initSidebarNavigation() {
        $(document).on('click', '.saw-sidebar-prev, .saw-sidebar-next', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $button = $(this);
            const $sidebar = $button.closest('.saw-sidebar');
            const entity = $sidebar.data('entity');
            const currentId = parseInt($sidebar.data('current-id') || 0);
            const direction = $button.hasClass('saw-sidebar-prev') ? 'prev' : 'next';
            
            console.log('🔄 Sidebar navigation clicked:', { 
                entity, 
                currentId, 
                direction, 
                sidebarExists: $sidebar.length > 0,
                hasEntity: !!entity,
                hasCurrentId: !!currentId
            });
            
            if (!entity || !currentId) {
                console.warn('⚠️ Missing entity or current ID for sidebar navigation', { entity, currentId });
                alert('Chybí informace pro navigaci. Entity: ' + (entity || 'není') + ', ID: ' + (currentId || 'není'));
                return;
            }
            
            // Disable button during loading
            $button.prop('disabled', true);
            
            // Get AJAX action name - try both slug and entity name
            // Convert entity to slug format (underscores to hyphens)
            const moduleSlug = entity.replace(/_/g, '-');
            const entityName = entity.replace(/-/g, '_');
            
            // Try slug first (e.g., saw_get_adjacent_visitors)
            let ajaxAction = 'saw_get_adjacent_' + moduleSlug;
            
            console.log('🔄 Loading adjacent record:', { 
                entity, 
                moduleSlug, 
                entityName, 
                ajaxAction, 
                currentId, 
                direction 
            });
            
            // Get AJAX URL and nonce
            const ajaxUrl = (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
            const nonce = (window.sawGlobal && window.sawGlobal.nonce) 
                ? window.sawGlobal.nonce 
                : (typeof sawAjaxNonce !== 'undefined' ? sawAjaxNonce : '');
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: ajaxAction,
                    id: currentId,
                    direction: direction,
                    nonce: nonce
                },
                success: function(response) {
                    console.log('📥 AJAX response received:', response);
                    
                    if (response.success && response.data && response.data.url) {
                        // Navigate to adjacent record
                        console.log('✅ Navigating to:', response.data.url);
                        
                        // Use view transition if available
                        if (window.viewTransition && window.viewTransition.navigateTo) {
                            window.viewTransition.navigateTo(response.data.url);
                        } else {
                            window.location.href = response.data.url;
                        }
                    } else {
                        const errorMsg = response.data?.message || 'Unknown error';
                        console.error('❌ Failed to get adjacent record:', errorMsg, response);
                        alert('Nepodařilo se načíst sousední záznam: ' + errorMsg);
                        $button.prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ AJAX error:', { 
                        status: xhr.status, 
                        statusText: xhr.statusText, 
                        error, 
                        responseText: xhr.responseText,
                        ajaxAction 
                    });
                    
                    let errorMsg = 'Chyba při načítání sousedního záznamu';
                    if (xhr.status === 0) {
                        errorMsg = 'Nelze se připojit k serveru';
                    } else if (xhr.status === 404) {
                        errorMsg = 'AJAX akce nenalezena: ' + ajaxAction;
                    } else if (xhr.status === 403) {
                        errorMsg = 'Oprávnění zamítnuto';
                    }
                    
                    alert(errorMsg);
                    $button.prop('disabled', false);
                }
            });
        });
    }
    
    /**
     * Set active row from sidebar data-current-id
     * 
     * @since 7.0.0
     */
    function setActiveRowFromSidebar() {
        const $sidebar = $('.saw-sidebar[data-current-id]');
        if ($sidebar.length) {
            const currentId = $sidebar.data('current-id');
            if (currentId) {
                console.log('🎯 Setting active row from sidebar:', currentId);
                // Wait for table to be fully rendered
                setTimeout(function() {
                    updateActiveRow(currentId, true);
                    console.log('✨ Active row set from sidebar:', currentId);
                }, 500);
            }
        }
    }
    
    /**
     * Initialize infinite scroll
     * Vanilla JS implementation following best practices
     * 
     * @since 7.0.0
     */
    function initInfiniteScroll() {
        const config = window.sawInfiniteScrollConfig;
        
        if (!config || !config.infinite_scroll || !config.infinite_scroll.enabled) {
            return;
        }
        
        const scrollArea = document.querySelector('.saw-table-scroll-area');
        const tbody = document.querySelector('.saw-admin-table tbody');
        
        if (!scrollArea || !tbody) {
            return;
        }
        
        let isLoading = false;
        let currentPage = 1;
        let hasMore = true;
        
        // NOVÁ logika pro initial load
        const initialLoad = config.infinite_scroll.initial_load || 100;
        const perPage = config.infinite_scroll.per_page || 50;
        const threshold = config.infinite_scroll.threshold || 0.7; // 70% (changed from px to %)
        const entity = config.entity;
        
        // Cache loaded pages to prevent re-loading when scrolling back up
        const loadedPages = new Set();
        const loadedRowsCache = new Map(); // page -> array of row IDs
        
        // Get current filters and search from URL
        const urlParams = new URLSearchParams(window.location.search);
        const filters = {
            search: urlParams.get('s') || '',
            orderby: urlParams.get('orderby') || 'id',
            order: urlParams.get('order') || 'DESC',
        };
        
        // Add filter params
        urlParams.forEach((value, key) => {
            if (!['s', 'orderby', 'order', 'paged'].includes(key) && value) {
                filters[key] = value;
            }
        });
        
        // Get columns from config
        const columns = config.columns || {};
        const actions = config.actions || [];
        
        // Count existing rows to determine starting page
        const existingRows = tbody.querySelectorAll('tr.saw-table-row').length;
        
        if (existingRows >= initialLoad) {
            // Already have initial load, use per_page for next loads
            currentPage = Math.ceil(existingRows / perPage);
            // Mark pages as loaded
            for (let i = 1; i <= currentPage; i++) {
                loadedPages.add(i);
            }
        } else {
            // First load, use initial_load
            currentPage = 1;
            if (existingRows > 0) {
                loadedPages.add(1);
            }
        }
        
        // Track loaded row IDs to prevent duplicates
        const getRowIds = () => {
            const rows = tbody.querySelectorAll('tr.saw-table-row[data-id]');
            return Array.from(rows).map(row => row.getAttribute('data-id')).filter(Boolean);
        };
        
        const loadedRowIds = new Set(getRowIds());
        
        /**
         * Load next page of items
         */
        function loadNextPage() {
            if (isLoading || !hasMore) {
                return;
            }
            
            const nextPage = currentPage + 1;
            
            // CRITICAL: Check cache BEFORE incrementing currentPage
            // This prevents re-loading when scrolling back up
            if (loadedPages.has(nextPage)) {
                console.log('✅ Page', nextPage, 'already loaded, skipping');
                return;
            }
            
            console.log('📥 Loading page', nextPage);
            
            // NOW it's safe to increment
            isLoading = true;
            currentPage = nextPage;
            
            // Remove existing loading indicator
            const existingLoading = tbody.querySelector('.saw-infinite-scroll-loading');
            if (existingLoading) {
                existingLoading.remove();
            }
            
            const loadingRow = document.createElement('tr');
            loadingRow.className = 'saw-infinite-scroll-loading';
            const colCount = document.querySelector('.saw-admin-table thead tr')?.querySelectorAll('th').length || 10;
            loadingRow.innerHTML = `<td colspan="${colCount}" class="saw-infinite-scroll-loading-cell">
                <div class="saw-infinite-scroll-loader">
                    <span class="dashicons dashicons-update"></span>
                    <span class="saw-infinite-scroll-loader-text">Načítání...</span>
                </div>
            </td>`;
            tbody.appendChild(loadingRow);
            
            // Get AJAX URL and nonce
            const ajaxUrl = (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
            const nonce = (window.sawGlobal && window.sawGlobal.nonce) 
                ? window.sawGlobal.nonce 
                : (typeof sawAjaxNonce !== 'undefined' ? sawAjaxNonce : '');
            
            // Build request data
            const requestData = new URLSearchParams({
                action: 'saw_get_items_infinite_' + entity.replace(/_/g, '-'),
                nonce: nonce,
                page: currentPage,
                per_page: perPage,
                search: filters.search || '',
                orderby: filters.orderby || 'id',
                order: filters.order || 'DESC',
            });
            
            // Add columns as JSON
            if (columns && Object.keys(columns).length > 0) {
                requestData.append('columns', JSON.stringify(columns));
            }
            
            // Add filter params
            Object.keys(filters).forEach(key => {
                if (!['search', 'orderby', 'order'].includes(key) && filters[key]) {
                    requestData.append(key, filters[key]);
                }
            });
            
            // NOVÉ: Add current tab filter
            if (config.tabs && config.current_tab) {
                const tabParam = config.tabs.tab_param || 'tab';
                requestData.append(tabParam, config.current_tab);
            }
            
            // Make AJAX request
            fetch(ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: requestData
            })
            .then(response => response.json())
                .then(data => {
                // Remove loading indicator
                const loadingEl = tbody.querySelector('.saw-infinite-scroll-loading');
                if (loadingEl) {
                    loadingEl.remove();
                }
                
                if (data.success && data.data) {
                    const { html, has_more, loaded } = data.data;
                    
                    hasMore = has_more;
                    
                    if (html && loaded > 0) {
                        // ✅ Mark page as loaded ONLY after successful load
                        loadedPages.add(currentPage);
                        console.log('✅ Page', currentPage, 'loaded successfully,', loaded, 'rows');
                        
                        // Create temporary container to parse HTML
                        const temp = document.createElement('tbody');
                        temp.innerHTML = html;
                        
                        const rows = temp.querySelectorAll('tr');
                        
                        // Filter out rows that are already loaded (prevent duplicates)
                        const rowsToAdd = Array.from(rows).filter(row => {
                            const rowId = row.getAttribute('data-id');
                            if (!rowId) {
                                // Rows without ID - allow them
                                return true;
                            }
                            if (loadedRowIds.has(rowId)) {
                                console.log('⚠️ Duplicate row detected:', rowId);
                                return false;
                            }
                            loadedRowIds.add(rowId);
                            return true;
                        });
                        
                        if (rowsToAdd.length === 0) {
                            console.log('⚠️ No new rows to add (all duplicates)');
                            isLoading = false;
                            return;
                        }
                        
                        console.log('✅ Adding', rowsToAdd.length, 'new rows');
                        
                        // For tabs (non-grouped), just append rows
                        rowsToAdd.forEach(row => {
                            tbody.appendChild(row);
                        });
                        
                    }
                } else {
                    console.error('Failed to load items:', data.data?.message || 'Unknown error');
                    hasMore = false;
                }
                
                isLoading = false;
            })
            .catch(error => {
                console.error('AJAX error loading items:', error);
                const loadingEl = tbody.querySelector('.saw-infinite-scroll-loading');
                if (loadingEl) {
                    loadingEl.remove();
                }
                isLoading = false;
            });
        }
        
        /**
         * Handle scroll event
         * Only load when scrolling down, not when scrolling up
         * Cache prevents re-loading when scrolling back up
         */
        let lastScrollTop = scrollArea.scrollTop;
        let scrollTimeout;
        
        function handleScroll() {
            const scrollTop = scrollArea.scrollTop;
            const scrollHeight = scrollArea.scrollHeight;
            const clientHeight = scrollArea.clientHeight;
            
            // Only load when scrolling down (with small threshold to avoid false positives)
            const isScrollingDown = scrollTop > lastScrollTop + 5;
            lastScrollTop = scrollTop;
            
            // Don't load when scrolling up - cached rows should already be in DOM
            if (!isScrollingDown) {
                return;
            }
            
            if (isLoading || !hasMore) {
                return;
            }
            
            // Check if user has scrolled to threshold percentage
            // threshold is now a percentage (0.7 = 70%) instead of pixels
            const scrollPercent = (scrollTop + clientHeight) / scrollHeight;
            
            if (scrollPercent >= threshold) {
                loadNextPage();
            }
        }
        
        // Attach scroll listener with throttling
        scrollArea.addEventListener('scroll', function() {
            if (scrollTimeout) {
                clearTimeout(scrollTimeout);
            }
            scrollTimeout = setTimeout(handleScroll, 100);
        }, { passive: true });
        
        console.log('✅ Infinite scroll initialized for', entity);
    }
    
    /**
     * Initialize on DOM ready
     */
    /**
     * Handle global search across all tabs
     * 
     * When searching, automatically switch to "all" tab if current tab has no results
     */
    function handleGlobalSearch() {
        const $searchForm = $('.saw-search-form');
        const $tabs = $('.saw-table-tabs');
        
        if (!$searchForm.length || !$tabs.length) {
            return;
        }
        
        $searchForm.on('submit', function() {
            const searchValue = $('.saw-search-input').val();
            
            if (searchValue && searchValue.trim() !== '') {
                // If searching, switch to "all" tab
                const $allTab = $('.saw-table-tab[data-tab="all"]');
                
                if ($allTab.length && !$allTab.hasClass('active')) {
                    // Modify form action to include "all" tab
                    const currentAction = $searchForm.attr('action');
                    const separator = currentAction.indexOf('?') !== -1 ? '&' : '?';
                    // Get tab param from first tab's data attribute or URL
                    const tabParam = $tabs.find('.saw-table-tab').first().attr('href').match(/\?([^=]+)=/)?.[1] || 'status';
                    
                    $searchForm.attr('action', currentAction + separator + tabParam + '=all');
                }
            }
            
            return true; // Allow form submission
        });
    }

    $(document).ready(function () {
        initAdminTable();
        
        // REMOVED: Grouping initialization (replaced by tabs)
        // Check if we have a grouped table
        // if ($('.saw-table-grouped').length) {
        //     initGrouping();
        //     restoreGroupStates();
        //     console.log('✅ Grouping initialized');
        // }
        
        // Initialize sidebar navigation
        // Use event delegation so it works for dynamically loaded sidebars
        // Only initialize once - event delegation handles dynamically loaded content
        if (!window.sawSidebarNavigationInitialized) {
            initSidebarNavigation();
            window.sawSidebarNavigationInitialized = true;
            console.log('✅ Sidebar navigation initialized (event delegation)');
        }
        
        if ($('.saw-sidebar').length) {
            console.log('📋 Found', $('.saw-sidebar').length, 'sidebar(s) on page');
            $('.saw-sidebar').each(function() {
                const entity = $(this).data('entity');
                const currentId = $(this).data('current-id');
                console.log('  - Sidebar:', { entity, currentId, mode: $(this).data('mode') });
            });
        }
        
        // Initialize infinite scroll
        initInfiniteScroll();
        
        // Initialize global search handling
        handleGlobalSearch();
    });

})(jQuery);
