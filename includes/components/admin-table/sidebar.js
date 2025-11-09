/**
 * Sidebar Navigation
 *
 * Handles keyboard navigation and prev/next controls for sidebar.
 * Supports AJAX loading for seamless navigation.
 *
 * @package     SAW_Visitors
 * @subpackage  Components/AdminTable
 * @version     3.1.0 - FIXED: Edit mode detection with console logs
 * @since       4.0.0
 */

(function($) {
    'use strict';
    
    /**
     * Navigate to previous or next item in sidebar
     *
     * @param {string} direction Direction: 'prev' or 'next'
     * @return {void}
     */
    window.saw_navigate_sidebar = function(direction) {
        const $sidebar = $('.saw-sidebar');
        
        if (!$sidebar.length) {
            return;
        }
        
        const currentId = parseInt($sidebar.data('current-id') || 0);
        
        if (!currentId) {
            return;
        }
        
        const $rows = $('.saw-table-panel tbody tr[data-id]').toArray();
        
        if (!$rows.length) {
            return;
        }
        
        const rowIds = $rows.map(function(row) {
            return parseInt($(row).data('id'));
        });
        
        const currentIndex = rowIds.indexOf(currentId);
        
        if (currentIndex === -1) {
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
            const entity = extractEntityFromUrl();
            
            if (entity && typeof window.openSidebarAjax === 'function') {
                window.openSidebarAjax(targetId, mode, entity);
            } else {
                navigateWithUrl(targetId);
            }
        }
    };
    
    /**
     * Extract entity name from current URL
     *
     * @return {string|null}
     */
    function extractEntityFromUrl() {
        const path = window.location.pathname.split('/').filter(function(p) { return p; });
        
        for (let i = 0; i < path.length; i++) {
            if (path[i] === 'settings' && path[i + 1]) {
                return path[i + 1];
            }
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
        const path = url.pathname.split('/').filter(function(p) { return p; });
        
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
     * Initialize keyboard navigation
     *
     * @return {void}
     */
    function initKeyboardNavigation() {
        $(document).on('keydown', function(e) {
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
        $(document).on('click', '.saw-sidebar-prev', function(e) {
            e.preventDefault();
            saw_navigate_sidebar('prev');
        });
        
        $(document).on('click', '.saw-sidebar-next', function(e) {
            e.preventDefault();
            saw_navigate_sidebar('next');
        });
    }
    
    /**
     * Handle sidebar close button click
     *
     * @return {void}
     */
    function initCloseButton() {
        $(document).on('click', '.saw-sidebar-close', function(e) {
            e.preventDefault();
            console.log('🔴 CLOSE BUTTON CLICKED - calling handleSidebarClose()');
            handleSidebarClose();
        });
    }
    
    /**
     * Handle cancel button in forms
     *
     * @return {void}
     */
    function initCancelButton() {
        $(document).on('click', '.saw-form-cancel-btn', function(e) {
            e.preventDefault();
            console.log('🟡 CANCEL BUTTON CLICKED - calling handleSidebarClose()');
            handleSidebarClose();
        });
    }
    
    /**
     * Handle sidebar close logic
     * UNIVERSAL LOGIC:
     * - If in EDIT mode -> go to DETAIL
     * - If in DETAIL mode -> go to LIST
     * - If in CREATE mode -> go to LIST
     *
     * @return {void}
     */
    function handleSidebarClose() {
        console.log('🚪 handleSidebarClose() STARTED');
        
        const $sidebar = $('.saw-sidebar');
        
        if (!$sidebar.length) {
            console.log('⚠️ No sidebar found');
            return;
        }
        
        const mode = $sidebar.attr('data-mode');
        const entity = $sidebar.attr('data-entity');
        const currentId = $sidebar.data('current-id');
        
        console.log('📊 Sidebar data:', {
            mode: mode,
            entity: entity,
            currentId: currentId
        });
        
        const currentUrl = window.location.pathname;
        const pathParts = currentUrl.split('/').filter(function(p) { return p; });
        
        console.log('🔗 Current URL:', currentUrl);
        console.log('📂 Path parts:', pathParts);
        console.log('🔍 Last part:', pathParts[pathParts.length - 1]);
        
        // EDIT MODE -> Go to DETAIL
        if (mode === 'edit' && currentId) {
            console.log('✅ EDIT MODE DETECTED - Going to DETAIL');
            
            // Remove 'edit' from path
            if (pathParts[pathParts.length - 1] === 'edit') {
                pathParts.pop();
            }
            
            const detailUrl = '/' + pathParts.join('/') + '/';
            console.log('🎯 Redirecting to:', detailUrl);
            window.location.href = detailUrl;
            return;
        }
        
        // CREATE MODE -> Go to LIST
        if (mode === 'create') {
            console.log('✅ CREATE MODE DETECTED - Going to LIST');
            
            if (pathParts[pathParts.length - 1] === 'create') {
                pathParts.pop();
            }
            
            const listUrl = '/' + pathParts.join('/') + '/';
            console.log('🎯 Redirecting to:', listUrl);
            window.location.href = listUrl;
            return;
        }
        
        // DETAIL MODE -> Go to LIST
        if (mode === 'detail' && currentId) {
            console.log('✅ DETAIL MODE DETECTED - Going to LIST');
            
            // Remove ID from path
            if (!isNaN(pathParts[pathParts.length - 1])) {
                pathParts.pop();
            }
            
            const listUrl = '/' + pathParts.join('/') + '/';
            console.log('🎯 Redirecting to:', listUrl);
            window.location.href = listUrl;
            return;
        }
        
        // FALLBACK: Use close button href
        console.log('⚠️ FALLBACK - using close button href');
        const closeUrl = $('.saw-sidebar-close').attr('href');
        if (closeUrl && closeUrl !== '#') {
            console.log('🎯 Redirecting to href:', closeUrl);
            window.location.href = closeUrl;
        } else {
            console.log('❌ No valid close URL found');
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
            const path = window.location.pathname.split('/').filter(function(p) { return p; });
            
            for (let i = path.length - 1; i >= 0; i--) {
                if (!isNaN(path[i]) && parseInt(path[i]) > 0) {
                    currentId = parseInt(path[i]);
                    $sidebar.data('current-id', currentId);
                    break;
                }
            }
        }
        
        console.log('✅ Sidebar initialized with ID:', currentId);
    }
    
    /**
     * Initialize on DOM ready
     */
    $(document).ready(function() {
        initSidebar();
        initKeyboardNavigation();
        initNavigationButtons();
        initCloseButton();
        initCancelButton();
    });
    
})(jQuery);