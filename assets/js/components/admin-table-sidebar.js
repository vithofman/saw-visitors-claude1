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
        // Use capture phase to run BEFORE admin-table.js handler
        $(document).on('click', '.saw-sidebar-close', function (e) {
            // ✅ Check if we're in a FORM (create/edit mode) FIRST
            const $sidebar = $('.saw-sidebar');
            const mode = $sidebar.attr('data-mode');
            
            // Only handle form modes here - let admin-table.js handle detail mode
            if (mode !== 'create' && mode !== 'edit') {
                return; // Let other handlers process
            }
            
            // For form modes, we handle it completely
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation(); // CRITICAL: Stop admin-table.js handler!

            const entity = $sidebar.attr('data-entity');
            const currentId = $sidebar.data('current-id');

            // ✅ EDIT MODE -> Load DETAIL directly via AJAX (keep sidebar open, just change content)
            if (mode === 'edit' && currentId && entity) {
                console.log('📝 X button in edit -> Loading detail via AJAX (wrapper stays open)');
                
                if (typeof window.openSidebarAjax === 'function') {
                    // Load detail directly without closing sidebar wrapper
                    // Wrapper should already be open, we just change content
                    window.openSidebarAjax(currentId, 'detail', entity);
                } else {
                    console.error('❌ openSidebarAjax not available');
                    // Fallback: trigger cancel button
                    const $cancelBtn = $('.saw-form-cancel-btn');
                    if ($cancelBtn.length) {
                        $cancelBtn.trigger('click');
                    }
                }
                return false;
            }

            // ✅ CREATE MODE -> Close sidebar and go to list
            if (mode === 'create') {
                console.log('➕ X button in create -> Closing sidebar');
                
                if (typeof window.closeSidebar === 'function') {
                    const currentUrl = window.location.pathname;
                    const pathParts = currentUrl.split('/').filter(function (p) { return p; });
                    if (pathParts[pathParts.length - 1] === 'create') {
                        pathParts.pop();
                        const listUrl = '/' + pathParts.join('/') + '/';
                        window.closeSidebar(listUrl);
                    } else {
                        window.closeSidebar('#');
                    }
                } else {
                    // Fallback: trigger cancel button
                    const $cancelBtn = $('.saw-form-cancel-btn');
                    if ($cancelBtn.length) {
                        $cancelBtn.trigger('click');
                    }
                }
                return;
            }

            // Otherwise use normal close logic (for detail mode)
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
            // Keep sidebar wrapper open, just change content
            if (mode === 'edit' && currentId && entity) {
                console.log('📝 Cancel button -> Loading detail via AJAX (keeping sidebar open)');

                if (typeof window.openSidebarAjax === 'function') {
                    // Load detail directly - sidebar wrapper stays open
                    window.openSidebarAjax(currentId, 'detail', entity);
                } else {
                    console.error('❌ openSidebarAjax not available');
                    // Fallback: use closeSidebar function
                    if (typeof window.closeSidebar === 'function') {
                        const currentUrl = window.location.pathname;
                        const pathParts = currentUrl.split('/').filter(function (p) { return p; });
                        if (pathParts[pathParts.length - 1] === 'edit') {
                            pathParts.pop();
                            const detailUrl = '/' + pathParts.join('/') + '/';
                            window.closeSidebar(detailUrl);
                        }
                    }
                }
                return;
            }

            // ✅ CREATE MODE -> Close sidebar smoothly (no reload)
            if (mode === 'create') {
                console.log('➕ Create -> List: Closing smoothly');

                // Use closeSidebar function to properly close wrapper
                if (typeof window.closeSidebar === 'function') {
                    const currentUrl = window.location.pathname;
                    const pathParts = currentUrl.split('/').filter(function (p) { return p; });
                    if (pathParts[pathParts.length - 1] === 'create') {
                        pathParts.pop();
                        const listUrl = '/' + pathParts.join('/') + '/';
                        window.closeSidebar(listUrl);
                    }
                } else {
                    // Fallback
                    const $wrapper = $('.saw-sidebar-wrapper');
                    $wrapper.removeClass('active');
                    $('.saw-admin-table-split').removeClass('has-sidebar');
                    setTimeout(function () {
                        $wrapper.html('');
                    }, 300);
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

        // EDIT MODE -> Go to DETAIL (keep sidebar open, just reload content)
        if (pathParts[pathParts.length - 1] === 'edit' && currentId && entity) {
            console.log('📝 Edit -> Detail: Keeping sidebar open, switching content');
            if (typeof window.openSidebarAjax === 'function') {
                // Don't close - just load detail into existing sidebar
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
        // CRITICAL: initCloseButton must run BEFORE admin-table.js handler
        // Use capture phase to ensure it runs first
        initCloseButton();
        initCancelButton();
        initDeleteButton(); // ✅ HOTFIX: Improved delete with sidebar close before reload
        initRelatedItemsNavigation();
        
        // ✅ NEW: Universal detail sidebar handlers (moved from detail-sidebar.php)
        initDetailSidebarHandlers();
    });
    
    /**
     * Initialize universal detail sidebar handlers
     * 
     * Handles edit button, related item links, and collapsible sections.
     * This replaces module-specific JS in detail-sidebar.php.
     * 
     * @since 7.0.0
     * @return {void}
     */
    function initDetailSidebarHandlers() {
        // Initialize collapsible related sections
        $(document).on('click', '[data-toggle-section]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $header = $(this);
            const $section = $header.closest('.saw-related-section');
            
            $section.toggleClass('is-collapsed');
            
            console.log('🔽 Section toggled:', $section.data('section'));
        });
        
        // Handle Edit button - USE AJAX NAVIGATION
        $(document).off('click', '.saw-edit-ajax');
        
        $(document).on('click', '.saw-edit-ajax', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $btn = $(this);
            const entity = $btn.data('entity');
            const id = $btn.data('id');
            
            console.warn('🔴 EDIT BUTTON CLICKED!', {entity, id});
            console.warn('🔴 Event prevented and stopped');
            
            if (entity && id && typeof window.openSidebarAjax === 'function') {
                console.log('✅ Using AJAX navigation to edit mode');
                window.openSidebarAjax(id, 'edit', entity);
            } else {
                // Fallback to full page reload
                const href = $btn.attr('href');
                if (href) {
                    console.log('⚠️ Fallback to full page reload');
                    window.location.href = href;
                }
            }
            
            return false;
        });
        
        // Handle related item links - USE AJAX NAVIGATION
        $(document).off('click', '.saw-related-item-link');
        
        $(document).on('click', '.saw-related-item-link', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            const $link = $(this);
            const entity = $link.data('entity');
            const id = $link.data('id');
            
            console.log('🔗 Related link clicked:', {entity, id});
            
            if (entity && id && typeof window.openSidebarAjax === 'function') {
                console.log('✅ Using AJAX navigation');
                window.openSidebarAjax(id, 'detail', entity);
            } else {
                // Fallback to full page reload
                const href = $link.attr('href');
                if (href && href !== '#') {
                    console.log('⚠️ Fallback to full page reload');
                    setTimeout(function() {
                        window.location.href = href;
                    }, 10);
                }
            }
            
            return false;
        });
        
        console.log('✅ Universal detail sidebar handlers initialized');
    }
    
    // Re-initialize handlers after AJAX load
    $(document).on('saw-sidebar-loaded', function() {
        console.log('🔄 Re-initializing detail sidebar handlers after AJAX load');
        initDetailSidebarHandlers();
    });
    
    // CRITICAL: Register close button handler in CAPTURE PHASE to run BEFORE admin-table.js
    // This ensures we can prevent closeSidebar() from being called when switching from edit to detail
    document.addEventListener('click', function(e) {
        // Check if clicked element is close button or inside it
        const $target = $(e.target);
        const $closeBtn = $target.closest('.saw-sidebar-close');
        
        if ($closeBtn.length) {
            const $sidebar = $('.saw-sidebar');
            const mode = $sidebar.attr('data-mode');
            
            // Only handle form modes (create/edit) in capture phase
            // Let admin-table.js handle detail mode
            if (mode === 'create' || mode === 'edit') {
                const entity = $sidebar.attr('data-entity');
                const currentId = $sidebar.data('current-id');
                
                // EDIT MODE -> Load detail without closing wrapper
                if (mode === 'edit' && currentId && entity) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation(); // CRITICAL: Stop all other handlers!
                    
                    console.log('📝 X button (CAPTURE PHASE) -> Loading detail via AJAX (preventing closeSidebar)');
                    
                    // Use setTimeout to ensure this runs after current event loop
                    setTimeout(function() {
                        if (typeof window.openSidebarAjax === 'function') {
                            window.openSidebarAjax(currentId, 'detail', entity);
                        }
                    }, 0);
                    
                    return false;
                }
                
                // CREATE MODE -> Close sidebar properly
                if (mode === 'create') {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    
                    console.log('➕ X button (CAPTURE PHASE) -> Closing sidebar');
                    
                    setTimeout(function() {
                        if (typeof window.closeSidebar === 'function') {
                            const currentUrl = window.location.pathname;
                            const pathParts = currentUrl.split('/').filter(function (p) { return p; });
                            if (pathParts[pathParts.length - 1] === 'create') {
                                pathParts.pop();
                                const listUrl = '/' + pathParts.join('/') + '/';
                                window.closeSidebar(listUrl);
                            } else {
                                window.closeSidebar('#');
                            }
                        }
                    }, 0);
                    
                    return false;
                }
            }
        }
    }, true); // TRUE = CAPTURE PHASE (runs before bubble phase handlers)

})(jQuery);