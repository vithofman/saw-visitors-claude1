/**
 * Sidebar Navigation - HOTFIX EDITION
 *
 * Handles keyboard navigation and prev/next controls for sidebar.
 * Supports AJAX loading for seamless navigation.
 * 
 * HOTFIX v5.0.2:
 * - Opraveno: Duplicitní delete handlers (sidebar.js vs saw-app.js)
 * - Opraveno: "Zákazník nenalezen" po smazání
 * - Opraveno: Dvakrát confirm dialog
 * - Sidebar se zavře PŘED reload, aby se nezobrazila chyba
 *
 * @package     SAW_Visitors
 * @subpackage  Components/AdminTable
 * @version     5.0.2 - HOTFIX: Delete handler improvements
 * @since       4.0.0
 */

(function ($) {
    'use strict';

    /**
     * Get AJAX URL with fallback
     * 
     * @return {string}
     */
    function getAjaxUrl() {
        return (window.sawGlobal && window.sawGlobal.ajaxurl) ||
            window.ajaxurl ||
            '/wp-admin/admin-ajax.php';
    }

    /**
     * Get nonce with fallback chain
     * 
     * Priority:
     * 1. sawGlobal.nonce (from Asset Manager)
     * 2. sawGlobal.deleteNonce (legacy)
     * 3. sawAjaxNonce (fallback)
     * 
     * @return {string}
     */
    function getNonce() {
        if (window.sawGlobal && window.sawGlobal.nonce) {
            return window.sawGlobal.nonce;
        }

        if (window.sawGlobal && window.sawGlobal.deleteNonce) {
            console.warn('⚠️ Using legacy deleteNonce');
            return window.sawGlobal.deleteNonce;
        }

        if (window.sawAjaxNonce) {
            console.warn('⚠️ Using fallback sawAjaxNonce');
            return window.sawAjaxNonce;
        }

        console.error('❌ NO NONCE AVAILABLE!');
        return '';
    }

    /**
     * Navigate to previous or next item in sidebar
     *
     * @param {string} direction Direction: 'prev' or 'next'
     * @return {void}
     */
    window.saw_navigate_sidebar = function (direction) {
        const $sidebar = $('.saw-sidebar');

        if (!$sidebar.length) {
            console.error('❌ Sidebar not found');
            return;
        }

        const currentId = parseInt($sidebar.data('current-id') || 0);

        if (!currentId) {
            console.error('❌ No current ID');
            return;
        }

        const $rows = $('.saw-table-panel tbody tr[data-id]').toArray();

        if (!$rows.length) {
            console.error('❌ No table rows found');
            return;
        }

        const rowIds = $rows.map(function (row) {
            return parseInt($(row).data('id'));
        });

        const currentIndex = rowIds.indexOf(currentId);

        if (currentIndex === -1) {
            console.error('❌ Current ID not found in rows');
            return;
        }

        let targetId = null;

        if (direction === 'prev' && currentIndex > 0) {
            targetId = rowIds[currentIndex - 1];
        } else if (direction === 'next' && currentIndex < rowIds.length - 1) {
            targetId = rowIds[currentIndex + 1];
        }

        if (targetId) {
            const mode = $sidebar.attr('data-mode') || 'detail';
            const entity = $sidebar.attr('data-entity') || extractEntityFromUrl();

            console.log('🔄 Navigating:', { direction, currentId, targetId, mode, entity });

            if (entity && typeof window.openSidebarAjax === 'function') {
                window.openSidebarAjax(targetId, mode, entity);

                // ✅ PŘIDEJ TOTO - zvýrazni aktivní řádek BEZ scrollu
                setTimeout(function () {
                    if (typeof window.updateActiveRow === 'function') {
                        window.updateActiveRow(targetId, false);
                        console.log('✨ Active row updated for:', targetId);
                    }
                }, 300);
            }
        }
    };

    /**
     * Extract entity name from current URL
     *
     * @return {string|null}
     */
    function extractEntityFromUrl() {
        const path = window.location.pathname.split('/').filter(function (p) { return p; });

        for (let i = 0; i < path.length; i++) {
            if (path[i] === 'settings' && path[i + 1]) {
                return path[i + 1];
            }
        }

        // Fallback: try to get entity from path
        if (path.length >= 3 && path[0] === 'admin') {
            return path[1];
        }

        return null;
    }

    /**
     * Navigate using URL replacement (fallback)
     *
     * @param {number} targetId Target item ID
     * @return {void}
     */
    function navigateWithUrl(targetId) {
        const url = new URL(window.location);
        const path = url.pathname.split('/').filter(function (p) { return p; });

        for (let i = path.length - 1; i >= 0; i--) {
            if (!isNaN(path[i]) && parseInt(path[i]) > 0) {
                path[i] = targetId;
                break;
            }
        }

        const newUrl = '/' + path.join('/') + '/';
        window.location.href = newUrl;
    }

    /**
     * Initialize related items navigation
     *
     * @return {void}
     */
    function initRelatedItemsNavigation() {
        $(document).on('click', '.saw-related-item-link', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const $link = $(this);
            const entity = $link.data('entity');
            const id = $link.data('id');

            if (!entity || !id) {
                console.error('❌ Related item missing entity or id');
                return;
            }

            console.log('🔗 Related item clicked:', { entity, id });

            // Use global sidebar AJAX function
            if (typeof window.openSidebarAjax === 'function') {
                window.openSidebarAjax(id, 'detail', entity);
            } else {
                console.error('❌ openSidebarAjax not available');
                // Fallback to full page load
                const baseUrl = window.location.origin;
                window.location.href = baseUrl + '/admin/settings/' + entity + '/' + id + '/';
            }
        });
    }

    /**
     * Initialize keyboard navigation
     *
     * @return {void}
     */
    function initKeyboardNavigation() {
        $(document).on('keydown', function (e) {
            if (!$('.saw-sidebar').length) {
                return;
            }

            if ($(e.target).is('input, textarea, select')) {
                return;
            }

            if (e.ctrlKey && e.key === 'ArrowUp') {
                e.preventDefault();
                saw_navigate_sidebar('prev');
            }

            if (e.ctrlKey && e.key === 'ArrowDown') {
                e.preventDefault();
                saw_navigate_sidebar('next');
            }

            if (e.key === 'Escape') {
                e.preventDefault();
                handleSidebarClose();
            }
        });
    }

    /**
     * Initialize prev/next button handlers
     *
     * @return {void}
     */
    function initNavigationButtons() {
        $(document).on('click', '.saw-sidebar-prev', function (e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('⬅️ Prev button clicked');
            saw_navigate_sidebar('prev');
        });

        $(document).on('click', '.saw-sidebar-next', function (e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('➡️ Next button clicked');
            saw_navigate_sidebar('next');
        });
    }

    /**
     * Initialize delete button in sidebar
     * 
     * ✅ HOTFIX v5.0.2:
     * - Closes sidebar BEFORE reload to prevent "not found" error
     * - Only ONE delete handler (removed from saw-app.js)
     * - No duplicate confirm dialogs
     *
     * @return {void}
     */
    function initDeleteButton() {
        // CRITICAL: Only handle .saw-delete-btn that are INSIDE .saw-sidebar
        // This prevents conflicts with other delete buttons
        $(document).on('click', '.saw-sidebar .saw-delete-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const $btn = $(this);
            const id = $btn.data('id');
            const entity = $btn.data('entity');
            const name = $btn.data('name') || '#' + id;

            if (!id || !entity) {
                console.error('❌ Delete button missing id or entity', { id, entity });
                alert('Chyba: Neplatná konfigurace tlačítka');
                return;
            }

            // CRITICAL: Get nonce using centralized function
            const nonce = getNonce();
            if (!nonce) {
                console.error('❌ Cannot delete - no nonce available');
                alert('Chyba bezpečnosti: Nonce není k dispozici. Obnovte stránku.');
                return;
            }

            const confirmMsg = 'Opravdu chcete smazat "' + name + '"?';
            if (!confirm(confirmMsg)) {
                return;
            }

            console.log('🗑️ Deleting:', { id, entity, name, nonce: nonce.substring(0, 10) + '...' });

            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update-alt saw-spin"></span>');

            $.ajax({
                url: getAjaxUrl(),
                method: 'POST',
                data: {
                    action: 'saw_delete_' + entity,
                    nonce: nonce,
                    id: id
                },
                success: function (response) {
                    console.log('✅ Delete response:', response);

                    if (response.success) {
                        // ✅ HOTFIX: Close sidebar immediately
                        const $wrapper = $('.saw-sidebar-wrapper');
                        $wrapper.removeClass('active');
                        $('.saw-admin-table-split').removeClass('has-sidebar');

                        // Show success message
                        if (typeof sawShowToast === 'function') {
                            sawShowToast(response.data.message || 'Úspěšně smazáno', 'success');
                        }

                        // ✅ CRITICAL FIX: Redirect to LIST URL, not reload
                        // This prevents "Zákazník nenalezen" error because:
                        // - reload() keeps current URL with deleted ID
                        // - controller tries to load deleted customer
                        // - shows "not found" error
                        setTimeout(function () {
                            // Build list URL from current path
                            const pathParts = window.location.pathname.split('/').filter(function (p) { return p; });

                            // Remove ID from path (last numeric segment)
                            for (let i = pathParts.length - 1; i >= 0; i--) {
                                if (!isNaN(pathParts[i]) && parseInt(pathParts[i]) > 0) {
                                    pathParts.splice(i, 1);
                                    break;
                                }
                            }

                            const listUrl = '/' + pathParts.join('/') + '/';
                            console.log('🔄 Redirecting to list:', listUrl);
                            window.location.href = listUrl;
                        }, 500);
                    } else {
                        console.error('❌ Delete failed:', response.data);
                        const errorMsg = response.data && response.data.message
                            ? response.data.message
                            : 'Neznámá chyba';
                        alert('Chyba při mazání: ' + errorMsg);
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('❌ AJAX error:', {
                        status: status,
                        error: error,
                        xhr: xhr,
                        responseText: xhr.responseText,
                        statusCode: xhr.status
                    });

                    let errorMsg = 'Chyba spojení se serverem';

                    if (xhr.status === 403) {
                        errorMsg = 'Chyba oprávnění (403). Obnovte stránku a zkuste znovu.';
                    } else if (xhr.status === 500) {
                        errorMsg = 'Chyba serveru (500). Kontaktujte administrátora.';
                    } else if (xhr.responseText === '-1') {
                        errorMsg = 'Bezpečnostní token vypršel. Obnovte stránku.';
                    }

                    alert(errorMsg);
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        });
    }

    /**
     * Handle sidebar close button click
     * 
     * ✅ v5.1.0: In form mode, trigger Cancel button instead
     *
     * @return {void}
     */
    function initCloseButton() {
        $(document).on('click', '.saw-sidebar-close', function (e) {
            e.preventDefault();

            // ✅ Check if we're in a FORM (create/edit mode)
            const $sidebar = $('.saw-sidebar');
            const mode = $sidebar.attr('data-mode');

            // If in form mode, find Cancel button and click it
            if (mode === 'create' || mode === 'edit') {
                const $cancelBtn = $('.saw-form-cancel-btn');
                if ($cancelBtn.length) {
                    console.log('✅ X button -> triggering Cancel button');
                    $cancelBtn.trigger('click');
                    return;
                }
            }

            // Otherwise use normal close logic
            handleSidebarClose();
        });
    }

    /**
     * Handle cancel button in forms
     * 
     * ✅ v5.2.0: Prevent default to avoid page reload flash
     *
     * @return {void}
     */
    function initCancelButton() {
        $(document).on('click', '.saw-form-cancel-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            console.log('❌ Cancel button clicked');

            const $sidebar = $('.saw-sidebar');
            const mode = $sidebar.attr('data-mode');
            const entity = $sidebar.attr('data-entity');
            const currentId = $sidebar.data('current-id');

            // ✅ EDIT MODE -> Load DETAIL via AJAX (smooth, no reload)
            if (mode === 'edit' && currentId && entity) {
                console.log('📝 Edit -> Detail: Loading via AJAX');

                if (typeof window.openSidebarAjax === 'function') {
                    window.openSidebarAjax(currentId, 'detail', entity);
                } else {
                    console.error('❌ openSidebarAjax not available');
                    // Fallback: smooth close
                    $sidebar.fadeOut(300, function () {
                        $(this).remove();
                    });

                    // Update URL
                    const currentUrl = window.location.pathname;
                    const pathParts = currentUrl.split('/').filter(function (p) { return p; });
                    if (pathParts[pathParts.length - 1] === 'edit') {
                        pathParts.pop();
                        window.history.pushState({}, '', '/' + pathParts.join('/') + '/');
                    }
                }
                return;
            }

            // ✅ CREATE MODE -> Close sidebar smoothly (no reload)
            if (mode === 'create') {
                console.log('➕ Create -> List: Closing smoothly');

                $sidebar.fadeOut(300, function () {
                    $(this).remove();
                });

                // Update URL
                const currentUrl = window.location.pathname;
                const pathParts = currentUrl.split('/').filter(function (p) { return p; });
                if (pathParts[pathParts.length - 1] === 'create') {
                    pathParts.pop();
                    window.history.pushState({}, '', '/' + pathParts.join('/') + '/');
                }

                $(document).trigger('sidebar:closed');
                return;
            }

            // FALLBACK: Generic close
            handleSidebarClose();
        });
    }

    /**
     * Handle sidebar close logic
     * UNIVERSAL LOGIC:
     * - If in EDIT mode -> go to DETAIL (via AJAX)
     * - If in DETAIL mode -> go to LIST (close sidebar)
     * - If in CREATE mode -> go to LIST (close sidebar)
     *
     * @return {void}
     */
    function handleSidebarClose() {
        const $sidebar = $('.saw-sidebar');

        if (!$sidebar.length) {
            return;
        }

        const mode = $sidebar.attr('data-mode');
        const entity = $sidebar.attr('data-entity');
        const currentId = $sidebar.data('current-id');

        const currentUrl = window.location.pathname;
        const pathParts = currentUrl.split('/').filter(function (p) { return p; });

        // EDIT MODE -> Go to DETAIL (via AJAX, no reload!)
        if (pathParts[pathParts.length - 1] === 'edit' && currentId && entity) {
            console.log('📝 Edit -> Detail: Using AJAX');
            if (typeof window.openSidebarAjax === 'function') {
                window.openSidebarAjax(currentId, 'detail', entity);
            } else {
                // Fallback
                pathParts.pop();
                window.location.href = '/' + pathParts.join('/') + '/';
            }
            return;
        }

        // CREATE MODE -> Go to LIST (close sidebar smoothly)
        if (pathParts[pathParts.length - 1] === 'create') {
            console.log('➕ Create -> List: Closing sidebar');
            if (typeof window.closeSidebar === 'function') {
                pathParts.pop();
                const listUrl = '/' + pathParts.join('/') + '/';
                window.closeSidebar(listUrl);
            } else {
                pathParts.pop();
                window.location.href = '/' + pathParts.join('/') + '/';
            }
            return;
        }

        // DETAIL MODE -> Go to LIST (close sidebar smoothly)
        if (currentId && !isNaN(pathParts[pathParts.length - 1])) {
            console.log('📋 Detail -> List: Closing sidebar');
            if (typeof window.closeSidebar === 'function') {
                pathParts.pop();
                const listUrl = '/' + pathParts.join('/') + '/';
                window.closeSidebar(listUrl);
            } else {
                pathParts.pop();
                window.location.href = '/' + pathParts.join('/') + '/';
            }
            return;
        }

        // FALLBACK: Use close button href or closeSidebar
        const closeUrl = $('.saw-sidebar-close').attr('href');
        if (closeUrl && closeUrl !== '#') {
            if (typeof window.closeSidebar === 'function') {
                window.closeSidebar(closeUrl);
            } else {
                window.location.href = closeUrl;
            }
        }
    }

    /**
     * Initialize sidebar on DOM ready
     *
     * @return {void}
     */
    function initSidebar() {
        const $sidebar = $('.saw-sidebar');

        if (!$sidebar.length) {
            return;
        }

        let currentId = parseInt($sidebar.data('current-id') || 0);

        if (!currentId) {
            const path = window.location.pathname.split('/').filter(function (p) { return p; });

            for (let i = path.length - 1; i >= 0; i--) {
                if (!isNaN(path[i]) && parseInt(path[i]) > 0) {
                    currentId = parseInt(path[i]);
                    $sidebar.data('current-id', currentId);
                    break;
                }
            }
        }

        console.log('🎯 Sidebar initialized with ID:', currentId);
    }

    /**
     * Debug: Log available nonces on init
     *
     * @return {void}
     */
    function debugNonceAvailability() {
        console.log('🔐 Nonce Debug:', {
            sawGlobal: window.sawGlobal ? {
                nonce: window.sawGlobal.nonce ? '✅ Available' : '❌ Missing',
                deleteNonce: window.sawGlobal.deleteNonce ? '✅ Available' : '❌ Missing',
                ajaxurl: window.sawGlobal.ajaxurl || '❌ Missing'
            } : '❌ sawGlobal not found',
            sawAjaxNonce: window.sawAjaxNonce ? '✅ Available' : '❌ Missing',
            selectedNonce: getNonce() ? '✅ Using: ' + getNonce().substring(0, 10) + '...' : '❌ NONE'
        });
    }

    /**
     * Initialize on DOM ready
     */
    $(document).ready(function () {
        console.log('🎨 Sidebar JS initialized v5.0.2 HOTFIX');
        debugNonceAvailability();
        initSidebar();
        initKeyboardNavigation();
        initNavigationButtons();
        initCloseButton();
        initCancelButton();
        initDeleteButton(); // ✅ HOTFIX: Improved delete with sidebar close before reload
        initRelatedItemsNavigation();
    });

})(jQuery);