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
        setActiveRowFromUrl();

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
     * Initialize on DOM ready
     */
    $(document).ready(function () {
        initAdminTable();
    });

})(jQuery);
