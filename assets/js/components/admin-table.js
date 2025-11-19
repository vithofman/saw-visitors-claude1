/**
 * Admin Table Component JavaScript
 *
 * Handles clickable table rows with AJAX sidebar loading.
 * Supports both modal and sidebar modes with smooth navigation.
 *
 * @package    SAW_Visitors
 * @subpackage Components
 * @version    4.1.0 - FIXED: Export updateActiveRow as global + scroll preservation
 * @since      1.0.0
 */

(function ($) {
    'use strict';

    /**
     * Open sidebar via AJAX
     *
     * @param {number} id    Item ID
     * @param {string} mode  Mode: 'detail' or 'edit'
     * @param {string} entity Entity name (e.g., 'customers')
     * @return {void}
     */
    window.openSidebarAjax = function (id, mode, entity) {
        // For create mode, id can be 0 or undefined
        if ((mode !== 'create' && (!id || id === 0)) || !mode || !entity) {
            console.error('openSidebarAjax: Missing required parameters', { id, mode, entity });
            return;
        }

        console.log('📊 Opening sidebar via AJAX:', { id, mode, entity });

        // Get or create wrapper BEFORE AJAX to avoid flicker
        let $wrapper = $('.saw-sidebar-wrapper');
        if (!$wrapper.length) {
            console.log('🆕 Creating new sidebar wrapper');
            $wrapper = $('<div class="saw-sidebar-wrapper"></div>');
            $('body').append($wrapper);
        }

        // Check if sidebar is already open (switching content, not opening new)
        const isAlreadyOpen = $wrapper.hasClass('active');
        
        // CRITICAL: When switching content (e.g., edit -> detail), keep wrapper open!
        // Don't remove active class, just update content
        if (isAlreadyOpen) {
            console.log('🔄 Sidebar already open - switching content (keeping wrapper open)');
            $wrapper.addClass('switching');
            // Ensure wrapper stays visible
            $wrapper.addClass('active');
        } else {
            // New sidebar - show loading
            console.log('🆕 Opening new sidebar');
            $wrapper.addClass('loading');
        }

        $.ajax({
            url: (window.sawGlobal && window.sawGlobal.ajaxurl) || '/wp-admin/admin-ajax.php',
            method: 'POST',
            timeout: 10000,
            data: {
                action: 'saw_load_sidebar_' + entity,
                nonce: (window.sawGlobal && window.sawGlobal.nonce) || window.sawAjaxNonce,
                id: id,
                mode: mode
            },
            beforeSend: function () {
                console.log('⏳ Loading sidebar content...');
            },
            success: function (response) {
                console.log('✅ AJAX Response received:', response);

                if (response.success && response.data && response.data.html) {
                    // Check if sidebar is already open (switching content, not opening new)
                    const wasAlreadyOpen = $wrapper.hasClass('active');
                    
                    // Update content smoothly
                    $wrapper.html(response.data.html);
                    $wrapper.removeClass('loading switching').addClass('active');

                    // Add padding to table when sidebar opens (only if not already open)
                    if (!wasAlreadyOpen) {
                        $('.saw-admin-table-split').addClass('has-sidebar');
                    }
                    
                    // Ensure wrapper stays visible when switching content
                    // Sidebar wrapper should remain open and visible throughout the switch

                    // Update active row highlight
                    updateActiveRow(id, false);

                    // Update browser URL
                    const newUrl = buildUrl(entity, id, mode);
                    console.log('🔗 Updating URL to:', newUrl);

                    window.history.pushState(
                        {
                            id: id,
                            mode: mode,
                            entity: entity,
                            url: newUrl,
                            sawAdminTable: true
                        },
                        '',
                        newUrl
                    );

                    console.log('✅ Sidebar opened successfully', wasAlreadyOpen ? '(content switched)' : '(newly opened)');
                } else {
                    console.error('❌ Invalid AJAX response:', response);
                    $wrapper.removeClass('loading');
                    alert('Chyba při načítání detailu. Zkuste to prosím znovu.');
                }
            },
            error: function (xhr, status, error) {
                console.error('❌ AJAX Error:', { xhr, status, error });
                $wrapper.removeClass('loading');
                alert('Chyba připojení. Zkuste to prosím znovu.');
            }
        });
    };

    /**
     * Close sidebar
     *
     * @param {string} listUrl URL to navigate back to list
     * @return {void}
     */
    window.closeSidebar = function (listUrl) {
    console.log('🚪 Closing sidebar, listUrl:', listUrl);

    const $wrapper = $('.saw-sidebar-wrapper');

    if (!$wrapper.length) {
        console.log('⚠️ No wrapper found');
        return;
    }

    $wrapper.removeClass('active');
    $('.saw-admin-table-split').removeClass('has-sidebar');

    setTimeout(function () {
        $wrapper.html('');
        console.log('✅ Sidebar closed');
    }, 300);

    $('.saw-admin-table tbody tr').removeClass('saw-row-active');

    // ✅ FIX: Ignore '#' and extract proper URL from current path
    if (listUrl && listUrl !== '#') {
        console.log('🔗 Updating URL to:', listUrl);
        window.history.pushState(
            { url: listUrl, sawAdminTable: true },
            '',
            listUrl
        );
    } else {
        // ✅ Build list URL from current path
        const currentUrl = window.location.pathname;
        const pathParts = currentUrl.split('/').filter(p => p);
        
        // Remove ID from end (e.g., /admin/visits/44 -> /admin/visits)
        if (pathParts.length > 0 && !isNaN(pathParts[pathParts.length - 1])) {
            pathParts.pop();
        }
        
        const properListUrl = '/' + pathParts.join('/') + '/';
        
        console.log('🔗 Built list URL from path:', properListUrl);
        window.history.pushState(
            { url: properListUrl, sawAdminTable: true },
            '',
            properListUrl
        );
    }
};

    /**
     * Show loading overlay
     *
     * @return {void}
     */
    function showLoadingOverlay() {
        let $overlay = $('#saw-loading-overlay');

        if (!$overlay.length) {
            $overlay = $('<div id="saw-loading-overlay" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.8);z-index:500;display:flex;align-items:center;justify-content:center;"><div class="saw-spinner" style="width:40px;height:40px;border:4px solid #e5e7eb;border-top-color:#3b82f6;border-radius:50%;animation:spin 0.8s linear infinite;"></div></div>');
            $('body').append($overlay);

            $('<style>@keyframes spin{to{transform:rotate(360deg);}}</style>').appendTo('head');
        }

        $overlay.fadeIn(200);
    }

    /**
     * Hide loading overlay
     *
     * @return {void}
     */
    function hideLoadingOverlay() {
        $('#saw-loading-overlay').fadeOut(200);
    }

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

        // CRITICAL FIX: Scroll active row into view
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
            }, 100); // Wait for DOM and animations to settle
        }
    }

    // ✅ EXPORT updateActiveRow as global function
    window.updateActiveRow = updateActiveRow;

    /**
     * Build URL from entity, id and mode
     *
     * @param {string} entity Entity name
     * @param {number} id Item ID
     * @param {string} mode Mode: 'detail', 'edit', or 'create'
     * @return {string}
     */
    function buildUrl(entity, id, mode) {
        const base = '/admin/' + entity + '/';

        if (mode === 'create') {
            return base + 'create';
        }

        if (mode === 'edit') {
            return base + id + '/edit';
        }

        return base + id + '/';
    }

    /**
     * Fallback to full page reload
     *
     * @param {string} url URL to navigate to
     * @return {void}
     */
    function fallbackToFullReload(url) {
        if (url) {
            console.log('🔄 Fallback to full reload:', url);
            window.location.href = url;
        }
    }

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

        // ✅ OPRAVENO: Najdi entity - může být /admin/entity/ NEBO /admin/settings/entity/
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
     * CRITICAL FIX: Set active row on page load from URL
     * 
     * This ensures that when user opens a detail URL directly 
     * (e.g., /admin/settings/customers/24/), the corresponding 
     * table row is highlighted and scrolled into view.
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
        // CRITICAL FIX: Set active row on page load
        setActiveRowFromUrl();

        // Clickable table rows - USE CAPTURE PHASE for earlier interception
        document.addEventListener('click', function (e) {
            // Find if we clicked inside a table row
            const $row = $(e.target).closest('.saw-admin-table tbody tr');

            if (!$row.length) {
                return; // Not in a table row
            }

            // Ignore clicks on action buttons and form inputs ONLY
            if ($(e.target).closest('.saw-action-buttons, button, input, select').length) {
                return;
            }

            const itemId = $row.data('id');
            if (!itemId) {
                return;
            }

            // ========================================
            // CRITICAL: Stop IMMEDIATELY in capture phase
            // AND prevent ALL other handlers (including SPA navigation!)
            // ========================================
            e.stopPropagation();
            e.stopImmediatePropagation(); // CRITICAL: Stops SPA navigation handler!
            e.preventDefault();

            console.warn('🔴 ADMIN TABLE: ROW CLICKED!', { id: itemId });
            console.warn('🔴 Event prevented and stopped IMMEDIATELY');

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

            // Sidebar mode (AJAX)
            const detailUrl = $row.data('detail-url');

            if (detailUrl) {
                console.log('📊 Detail URL found:', detailUrl);

                const parsed = parseUrl(detailUrl);
                console.log('📊 Parsed URL:', parsed);

                if (parsed.entity && parsed.id) {
                    console.log('📊 Calling openSidebarAjax');

                    // CRITICAL FIX: Set active row IMMEDIATELY on click
                    updateActiveRow(itemId, false);

                    openSidebarAjax(parsed.id, parsed.mode, parsed.entity);
                } else {
                    console.warn('⚠️ Could not parse URL, falling back to full reload');
                    fallbackToFullReload(detailUrl);
                }
            }
        }, true); // TRUE = CAPTURE PHASE!

        // Close sidebar button
        // NOTE: This handler has LOWER priority - sidebar.js handles form modes first with stopImmediatePropagation
        // We use a delayed check to see if sidebar.js already handled it
        $(document).on('click', '.saw-sidebar-close', function (e) {
            const $btn = $(this);
            const $sidebar = $('.saw-sidebar');
            const mode = $sidebar.attr('data-mode');
            
            // If in form mode (create/edit), let sidebar.js handle it
            // sidebar.js will call stopImmediatePropagation, so this handler won't run
            if (mode === 'create' || mode === 'edit') {
                // Don't do anything - sidebar.js will handle it
                return;
            }

            e.preventDefault();
            e.stopPropagation();

            const listUrl = $btn.attr('href');
            closeSidebar(listUrl);
        });

        // CRITICAL FIX: Browser back/forward button support
        window.addEventListener('popstate', function (e) {
            console.log('🔙 Browser back/forward detected', e.state);

            if (e.state && e.state.id && e.state.entity) {
                // Reopen sidebar via AJAX
                console.log('📊 Reopening sidebar from history');
                openSidebarAjax(e.state.id, e.state.mode, e.state.entity);
            } else {
                // Close sidebar (we're back at list view)
                console.log('🚪 Closing sidebar from history');
                const $wrapper = $('.saw-sidebar-wrapper');
                if ($wrapper.length && $wrapper.hasClass('active')) {
                    $wrapper.removeClass('active');
                    $('.saw-admin-table-split').removeClass('has-sidebar');
                    setTimeout(function () {
                        $wrapper.html('');
                    }, 300);
                    $('.saw-admin-table tbody tr').removeClass('saw-row-active');
                }
            }
        });

        // Floating button (Add New) - open create form via AJAX
        $(document).on('click', '.saw-floating-button', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const $btn = $(this);
            const createUrl = $btn.attr('href');

            if (!createUrl || createUrl === '#') {
                console.warn('⚠️ No create URL found');
                return;
            }

            // Extract entity from URL (e.g., /admin/settings/customers/create -> customers)
            const parsed = parseUrl(createUrl);
            if (parsed.entity) {
                console.log('➕ Opening create form via AJAX:', parsed.entity);
                // For create mode, id is 0
                openSidebarAjax(0, 'create', parsed.entity);
            } else {
                console.warn('⚠️ Could not parse create URL, falling back to full reload');
                window.location.href = createUrl;
            }
        });

        // AJAX form submit handler for sidebar forms
        $(document).on('submit', '.saw-sidebar form', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const $form = $(this);
            const $sidebar = $form.closest('.saw-sidebar');
            const mode = $sidebar.attr('data-mode');
            const entity = $sidebar.attr('data-entity');
            const currentId = $sidebar.data('current-id') || 0;

            // Only handle create/edit forms in sidebar
            if (mode !== 'create' && mode !== 'edit') {
                return true; // Let form submit normally
            }

            console.log('📝 Submitting sidebar form via AJAX:', { mode, entity, currentId });

            // Check if form has validation errors
            if (!$form[0].checkValidity()) {
                $form[0].reportValidity();
                return false;
            }

            // Disable submit button and show loading
            const $submitBtn = $form.find('button[type="submit"], input[type="submit"]');
            const originalHtml = $submitBtn.html();
            $submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update-alt saw-spin"></span> Ukládání...');

            // Prepare form data
            // Get form data including hidden fields
            const formData = $form.serialize();
            
            // Normalize entity name (replace hyphens with underscores for AJAX action)
            const entityNormalized = entity.replace(/-/g, '_');
            const action = mode === 'create' 
                ? 'saw_create_' + entityNormalized 
                : 'saw_edit_' + entityNormalized;

            $.ajax({
                url: (window.sawGlobal && window.sawGlobal.ajaxurl) || '/wp-admin/admin-ajax.php',
                method: 'POST',
                timeout: 30000,
                data: formData + '&action=' + action + '&_ajax_sidebar_submit=1&nonce=' + ((window.sawGlobal && window.sawGlobal.nonce) || window.sawAjaxNonce),
                success: function (response) {
                    console.log('✅ Form submit response:', response);

                    if (response.success) {
                        // Get created/updated ID
                        const newId = response.data.id || currentId;

                        if (mode === 'create' && newId) {
                            // After create -> show detail
                            console.log('✅ Create successful, showing detail:', newId);
                            openSidebarAjax(newId, 'detail', entity);
                        } else if (mode === 'edit' && newId) {
                            // After edit -> show detail
                            console.log('✅ Edit successful, showing detail:', newId);
                            openSidebarAjax(newId, 'detail', entity);
                        } else {
                            console.error('❌ No ID returned after save');
                            alert('Chyba: Nepodařilo se získat ID záznamu');
                            $submitBtn.prop('disabled', false).html(originalHtml);
                        }
                    } else {
                        // Show errors
                        console.error('❌ Form submit failed:', response.data);
                        const errorMsg = response.data && response.data.message 
                            ? response.data.message 
                            : 'Chyba při ukládání';
                        
                        // Try to show errors in form
                        if (response.data && response.data.errors) {
                            alert(errorMsg + '\n\n' + response.data.errors.join('\n'));
                        } else {
                            alert(errorMsg);
                        }
                        
                        $submitBtn.prop('disabled', false).html(originalHtml);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('❌ AJAX error:', { xhr, status, error });
                    alert('Chyba připojení. Zkuste to prosím znovu.');
                    $submitBtn.prop('disabled', false).html(originalHtml);
                }
            });

            return false;
        });

        console.log('✅ Admin Table initialized v5.0.0 - AJAX sidebar support');
    }

    /**
     * Initialize on DOM ready
     */
    $(document).ready(function () {
        initAdminTable();
    });

})(jQuery);