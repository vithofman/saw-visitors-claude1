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

        // Support both old and new CSS classes
        $('.sa-table-element tbody tr').removeClass('sa-table-row--active');
        const $activeRow = $('.sa-table-element tbody tr[data-id="' + id + '"]');

        if (!$activeRow.length) {
            console.warn('⚠️ Active row not found for ID:', id);
            return;
        }

        $activeRow.addClass('sa-table-row--active');
        console.log('✨ Active row updated:', id);

        // Scroll active row into view - ALWAYS when row is set
        setTimeout(function () {
            const $scrollContainer = $('.sa-table-scroll');

            if (!$scrollContainer.length) {
                console.warn('⚠️ Scroll container not found');
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

            if (!isRowVisible || scrollToRow) {
                // Scroll so row is centered in viewport
                const targetScrollTop = containerScrollTop + rowRelativeTop - (containerHeight / 2) + (rowHeight / 2);
                $scrollContainer.animate({
                    scrollTop: targetScrollTop
                }, 300);
                console.log('📜 Scrolled active row into view');
            } else {
                console.log('✅ Active row already visible');
            }
        }, 200);
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

        const $scrollArea = $('.sa-table-scroll');
        const scrollTop = $scrollArea.length ? $scrollArea.scrollTop() : 0;

        window.stateManager.saveTableState(entity, {
            scrollTop: scrollTop,
            activeRowId: rowId
        });

        console.log('💾 Table state saved:', { entity, scrollTop, activeRowId: rowId });
    }

    /**
     * Restore table state after page load
     * 
     * CRITICAL: Respects sidebar priority - skips if sidebar has active row
     * 
     * @param {string} entity - Entity name
     * @return {void}
     */
    function restoreTableState(entity) {
        if (!window.stateManager || !entity) {
            return;
        }

        // ===== CRITICAL: SKIP IF SIDEBAR HAS PRIORITY =====
        if (window._hasSidebarActiveRow) {
            console.log('⏭️ Restore skipped - sidebar handling active row');
            return;
        }

        const state = window.stateManager.restoreTableState(entity);

        if (!state) {
            return;
        }

        const $scrollArea = $('.sa-table-scroll');

        // Restore scroll position
        if ($scrollArea.length && state.scrollTop) {
            setTimeout(function () {
                // Check again for lock (safety check)
                const scrollArea = $scrollArea[0];
                if (scrollArea && scrollArea._isLockedToActiveRow) {
                    console.log('🔒 Scroll restore blocked - locked to active row');
                    return;
                }

                $scrollArea.scrollTop(state.scrollTop);
                console.log('📜 Scroll position restored:', state.scrollTop);
            }, 100);
        }

        // Restore active row
        if (state.activeRowId) {
            setTimeout(function () {
                updateActiveRow(state.activeRowId, true);
                console.log('✨ Active row restored:', state.activeRowId);
            }, 800);
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
     * PRIORITY LOGIC:
     * 1. If sidebar has current-id → skip restore, let sidebar handle it
     * 2. If no sidebar → try restore
     *
     * @return {void}
     */
    function initAdminTable() {
        // Get entity from table data attribute
        // Support both old and new CSS classes
        const $table = $('.sa-table-element');
        const entity = $table.data('entity');

        // ===== PRIORITY CHECK: SIDEBAR HAS HIGHEST PRIORITY =====
        // Support both old and new CSS classes
        const $sidebar = $('.sa-sidebar[data-current-id], .saw-sidebar[data-current-id]');
        const hasSidebarActiveRow = $sidebar.length && $sidebar.data('current-id');

        // Store flag globally for restore logic
        if (hasSidebarActiveRow) {
            window._hasSidebarActiveRow = true;
            window._sidebarActiveRowId = $sidebar.data('current-id');
            console.log('🎯 Sidebar detected with active row:', window._sidebarActiveRowId);
        }

        // ===== CONDITIONAL LOGIC: RESTORE OR SIDEBAR =====

        // Check if we're on detail page
        const isDetailPage = window.location.pathname.split('/').filter(p => p && !isNaN(p)).length > 0;

        if (entity && !isDetailPage) {
            // We're on LIST page
            if (!hasSidebarActiveRow) {
                // No sidebar → safe to restore
                console.log('📜 No sidebar, attempting restore');
                restoreTableState(entity);
            } else {
                console.log('⏭️ Sidebar active row detected, skipping restore');
            }
        }

        // Set active row from URL (only if no sidebar)
        if (!hasSidebarActiveRow) {
            setActiveRowFromUrl();
        }

        // Set active row from sidebar (PRIORITY over everything)
        if (hasSidebarActiveRow) {
            setActiveRowFromSidebar();
        }

        // Clickable table rows - intercept clicks
        document.addEventListener('click', function (e) {
            // Find if we clicked inside a table row
            // Support both old and new CSS classes
            const $row = $(e.target).closest('.sa-table-element tbody tr');

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
        // Support both old and new CSS classes
        $(document).on('click', '.sa-fab, .saw-floating-button', function (e) {
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

        // Initialize scroll to top button
        initScrollToTop();

        console.log('✅ Admin Table initialized v6.0.0 - Full page reload with View Transition');
    }

    /**
     * Initialize scroll to top button
     * 
     * Shows/hides button based on scroll position and handles click
     * 
     * @return {void}
     */
    function initScrollToTop() {
        const $scrollArea = $('.sa-table-scroll');
        const $scrollToTopBtn = $('.sa-scroll-to-top');

        if (!$scrollArea.length) {
            console.warn('[Scroll to Top] Scroll area not found');
            return;
        }

        if (!$scrollToTopBtn.length) {
            console.warn('[Scroll to Top] Button not found');
            return;
        }

        console.log('[Scroll to Top] Initializing', { scrollArea: $scrollArea.length, button: $scrollToTopBtn.length });

        // Show/hide button based on scroll position
        function toggleScrollToTop() {
            const scrollTop = $scrollArea.scrollTop();
            if (scrollTop > 300) {
                $scrollToTopBtn.addClass('sa-scroll-to-top--visible');
            } else {
                $scrollToTopBtn.removeClass('sa-scroll-to-top--visible');
            }
        }

        // Check on scroll
        $scrollArea.on('scroll', toggleScrollToTop);

        // Check on load
        setTimeout(toggleScrollToTop, 100);

        // Handle click
        $scrollToTopBtn.on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $scrollArea.animate({
                scrollTop: 0
            }, 300);
        });
    }

    /**
     * Toggle filters dropdown menu
     * 
     * @param {HTMLElement} button - The trigger button
     * @param {Event} event - Click event
     * @return {void}
     */
    window.toggleFiltersMenu = function (button, event) {
        if (event) {
            event.stopPropagation();
        }

        // Support both old and new CSS classes
        const wrapper = button.closest('.sa-table-filters-wrapper, .saw-filters-dropdown-wrapper');
        if (!wrapper) {
            return;
        }

        const dropdown = wrapper.querySelector('.sa-dropdown--filters, .saw-filters-dropdown-menu');
        if (!dropdown) {
            return;
        }

        const isActive = dropdown.classList.contains('sa-dropdown--open') || wrapper.classList.contains('active');

        // Close all other filter dropdowns
        document.querySelectorAll('.sa-dropdown--filters.sa-dropdown--open, .saw-filters-dropdown-wrapper.active').forEach(function (el) {
            if (el !== dropdown && el !== wrapper) {
                el.classList.remove('sa-dropdown--open', 'active');
            }
        });

        // Close action dropdowns when opening filters
        document.querySelectorAll('.saw-action-dropdown.active').forEach(function (el) {
            el.classList.remove('active');
            const row = el.closest('tr');
            if (row) {
                row.classList.remove('saw-row-with-active-dropdown');
            }
        });

        // Toggle current dropdown
        if (!isActive) {
            dropdown.classList.add('sa-dropdown--open');
            wrapper.classList.add('active');
        } else {
            dropdown.classList.remove('sa-dropdown--open');
            wrapper.classList.remove('active');
        }
    };

    /**
     * Close dropdowns when clicking outside
     */
    document.addEventListener('click', function (e) {
        // Close action dropdowns
        if (!e.target.closest('.saw-action-dropdown')) {
            document.querySelectorAll('.saw-action-dropdown.active').forEach(function (el) {
                el.classList.remove('active');
                const row = el.closest('tr');
                if (row) {
                    row.classList.remove('saw-row-with-active-dropdown');
                }
            });
        }

        // Close filter dropdowns
        if (!e.target.closest('.saw-filters-dropdown-wrapper')) {
            document.querySelectorAll('.saw-filters-dropdown-wrapper.active').forEach(function (el) {
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
        const $table = $('.sa-table-element');
        if ($table.length && $table.data('entity')) {
            return $table.data('entity');
        }

        const path = window.location.pathname.split('/').filter(function (p) { return p; });
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
        $(document).on('click', '.saw-sidebar-prev, .saw-sidebar-next', function (e) {
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
                success: function (response) {
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
                error: function (xhr, status, error) {
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
 * This has HIGHEST priority - overrides restore and URL
 * 
 * @since 7.0.0
 */
    function setActiveRowFromSidebar() {
        const $sidebar = $('.saw-sidebar[data-current-id]');
        if (!$sidebar.length) {
            return;
        }

        const currentId = $sidebar.data('current-id');
        if (!currentId) {
            return;
        }

        console.log('🎯 Setting active row from sidebar:', currentId);

        // Check if row exists in DOM
        const checkAndSetActiveRow = () => {
            const $activeRow = $('.sa-table-element tbody tr[data-id="' + currentId + '"]');

            if ($activeRow.length) {
                // ===== ROW FOUND: SET ACTIVE + SCROLL =====
                console.log('✅ Row found in DOM, setting active');
                updateActiveRow(currentId, true); // true = scroll to row

                // Clear any pending flags
                const scrollArea = document.querySelector('.sa-table-scroll');
                if (scrollArea) {
                    scrollArea._pendingActiveRowId = undefined;
                    scrollArea._needsActiveRow = false;
                }
            } else {
                // ===== ROW NOT FOUND: SET FLAG FOR INFINITE SCROLL =====
                console.log('⏳ Row not in DOM, setting pending flag for infinite scroll');

                const scrollArea = document.querySelector('.sa-table-scroll');
                if (scrollArea) {
                    // Store pending active row ID
                    scrollArea._pendingActiveRowId = currentId;
                    scrollArea._needsActiveRow = true;

                    console.log('📌 Pending active row set:', currentId);
                    console.log('ℹ️ Infinite scroll will load pages until row is found');

                    // ===== KRITICKÉ: TRIGGER SCROLL DOWN TO START LOADING =====
                    // Simuluj scroll dolů aby se spustil infinite scroll
                    setTimeout(() => {
                        const currentScroll = scrollArea.scrollTop;
                        const scrollHeight = scrollArea.scrollHeight;
                        const clientHeight = scrollArea.clientHeight;

                        // Scroll na 70% aby se triggernul infinite scroll
                        const targetScroll = scrollHeight * 0.7;

                        console.log('🚀 Auto-scrolling to trigger infinite scroll loading');
                        scrollArea.scrollTop = targetScroll;
                    }, 100);
                }
            }
        };

        // Wait for table to be rendered
        setTimeout(checkAndSetActiveRow, 500);
    }

    /**
     * Initialize infinite scroll
     * Vanilla JS implementation following best practices
     * 
     * @since 7.0.0
     */
    function initInfiniteScroll() {
        const config = window.sawInfiniteScrollConfig;

        // DEBUG MODE
        const DEBUG = true;

        if (!config || !config.infinite_scroll || !config.infinite_scroll.enabled) {
            return;
        }

        if (DEBUG) {
            console.group('🐛 Infinite Scroll Initialization');
            console.log('Entity:', config.entity);
            console.log('Config:', config);
        }

        const scrollArea = document.querySelector('.sa-table-scroll');

        if (DEBUG) {
            console.log('🔍 Looking for scrollArea (.sa-table-scroll):', scrollArea ? '✅ Found' : '❌ Not found');
            if (scrollArea) {
                console.log('📏 ScrollArea dimensions:', {
                    scrollHeight: scrollArea.scrollHeight,
                    clientHeight: scrollArea.clientHeight,
                    scrollTop: scrollArea.scrollTop,
                    isScrollable: scrollArea.scrollHeight > scrollArea.clientHeight
                });
            }
        }

        // Cleanup previous initialization if exists
        if (scrollArea && scrollArea._sawInfiniteScrollHandler) {
            scrollArea.removeEventListener('scroll', scrollArea._sawInfiniteScrollHandler);
            scrollArea._sawInfiniteScrollHandler = null;
            if (DEBUG) {
                console.log('🧹 Cleaned up previous scroll listener');
            }
        }

        // Cleanup wheel handler if exists
        if (scrollArea && scrollArea._sawInfiniteScrollWheelHandler) {
            scrollArea.removeEventListener('wheel', scrollArea._sawInfiniteScrollWheelHandler);
            scrollArea._sawInfiniteScrollWheelHandler = null;
            if (DEBUG) {
                console.log('🧹 Cleaned up previous wheel listener');
            }
        }

        // Cleanup window wheel handler if exists
        if (scrollArea && scrollArea._sawInfiniteScrollWindowWheelHandler) {
            window.removeEventListener('wheel', scrollArea._sawInfiniteScrollWindowWheelHandler);
            scrollArea._sawInfiniteScrollWindowWheelHandler = null;
            if (DEBUG) {
                console.log('🧹 Cleaned up previous window wheel listener');
            }
        }

        // Cleanup IntersectionObserver if exists
        if (scrollArea && scrollArea._sawInfiniteScrollObserver) {
            scrollArea._sawInfiniteScrollObserver.disconnect();
            scrollArea._sawInfiniteScrollObserver = null;
            if (DEBUG) {
                console.log('🧹 Cleaned up previous IntersectionObserver');
            }
        }

        // Cleanup sentinel if exists
        if (scrollArea && scrollArea._sawInfiniteScrollSentinel) {
            scrollArea._sawInfiniteScrollSentinel.remove();
            scrollArea._sawInfiniteScrollSentinel = null;
            if (DEBUG) {
                console.log('🧹 Cleaned up previous sentinel element');
            }
        }

        const tbody = document.querySelector('.sa-table-element tbody');

        if (!scrollArea || !tbody) {
            if (DEBUG) {
                console.error('❌ Missing elements:', {
                    scrollArea: !scrollArea,
                    tbody: !tbody
                });
            }
            return;
        }

        let isLoading = false;
        let currentPage = 1;
        let hasMore = true;
        let isRestoring = false; // OPRAVENO 2025-01-22: Flag pro scroll restoration protection
        let isAppending = false; // PŘIDÁNO 2025-01-22: Flag pro append lock

        // NOVÁ logika pro initial load
        const initialLoad = config.infinite_scroll.initial_load || 100;
        const perPage = config.infinite_scroll.per_page || 50;
        const threshold = config.infinite_scroll.threshold || 0.6; // OPRAVENO 2025-01-22: Sníženo z 70% na 60% pro dřívější loading
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
        const existingRows = tbody.querySelectorAll('tr.sa-table-row').length;
        const totalItems = config.total_items || 0; // ⭐ NOVÉ: Získat total_items z configu

        // ⭐ KRITICKÁ OPRAVA: Inicializovat hasMore na základě total_items
        if (totalItems > 0) {
            hasMore = existingRows < totalItems;
            if (DEBUG) {
                console.log('📊 Initial hasMore:', hasMore, 'existingRows:', existingRows, 'totalItems:', totalItems);
            }
        } else {
            // Fallback: pokud není total_items, použít defaultní logiku
            hasMore = true;
            if (DEBUG) {
                console.log('📊 Initial hasMore: true (no totalItems)');
            }
        }

        if (DEBUG) {
            console.log('Initial rows:', existingRows);
            console.log('Total items:', totalItems);
            console.log('Has more:', hasMore);
            console.log('Initial load:', initialLoad);
            console.log('Per page:', perPage);
            console.log('Threshold:', threshold * 100 + '%');
        }

        // ⭐ FIX: Správně určit currentPage pro infinite scroll
        // Pro infinite scroll: page 1 = initialLoad, další stránky = perPage
        if (existingRows >= initialLoad) {
            // Máme initialLoad záznamů, takže jsme načtli stránku 1
            currentPage = 1;
            // Mark page 1 as loaded
            loadedPages.add(1);
            // Pokud máme více než initialLoad, znamená to, že už jsme načtli další stránky
            if (existingRows > initialLoad) {
                // Počítáme další stránky: (existingRows - initialLoad) / perPage
                const additionalPages = Math.ceil((existingRows - initialLoad) / perPage);
                currentPage = 1 + additionalPages;
                // Mark all pages as loaded
                for (let i = 1; i <= currentPage; i++) {
                    loadedPages.add(i);
                }
            }
        } else if (existingRows > 0) {
            // Máme méně než initialLoad, takže jsme na stránce 1, ale načteno méně záznamů
            currentPage = 1;
            loadedPages.add(1);
        } else {
            // No rows yet, start at page 1
            currentPage = 1;
        }

        if (DEBUG) {
            console.log('Loaded pages:', Array.from(loadedPages));
            console.groupEnd();
        }

        // ⭐ KRITICKÁ OPRAVA: Automaticky načíst další stránku, pokud není scrollovatelné
        // a existují další záznamy k načtení
        if (existingRows > 0 && existingRows < initialLoad && hasMore) {
            // Zkontroluj, zda je scrollArea scrollovatelné
            const isScrollable = scrollArea.scrollHeight > scrollArea.clientHeight;

            if (!isScrollable) {
                // ScrollArea není scrollovatelné, ale existují další záznamy
                // Automaticky načíst další stránku
                if (DEBUG) {
                    console.log('🔄 Auto-loading next page - scroll area not scrollable');
                    console.log('Scroll height:', scrollArea.scrollHeight, 'Client height:', scrollArea.clientHeight);
                }
                // Malé zpoždění, aby se DOM stihl vykreslit
                setTimeout(() => {
                    loadNextPage();
                }, 100);
            }
        }

        // Track loaded row IDs to prevent duplicates
        const getRowIds = () => {
            const rows = tbody.querySelectorAll('tr.sa-table-row[data-id]');
            return Array.from(rows).map(row => row.getAttribute('data-id')).filter(Boolean);
        };

        const loadedRowIds = new Set(getRowIds());

        /**
         * Load next page of items
         */
        function loadNextPage() {
            if (isLoading || !hasMore) {
                if (DEBUG && isLoading) {
                    console.log('⏸️ Load skipped - already loading');
                }
                if (DEBUG && !hasMore) {
                    console.log('⏸️ Load skipped - no more pages');
                }
                return;
            }

            const nextPage = currentPage + 1;

            // Store nextPage in a variable accessible in the callback
            const pageToLoad = nextPage;

            if (DEBUG) {
                console.log('🔍 Checking next page:', {
                    currentPage: currentPage,
                    nextPage: nextPage,
                    loadedPages: Array.from(loadedPages),
                    hasMore: hasMore
                });
            }

            // CRITICAL: Check cache BEFORE any state changes
            if (loadedPages.has(nextPage)) {
                if (DEBUG) {
                    console.log('✅ Page', nextPage, 'already cached, skipping');
                }
                return;
            }

            // Check if rows from this page are already in DOM
            const currentRowCount = tbody.querySelectorAll('tr.sa-table-row[data-id]').length;

            // ✅ OPRAVA: Správný výpočet pro infinite scroll
            // Page 1 = initialLoad (100), Page 2 = initialLoad + perPage (150), Page 3 = initialLoad + 2*perPage (200)
            let expectedRowsForNextPage;
            if (nextPage === 1) {
                expectedRowsForNextPage = initialLoad;
            } else {
                expectedRowsForNextPage = initialLoad + (nextPage - 1) * perPage;
            }

            if (currentRowCount >= expectedRowsForNextPage - 10) { // -10 for tolerance
                console.log('✅ Rows already in DOM, marking page as loaded');
                loadedPages.add(nextPage);
                currentPage = nextPage;
                return;
            }

            if (DEBUG) {
                console.group('📥 Loading Page ' + nextPage);
                console.log('Current page:', currentPage);
                console.log('Loaded pages:', Array.from(loadedPages));
                console.log('Current rows:', currentRowCount);
                console.log('Expected rows:', expectedRowsForNextPage);
                console.log('Has more:', hasMore);
                console.log('Is loading:', isLoading);
            }

            console.log('📥 Loading page', nextPage, '- Cache miss');

            // NOW increment and load
            isLoading = true;

            // Remove existing loading indicator
            const existingLoading = tbody.querySelector('.saw-infinite-scroll-loading');
            if (existingLoading) {
                existingLoading.remove();
            }

            const loadingRow = document.createElement('tr');
            loadingRow.className = 'saw-infinite-scroll-loading';
            const colCount = document.querySelector('.sa-table-element thead tr')?.querySelectorAll('th').length || 10;
            loadingRow.innerHTML = `<td colspan="${colCount}" class="sa-infinite-scroll-loading-cell">
                <div class="sa-infinite-scroll-loader">
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
            // ⭐ NOVÉ: Pošli skutečný počet již načtených záznamů pro offset-based pagination
            const loadedCount = tbody.querySelectorAll('tr.sa-table-row[data-id]').length;

            const requestData = new URLSearchParams({
                action: 'saw_get_items_infinite_' + entity.replace(/_/g, '-'),
                nonce: nonce,
                page: nextPage,
                per_page: nextPage === 1 ? initialLoad : perPage, // OPRAVENO 2025-01-22: Použij initial_load pro první stránku
                loaded_count: loadedCount, // NOVÉ: Počet skutečně načtených záznamů pro offset-based pagination
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

            // NOVÉ: Add current tab filter using filter_value from data attribute
            if (config.tabs && config.current_tab) {
                const tabParam = config.tabs.tab_param || 'tab';

                // Get filter_value from active tab
                const activeTab = document.querySelector('.sa-table-tab.sa-table-tab--active');
                if (activeTab) {
                    const filterValue = activeTab.getAttribute('data-filter-value');
                    if (filterValue && filterValue !== '' && filterValue !== 'null') {
                        requestData.append(tabParam, filterValue);
                        console.log('📌 Adding tab filter:', tabParam, '=', filterValue);
                    }
                }
            }


            // ===== DEBUG LOG - PŘED FETCH =====
            console.log('🚀 SENDING AJAX REQUEST:', {
                action: requestData.get('action'),
                page: requestData.get('page'),
                per_page: requestData.get('per_page'),
                nextPage: nextPage,
                currentPage: currentPage,
                loadedPages: Array.from(loadedPages)
            });
            // ===== END DEBUG =====

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

                    // ✅ NOVÉ: Přesuň sentinel na konec po přidání nových řádků
                    if (scrollArea._moveSentinelToEnd) {
                        scrollArea._moveSentinelToEnd();
                    }

                    if (data.success && data.data) {
                        const { html, has_more, loaded, page } = data.data;

                        hasMore = has_more;

                        if (html && loaded > 0) {
                            // ✅ OPRAVA: Reset isLoading dříve, aby se mohla načíst další stránka
                            isLoading = false;

                            // ✅ Mark page as loaded ONLY after successful load
                            // Use pageToLoad (which is nextPage) to ensure we mark the correct page
                            loadedPages.add(pageToLoad);
                            // Update currentPage to the page we just loaded
                            currentPage = pageToLoad;
                            if (DEBUG) {
                                console.log('✅ Page', pageToLoad, 'loaded successfully,', loaded, 'rows');
                                console.log('📊 State after load:', {
                                    currentPage: currentPage,
                                    loadedPages: Array.from(loadedPages),
                                    hasMore: hasMore
                                });
                            }

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
                                isAppending = false; // PŘIDÁNO 2025-01-22: Unlock i při prázdném výsledku
                                // ✅ CRITICAL: Still update currentPage even if no rows added
                                if (pageToLoad !== currentPage) {
                                    currentPage = pageToLoad;
                                    loadedPages.add(pageToLoad);
                                }
                                return;
                            }

                            // PŘIDÁNO 2025-01-22: Lock scroll during append
                            isAppending = true;

                            // PŘIDÁNO 2025-01-22: Preserve scroll position BEFORE append
                            const scrollPosBefore = scrollArea.scrollTop;
                            const scrollHeightBefore = scrollArea.scrollHeight;

                            // OPRAVENO 2025-01-22: Use DocumentFragment for batch append
                            const fragment = document.createDocumentFragment();

                            // OPRAVENO 2025-01-22: Reduced logging (only in DEBUG)
                            if (DEBUG) {
                                console.log('✅ Adding', rowsToAdd.length, 'new rows (batch append)');
                            }

                            // KRITICKÉ: Nastavit šířku prvního sloupce PŘED přidáním do DOM
                            // Toto je nutné, protože AJAX HTML nemusí mít inline styly
                            if (entity === 'translations') {
                                const firstColWidth = '300px';
                                rowsToAdd.forEach(row => {
                                    const firstTd = row.querySelector('td:first-child');
                                    if (firstTd) {
                                        firstTd.style.width = firstColWidth;
                                        firstTd.style.minWidth = firstColWidth;
                                        firstTd.style.maxWidth = firstColWidth;
                                        firstTd.style.whiteSpace = 'nowrap';
                                        firstTd.style.overflow = 'hidden';
                                        firstTd.style.textOverflow = 'ellipsis';
                                        firstTd.style.boxSizing = 'border-box';
                                    }
                                });
                            }

                            // Append to fragment (NO reflow)
                            rowsToAdd.forEach(row => {
                                fragment.appendChild(row);
                            });

                            // KRITICKÉ: Jen JEDEN reflow!
                            tbody.appendChild(fragment);

                            // ╔═══════════════════════════════════════════════════════════╗
                            // ║ NOVÁ LOGIKA: CHECK FOR PENDING ACTIVE ROW                ║
                            // ╚═══════════════════════════════════════════════════════════╝
                            if (scrollArea._needsActiveRow && scrollArea._pendingActiveRowId) {
                                const activeRowId = scrollArea._pendingActiveRowId;
                                const $activeRow = $('.sa-table-element tbody tr[data-id="' + activeRowId + '"]');

                                if ($activeRow.length) {
                                    // ===== ROW FOUND! SET IT ACTIVE AND SCROLL TO IT =====
                                    console.log('✅ Pending active row found after page load:', activeRowId);
                                    updateActiveRow(activeRowId, true); // true = scroll to row

                                    // Clear flags
                                    scrollArea._needsActiveRow = false;
                                    scrollArea._pendingActiveRowId = undefined;

                                    // ===== CRITICAL: LOCK SCROLL POSITION =====
                                    // Prevent restore from overwriting our scroll
                                    scrollArea._isLockedToActiveRow = true;
                                    setTimeout(() => {
                                        scrollArea._isLockedToActiveRow = false;
                                        console.log('🔓 Scroll lock released');
                                    }, 1000);
                                } else {
                                    // Not found yet, will check on next page load
                                    if (DEBUG) {
                                        console.log('⏳ Pending active row not found yet, will check again');
                                    }
                                }
                            }

                            // PŘIDÁNO 2025-01-22: Restore scroll position after reflow settles
                            requestAnimationFrame(() => {
                                const scrollHeightAfter = scrollArea.scrollHeight;
                                const heightDiff = scrollHeightAfter - scrollHeightBefore;

                                // Only adjust if height changed significantly
                                if (heightDiff > 50) {
                                    scrollArea.scrollTop = scrollPosBefore; // RESTORE pozici

                                    if (DEBUG) {
                                        console.log('📍 Scroll position restored:', scrollPosBefore, 'px');
                                    }
                                }

                                // PŘIDÁNO 2025-01-22: Unlock scroll after DOM settles
                                requestAnimationFrame(() => {
                                    isAppending = false;
                                    if (DEBUG) {
                                        console.log('🔓 Scroll unlocked');
                                    }

                                    // ✅ OPRAVA: Zavolat auto-load callback, pokud existuje (jen během auto-loading procesu)
                                    if (scrollArea._autoLoadCheckNeeded) {
                                        scrollArea._autoLoadCheckNeeded = false; // Vymazat flag
                                        setTimeout(() => {
                                            autoLoadActive = false; // Uvolnit flag
                                            // Zkontrolovat znovu jen pokud stále není scrollovatelné a nepřekročili jsme max
                                            const stillNotScrollable = scrollArea.scrollHeight <= scrollArea.clientHeight;
                                            const currentAttempts = scrollArea._autoLoadAttempts || 0;
                                            if (stillNotScrollable && currentAttempts < maxAutoLoadAttempts) {
                                                checkAndAutoLoad();
                                            } else if (DEBUG) {
                                                console.log('✅ Auto-loading stopped:', {
                                                    reason: stillNotScrollable ? 'max attempts reached' : 'scroll area is now scrollable',
                                                    isScrollable: !stillNotScrollable,
                                                    attempts: currentAttempts
                                                });
                                            }
                                            scrollArea._autoLoadAttempts = undefined; // Vymazat
                                        }, 800);
                                    }

                                    // ╔═══════════════════════════════════════════════════════════╗
                                    // ║ EXISTUJÍCÍ LOGIKA: CHECK FOR SCROLL RESTORE              ║
                                    // ║ (Tato část už existuje, nechat beze změny)               ║
                                    // ╚═══════════════════════════════════════════════════════════╝
                                    if (scrollArea._needsScrollRestore && scrollArea._pendingScrollRestore !== undefined) {
                                        // Only restore if NOT locked to active row
                                        if (!scrollArea._isLockedToActiveRow) {
                                            const pendingPage = scrollArea._pendingScrollPage || 1;
                                            const currentRowCount = tbody.querySelectorAll('tr.sa-table-row[data-id]').length;
                                            const expectedRowsForPage = pendingPage * perPage;

                                            // Check if we have enough rows now
                                            if (currentRowCount >= expectedRowsForPage - 10) {
                                                console.log('✅ Enough rows loaded, restoring scroll position');

                                                // Mark pages as loaded
                                                for (let i = 1; i <= pendingPage; i++) {
                                                    if (!loadedPages.has(i)) {
                                                        loadedPages.add(i);
                                                    }
                                                }
                                                currentPage = pendingPage;

                                                // Restore scroll position
                                                isRestoring = true;
                                                scrollArea._isProgrammaticScroll = true;

                                                requestAnimationFrame(() => {
                                                    scrollArea.scrollTop = scrollArea._pendingScrollRestore;
                                                    console.log('✅ Scroll position restored after loading pages');

                                                    // Reset flags after scroll settles
                                                    requestAnimationFrame(() => {
                                                        isRestoring = false;
                                                        scrollArea._isProgrammaticScroll = false;
                                                        scrollArea._needsScrollRestore = false;
                                                        scrollArea._pendingScrollRestore = undefined;
                                                        scrollArea._pendingScrollPage = undefined;

                                                        if (DEBUG) {
                                                            console.log('✅ Restoration complete, infinite scroll re-enabled');
                                                        }
                                                    });
                                                });
                                            } else {
                                                // Not enough rows yet, will check again on next page load
                                                if (DEBUG) {
                                                    console.log('⏳ Still need more rows, waiting for next page load');
                                                }
                                            }
                                        } else {
                                            console.log('🔒 Scroll restore skipped - locked to active row');
                                        }
                                    }
                                });
                            });

                        }
                    } else {
                        console.error('Failed to load items:', data.data?.message || 'Unknown error');
                        hasMore = false;
                    }

                    isLoading = false;

                    if (DEBUG) {
                        console.groupEnd();
                    }
                })
                .catch(error => {
                    console.error('AJAX error loading items:', error);
                    const loadingEl = tbody.querySelector('.saw-infinite-scroll-loading');
                    if (loadingEl) {
                        loadingEl.remove();
                    }
                    isLoading = false;
                    isAppending = false; // PŘIDÁNO 2025-01-22: Unlock i při erroru

                    if (DEBUG) {
                        console.groupEnd();
                    }
                });
        }

        /**
         * Handle scroll event
         * Only load when scrolling down, not when scrolling up
         * Cache prevents re-loading when scrolling back up
         */
        let lastScrollTop = scrollArea.scrollTop;
        let scrollTimeout;
        let scrollVelocity = 0;
        let lastScrollTime = Date.now();

        function handleScroll() {
            // ✅ DEBUG: Ověřit, že se handler spouští (vždy první 5x, pak jen občas)
            if (DEBUG) {
                if (!handleScroll._callCount) {
                    handleScroll._callCount = 0;
                }
                handleScroll._callCount++;
                if (handleScroll._callCount <= 5 || Math.random() < 0.1) {
                    console.log('🔄 Scroll event triggered (call #' + handleScroll._callCount + ')');
                }
            }

            // Skip if programmatic scroll (restoration)
            if (scrollArea._isProgrammaticScroll) {
                if (DEBUG && handleScroll._callCount <= 5) {
                    console.log('⏸️ Scroll skipped - programmatic scroll');
                }
                return;
            }

            // PŘIDÁNO 2025-01-22: Skip if appending rows
            if (isRestoring) {
                if (DEBUG) {
                    console.log('⏸️ Scroll paused - restoration or append in progress');
                }
                return;
            }

            const scrollTop = scrollArea.scrollTop;
            const scrollHeight = scrollArea.scrollHeight;
            const clientHeight = scrollArea.clientHeight;
            const now = Date.now();

            // Calculate scroll velocity (kept for debug logging only)
            const deltaTime = now - lastScrollTime;
            const deltaScroll = scrollTop - lastScrollTop;
            scrollVelocity = deltaTime > 0 ? deltaScroll / deltaTime : 0;

            // OPRAVENO 2025-01-22: Check scroll direction (velocity threshold removed for consistent loading)
            const isScrollingDown = deltaScroll > 5;

            lastScrollTop = scrollTop;
            lastScrollTime = now;

            // Don't load when scrolling up
            if (!isScrollingDown) {
                return;
            }

            if (isLoading || !hasMore) {
                if (DEBUG && !hasMore) {
                    console.log('⏸️ Load skipped - hasMore:', hasMore, 'isLoading:', isLoading);
                }
                return;
            }

            // Check if user has scrolled to threshold percentage OR is close to bottom
            const scrollPercent = (scrollTop + clientHeight) / scrollHeight;
            const distanceFromBottom = scrollHeight - (scrollTop + clientHeight);

            // OPRAVENO 2025-01-22: Reduced logging - only every 100px or threshold
            if (DEBUG && (Math.round(scrollTop) % 100 === 0 || scrollPercent >= threshold)) {
                console.log('📊 Scroll:', {
                    scrollTop: Math.round(scrollTop),
                    scrollPercent: Math.round(scrollPercent * 100) + '%',
                    distanceFromBottom: Math.round(distanceFromBottom) + 'px',
                    velocity: scrollVelocity.toFixed(2),
                    direction: isScrollingDown ? 'DOWN ⬇️' : 'UP ⬆️'
                });
            }

            // OPRAVENO 2025-01-22: Dual-trigger system:
            // - scrollPercent >= 60%: Works for large tables (many rows)
            // - distanceFromBottom <= 200px: Works for small tables (few rows)
            // Using OR (||) ensures at least one trigger always works
            if (scrollPercent >= threshold || distanceFromBottom <= 200) {
                console.log('📊 Scroll threshold reached:', {
                    percent: Math.round(scrollPercent * 100) + '%',
                    distance: Math.round(distanceFromBottom) + 'px',
                    triggeredBy: scrollPercent >= threshold ? 'threshold' : 'distance'
                });
                loadNextPage();
            }
        }

        // Attach scroll listener with throttling
        const scrollHandler = function () {
            if (scrollTimeout) {
                clearTimeout(scrollTimeout);
            }
            scrollTimeout = setTimeout(handleScroll, 16); // OPRAVENO 2025-01-22: ~60fps pro plynulejší scroll (16ms = 1 frame)
        };

        // ✅ DEBUG: Ověřit registraci scroll listeneru
        if (DEBUG) {
            console.log('📌 Registering scroll listener on:', scrollArea);
            console.log('📌 ScrollArea dimensions:', {
                scrollHeight: scrollArea.scrollHeight,
                clientHeight: scrollArea.clientHeight,
                scrollTop: scrollArea.scrollTop,
                isScrollable: scrollArea.scrollHeight > scrollArea.clientHeight
            });
        }

        scrollArea.addEventListener('scroll', scrollHandler, { passive: true });
        scrollArea._sawInfiniteScrollHandler = scrollHandler; // Store reference for cleanup

        // ✅ KRITICKÁ OPRAVA: Fallback pro případ, kdy scrollArea není scrollovatelné
        // Pokud scrollArea není scrollovatelné, scroll event se nespouští, takže musíme použít wheel event
        let lastWheelTime = 0;
        let wheelEventCount = 0;
        const wheelHandler = (e) => {
            if (DEBUG) {
                console.log('🔄 Wheel event detected:', {
                    deltaY: e.deltaY,
                    target: e.target,
                    currentTarget: e.currentTarget
                });
            }

            // Throttle wheel events
            const now = Date.now();
            if (now - lastWheelTime < 200) {
                if (DEBUG) {
                    console.log('⏸️ Wheel event throttled');
                }
                return;
            }
            lastWheelTime = now;

            // Zkontrolovat, zda scrollArea není scrollovatelné
            const isScrollable = scrollArea.scrollHeight > scrollArea.clientHeight;

            if (DEBUG) {
                console.log('🔍 Wheel handler check:', {
                    isScrollable: isScrollable,
                    hasMore: hasMore,
                    isLoading: isLoading,
                    deltaY: e.deltaY,
                    wheelEventCount: wheelEventCount
                });
            }

            // Pokud není scrollovatelné a uživatel scrolluje dolů, načíst další stránku
            if (!isScrollable && hasMore && !isLoading && e.deltaY > 0) {
                wheelEventCount++;
                if (DEBUG) {
                    console.log('📊 Wheel event count:', wheelEventCount);
                }
                // Po 3 wheel eventech (uživatel scrolluje dolů), načíst další stránku
                if (wheelEventCount >= 3) {
                    wheelEventCount = 0; // Reset counter
                    if (DEBUG) {
                        console.log('🔄 Wheel event fallback - loading next page (scrollArea not scrollable)');
                    }
                    loadNextPage();
                }
            } else if (isScrollable) {
                // Pokud je scrollovatelné, reset counter
                wheelEventCount = 0;
            }
        };

        // Přidat wheel handler na scrollArea
        scrollArea.addEventListener('wheel', wheelHandler, { passive: true });
        scrollArea._sawInfiniteScrollWheelHandler = wheelHandler; // Store reference for cleanup

        // ✅ PŘIDÁNO: Přidat wheel handler i na window jako fallback
        // Wheel event se může spouštět na window, pokud scrollArea není scrollovatelné
        const windowWheelHandler = (e) => {
            // Zkontrolovat, zda je kurzor nad scrollArea
            const rect = scrollArea.getBoundingClientRect();
            const isOverScrollArea = (
                e.clientX >= rect.left &&
                e.clientX <= rect.right &&
                e.clientY >= rect.top &&
                e.clientY <= rect.bottom
            );

            if (!isOverScrollArea) {
                return; // Ignorovat, pokud není nad scrollArea
            }

            // Zavolat původní wheel handler
            wheelHandler(e);
        };

        window.addEventListener('wheel', windowWheelHandler, { passive: true });
        scrollArea._sawInfiniteScrollWindowWheelHandler = windowWheelHandler; // Store reference for cleanup

        if (DEBUG) {
            console.log('✅ Wheel listener registered on scrollArea');
            console.log('✅ Wheel listener registered on window (fallback)');
        }

        // ✅ SCROLL TO TOP BUTTON
        const scrollToTopBtn = document.querySelector('.saw-scroll-to-top');
        if (scrollToTopBtn) {
            const showScrollThreshold = 300; // Show button after scrolling 300px

            const updateScrollToTopButton = () => {
                const scrollTop = scrollArea.scrollTop;
                if (scrollTop > showScrollThreshold) {
                    scrollToTopBtn.classList.add('visible');
                } else {
                    scrollToTopBtn.classList.remove('visible');
                }
            };

            // Update on scroll
            scrollArea.addEventListener('scroll', updateScrollToTopButton, { passive: true });

            // Click handler
            scrollToTopBtn.addEventListener('click', () => {
                scrollArea.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });

            // Initial check
            updateScrollToTopButton();
        }

        // ✅ KRITICKÁ OPRAVA: Pokud scrollArea není scrollovatelné, automaticky načíst několik stránek
        // Toto je důležité, protože pokud máme jen 100 řádků a scrollArea je přesně tak velké,
        // že se vejde celý obsah, není scrollovatelné, takže scroll event se nespustí
        // Ale načteme jen tolik, aby bylo scrollovatelné (max 2 stránky), pak se zastaví a čeká na scroll
        let autoLoadAttempts = 0;
        const maxAutoLoadAttempts = 2; // Max 2 pokusy na auto-loading (načte max 2 stránky navíc = 200 řádků celkem)
        let autoLoadActive = false; // Flag, aby se auto-loading nespouštěl opakovaně

        const checkAndAutoLoad = () => {
            // Pokud už probíhá auto-loading, nezačínat nový
            if (autoLoadActive) {
                return;
            }

            const isScrollable = scrollArea.scrollHeight > scrollArea.clientHeight;
            const currentRowCount = tbody.querySelectorAll('tr.sa-table-row[data-id]').length;

            if (DEBUG) {
                console.log('🔍 Checking if auto-load needed:', {
                    isScrollable: isScrollable,
                    currentRowCount: currentRowCount,
                    hasMore: hasMore,
                    isLoading: isLoading,
                    autoLoadAttempts: autoLoadAttempts,
                    maxAttempts: maxAutoLoadAttempts
                });
            }

            // ✅ OPRAVA: Auto-loading jen pokud:
            // 1. Není scrollovatelné
            // 2. Máme více záznamů
            // 3. Není právě loading
            // 4. Nepřekročili jsme max počet pokusů
            // 5. Máme méně než totalItems
            if (!isScrollable && hasMore && !isLoading && currentRowCount < totalItems && autoLoadAttempts < maxAutoLoadAttempts) {
                autoLoadActive = true;
                autoLoadAttempts++;
                if (DEBUG) {
                    console.log('🚀 Auto-loading next page - scroll area not scrollable yet (attempt ' + autoLoadAttempts + '/' + maxAutoLoadAttempts + ')');
                    console.log('📊 Current state:', {
                        rows: currentRowCount,
                        total: totalItems,
                        hasMore: hasMore
                    });
                }
                // Načíst další stránku
                // ✅ OPRAVA: Zaregistrovat flag, že probíhá auto-loading, aby se callback zavolal po načtení
                scrollArea._autoLoadCheckNeeded = true;
                scrollArea._autoLoadAttempts = autoLoadAttempts;

                loadNextPage();

                // ✅ OPRAVA: Callback se zavolá po načtení v requestAnimationFrame (viz níže)
                // Toto zajistí, že se checkAndAutoLoad nespustí po každém načtení, ale jen během auto-loading procesu
            } else if (DEBUG && !isScrollable && autoLoadAttempts >= maxAutoLoadAttempts) {
                console.log('⏸️ Auto-loading stopped - max attempts reached. Scroll area should be scrollable now.');
            }
        };

        // Zkontrolovat po malém zpoždění (až se DOM vykreslí) - jen jednou při inicializaci
        setTimeout(checkAndAutoLoad, 500);

        // Save scroll position on page unload
        window.addEventListener('beforeunload', function () {
            if (scrollArea && scrollArea.scrollTop > 0) {
                sessionStorage.setItem(`saw-scroll-${entity}`, scrollArea.scrollTop.toString());
                sessionStorage.setItem(`saw-page-${entity}`, currentPage.toString());
            }
        });

        // Restore scroll position on page load
        const savedScroll = sessionStorage.getItem(`saw-scroll-${entity}`);
        const savedPage = sessionStorage.getItem(`saw-page-${entity}`);
        if (savedScroll && savedPage) {
            const scrollPos = parseInt(savedScroll, 10);
            const page = parseInt(savedPage, 10);

            console.log('📍 Restoring scroll position:', scrollPos, 'page:', page);

            // CRITICAL: Only mark pages as loaded if rows actually exist in DOM
            const existingRows = tbody.querySelectorAll('tr.saw-table-row[data-id]').length;
            const expectedRowsForPage = page * perPage;

            if (existingRows >= expectedRowsForPage - 10) { // -10 for tolerance
                // Rows exist in DOM, mark pages as loaded
                if (DEBUG) {
                    console.log('✅ Rows exist in DOM, marking pages as loaded');
                }
                for (let i = 1; i <= page; i++) {
                    loadedPages.add(i);
                }
                currentPage = page;

                // OPRAVENO 2025-01-22: Set restoration flag to prevent loading during scroll
                isRestoring = true;
                scrollArea._isProgrammaticScroll = true; // Flag pro programatický scroll

                // Restore scroll position using requestAnimationFrame for better timing
                requestAnimationFrame(() => {
                    scrollArea.scrollTop = scrollPos;
                    console.log('✅ Scroll position restored');

                    // Reset flags after scroll settles
                    requestAnimationFrame(() => {
                        isRestoring = false;
                        scrollArea._isProgrammaticScroll = false;
                        if (DEBUG) {
                            console.log('✅ Restoration complete, infinite scroll re-enabled');
                        }
                    });
                });
            } else {
                // Rows don't exist, need to load them first
                // Don't mark pages as loaded yet - they will be marked when actually loaded
                console.log('⚠️ Rows missing for page', page, '- existing:', existingRows, 'expected:', expectedRowsForPage);

                // Calculate actual current page based on existing rows
                currentPage = Math.ceil(existingRows / perPage);
                if (currentPage < 1) currentPage = 1;

                // Mark only pages that actually have rows in DOM
                for (let i = 1; i <= currentPage; i++) {
                    loadedPages.add(i);
                }

                // Don't restore scroll position yet - let infinite scroll load missing pages first
                // The scroll will be restored naturally when user scrolls or when pages are loaded
                // Store the target scroll position for later restoration
                scrollArea._pendingScrollRestore = scrollPos;
                scrollArea._pendingScrollPage = page;

                if (DEBUG) {
                    console.log('⏳ Deferring scroll restoration - will restore after pages are loaded');
                }

                // Set a flag to restore scroll after pages are loaded
                // This will be checked in loadNextPage after successful load
                scrollArea._needsScrollRestore = true;

                // If we have pending active row, also trigger page loading
                if (scrollArea._needsActiveRow && scrollArea._pendingActiveRowId) {
                    // Calculate which page might contain this row ID
                    // For now, just start loading pages
                    console.log('📥 Triggering page load for active row:', scrollArea._pendingActiveRowId);
                    // Load next page to start the process
                    setTimeout(() => {
                        if (!isLoading && hasMore) {
                            loadNextPage();
                        }
                    }, 200);
                }
            }

            // Clear saved position
            sessionStorage.removeItem(`saw-scroll-${entity}`);
            sessionStorage.removeItem(`saw-page-${entity}`);
        }

        // ============================================
        // MOBILE/TABLET FALLBACK: IntersectionObserver
        // ============================================
        // IntersectionObserver funguje i když scroll eventy nefungují
        // (např. když na mobilu scrolluje window místo scrollArea)
        // Toto je ADDITIVNÍ - nerozbije stávající desktop funkcionalitu

        function initIntersectionObserverFallback() {
            // Zkontroluj podporu (iOS 12.2+, Android 5+, všechny moderní browsery)
            if (!('IntersectionObserver' in window)) {
                if (DEBUG) {
                    console.log('⚠️ IntersectionObserver not supported - skipping fallback');
                }
                return;
            }

            // Odstraň předchozí sentinel pokud existuje (cleanup při AJAX navigation)
            const existingSentinel = tbody.querySelector('.saw-infinite-scroll-sentinel');
            if (existingSentinel) {
                existingSentinel.remove();
            }

            // Odstraň předchozí observer pokud existuje
            if (scrollArea._sawInfiniteScrollObserver) {
                scrollArea._sawInfiniteScrollObserver.disconnect();
                scrollArea._sawInfiniteScrollObserver = null;
            }

            // Vytvoř sentinel element - neviditelný řádek na konci tabulky
            const sentinel = document.createElement('tr');
            sentinel.className = 'saw-infinite-scroll-sentinel';
            sentinel.setAttribute('aria-hidden', 'true');
            sentinel.innerHTML = '<td colspan="100" class="saw-sentinel-cell"></td>';
            tbody.appendChild(sentinel);

            // Vytvoř IntersectionObserver
            // DŮLEŽITÉ: root: null = sleduje vůči VIEWPORT (celé obrazovce)
            // Tím pádem funguje i když scrolluje window místo scrollArea
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        if (DEBUG) {
                            console.log('👁️ IntersectionObserver: Sentinel is visible', {
                                hasMore: hasMore,
                                isLoading: isLoading,
                                intersectionRatio: entry.intersectionRatio
                            });
                        }

                        // Načti další stránku pokud je co načítat a neprobíhá načítání
                        if (hasMore && !isLoading) {
                            if (DEBUG) {
                                console.log('👁️ IntersectionObserver: Triggering loadNextPage()');
                            }
                            loadNextPage();
                        }
                    }
                });
            }, {
                root: null,           // null = viewport (funguje i při window scroll)
                rootMargin: '400px',  // Načti 400px PŘED tím, než uživatel dorazí na konec
                threshold: 0          // Spustí se jakmile je vidět 1px sentinelu
            });

            // Spusť pozorování
            observer.observe(sentinel);

            // Ulož referenci pro cleanup
            scrollArea._sawInfiniteScrollObserver = observer;
            scrollArea._sawInfiniteScrollSentinel = sentinel;

            if (DEBUG) {
                console.log('✅ IntersectionObserver fallback initialized', {
                    sentinel: sentinel,
                    rootMargin: '400px',
                    threshold: 0
                });
            }
        }

        // Funkce pro PŘESUN sentinelu na konec tabulky po načtení nových řádků
        // Musí se volat po každém úspěšném načtení
        function moveSentinelToEnd() {
            const sentinel = tbody.querySelector('.saw-infinite-scroll-sentinel');
            if (sentinel && tbody.lastElementChild !== sentinel) {
                tbody.appendChild(sentinel);
                if (DEBUG) {
                    console.log('📍 Sentinel moved to end of tbody');
                }
            }
        }

        // Ulož funkci globálně pro volání z loadNextPage
        scrollArea._moveSentinelToEnd = moveSentinelToEnd;

        // Inicializuj IntersectionObserver fallback
        initIntersectionObserverFallback();

        console.log('✅ Infinite scroll initialized for', entity, '- Enhanced cache mode');
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
        const $tabs = $('.sa-table-tabs');

        if (!$searchForm.length || !$tabs.length) {
            return;
        }

        $searchForm.on('submit', function () {
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

    /**
     * Initialize sticky header - FIXED POSITION APPROACH
     * 
     * Since CSS sticky doesn't work reliably, we use position:fixed
     * like the main header does. Creates fixed clones of toolbar and thead.
     */
    function initStickyHeader() {
        console.log('🏗️ Sticky Header: Initializing with FIXED position approach...');

        const scrollArea = document.querySelector('.sa-table-scroll');
        const toolbar = document.querySelector('.sa-table-toolbar');
        const thead = document.querySelector('.sa-table-thead');

        if (!scrollArea || !toolbar) {
            console.warn('❌ Missing elements for sticky header');
            return;
        }

        // Get header height for offset calculation
        const mainHeader = document.querySelector('.sa-app-header');
        const headerHeight = mainHeader ? mainHeader.offsetHeight : 60;

        // Create fixed container for sticky elements
        const fixedContainer = document.createElement('div');
        fixedContainer.className = 'sa-fixed-header-container';
        fixedContainer.style.cssText = `
            position: fixed;
            top: ${headerHeight}px;
            left: 0;
            right: 0;
            z-index: 500;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: none;
        `;

        // Clone toolbar
        const toolbarClone = toolbar.cloneNode(true);
        toolbarClone.className = 'sa-table-toolbar sa-table-toolbar--fixed';
        toolbarClone.style.cssText = `
            position: relative;
            top: 0;
            margin: 0;
        `;
        fixedContainer.appendChild(toolbarClone);

        // Clone thead if exists
        let theadClone = null;
        if (thead) {
            // Create a wrapper table for the thead clone
            const table = thead.closest('table');
            if (table) {
                const tableClone = document.createElement('table');
                tableClone.className = table.className;
                tableClone.style.cssText = table.style.cssText + '; margin: 0; border-collapse: collapse;';

                // Copy colgroup if exists
                const colgroup = table.querySelector('colgroup');
                if (colgroup) {
                    tableClone.appendChild(colgroup.cloneNode(true));
                }

                theadClone = thead.cloneNode(true);
                theadClone.style.cssText = 'position: relative; top: 0;';
                tableClone.appendChild(theadClone);

                fixedContainer.appendChild(tableClone);
            }
        }

        // Add to DOM
        document.body.appendChild(fixedContainer);

        // Get original positions
        const toolbarRect = toolbar.getBoundingClientRect();
        const toolbarTop = toolbarRect.top + window.scrollY;

        // Handle scroll to show/hide fixed header
        let isFixed = false;
        const scrollHandler = () => {
            const scrollTop = scrollArea.scrollTop;
            const shouldBeFixed = scrollTop > 10; // Show after 10px scroll

            if (shouldBeFixed !== isFixed) {
                isFixed = shouldBeFixed;
                fixedContainer.style.display = isFixed ? 'block' : 'none';

                // Sync horizontal scroll
                if (isFixed && theadClone) {
                    const tableWrapper = fixedContainer.querySelector('table');
                    if (tableWrapper) {
                        tableWrapper.style.marginLeft = `-${scrollArea.scrollLeft}px`;
                    }
                }
            }

            // Always sync horizontal scroll when fixed
            if (isFixed) {
                const tableWrapper = fixedContainer.querySelector('table');
                if (tableWrapper) {
                    tableWrapper.style.marginLeft = `-${scrollArea.scrollLeft}px`;
                }
            }
        };

        // Attach scroll listener
        scrollArea.addEventListener('scroll', scrollHandler, { passive: true });

        // Sync clicks from cloned toolbar to original
        toolbarClone.addEventListener('click', (e) => {
            const targetTab = e.target.closest('.sa-table-tab');
            if (targetTab) {
                const tabValue = targetTab.dataset.tab || targetTab.getAttribute('href');
                const originalTab = toolbar.querySelector(`.sa-table-tab[data-tab="${targetTab.dataset.tab}"], .sa-table-tab[href="${tabValue}"]`);
                if (originalTab) {
                    originalTab.click();
                }
            }
        });

        // Update container width on resize
        const resizeHandler = () => {
            const panelRect = scrollArea.getBoundingClientRect();
            fixedContainer.style.left = `${panelRect.left}px`;
            fixedContainer.style.width = `${panelRect.width}px`;
        };

        resizeHandler(); // Initial sizing
        window.addEventListener('resize', resizeHandler, { passive: true });

        console.log('✅ Sticky Header: Fixed position initialized');
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
            $('.saw-sidebar').each(function () {
                const entity = $(this).data('entity');
                const currentId = $(this).data('current-id');
                console.log('  - Sidebar:', { entity, currentId, mode: $(this).data('mode') });
            });
        }

        // Initialize infinite scroll
        initInfiniteScroll();

        // Initialize global search handling
        handleGlobalSearch();

        // Initialize sticky header dynamic height
        initStickyHeader();
    });

})(jQuery);
