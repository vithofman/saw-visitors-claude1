/**
 * Sidebar Navigation
 *
 * Handles keyboard navigation and prev/next controls for sidebar.
 * Supports AJAX loading for seamless navigation.
 *
 * @package     SAW_Visitors
 * @subpackage  Components/AdminTable
 * @version     2.1.0 - Added close button handler
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
            handleSidebarClose();
        });
    }
    
    /**
     * Handle sidebar close logic
     * If in edit mode, go to detail
     * Otherwise go to list
     *
     * @return {void}
     */
    function handleSidebarClose() {
    const currentUrl = window.location.pathname;
    const pathParts = currentUrl.split('/').filter(function(p) { return p; });
    
    // Pokud URL končí na /edit - jdi na detail
    if (pathParts[pathParts.length - 1] === 'edit') {
        pathParts.pop(); // odstranit 'edit'
        window.location.href = '/' + pathParts.join('/') + '/';
    } 
    // Pokud URL obsahuje číselné ID (detail view) - jdi na list
    else if (pathParts.length > 0 && !isNaN(pathParts[pathParts.length - 1])) {
        pathParts.pop(); // odstranit ID
        window.location.href = '/' + pathParts.join('/') + '/';
    }
    // Jinak použij href z tlačítka
    else {
        const closeUrl = $('.saw-sidebar-close').attr('href');
        if (closeUrl) {
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
            const path = window.location.pathname.split('/').filter(function(p) { return p; });
            
            for (let i = path.length - 1; i >= 0; i--) {
                if (!isNaN(path[i]) && parseInt(path[i]) > 0) {
                    currentId = parseInt(path[i]);
                    $sidebar.data('current-id', currentId);
                    break;
                }
            }
        }
    }
    
    /**
     * Initialize on DOM ready
     */
    $(document).ready(function() {
        initSidebar();
        initKeyboardNavigation();
        initNavigationButtons();
        initCloseButton();
    });
    
})(jQuery);