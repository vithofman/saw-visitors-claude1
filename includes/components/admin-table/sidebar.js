/**
 * Sidebar Navigation
 *
 * Handles keyboard navigation and prev/next controls for sidebar.
 *
 * @package     SAW_Visitors
 * @subpackage  Components/AdminTable
 * @version     1.0.0
 * @since       4.0.0
 */

(function($) {
    'use strict';
    
    /**
     * Navigate to previous or next item in sidebar
     *
     * @param {string} direction 'prev' or 'next'
     */
    window.saw_navigate_sidebar = function(direction) {
        const $sidebar = $('.saw-sidebar');
        if (!$sidebar.length) return;
        
        const mode = $sidebar.data('mode');
        const currentId = parseInt($sidebar.data('current-id') || 0);
        const $rows = $('.saw-table-panel tbody tr[data-id]');
        
        if (!$rows.length) return;
        
        let targetRow = null;
        
        if (direction === 'prev') {
            for (let i = $rows.length - 1; i >= 0; i--) {
                const rowId = parseInt($($rows[i]).data('id'));
                if (currentId && rowId < currentId) {
                    targetRow = $rows[i];
                    break;
                }
            }
        } else if (direction === 'next') {
            for (let i = 0; i < $rows.length; i++) {
                const rowId = parseInt($($rows[i]).data('id'));
                if (currentId && rowId > currentId) {
                    targetRow = $rows[i];
                    break;
                }
            }
        }
        
        if (targetRow) {
            const targetId = $(targetRow).data('id');
            const url = new URL(window.location);
            const path = url.pathname.split('/').filter(p => p);
            
            // Find and replace the ID in path
            for (let i = path.length - 1; i >= 0; i--) {
                if (!isNaN(path[i])) {
                    path[i] = targetId;
                    break;
                }
            }
            
            window.location.href = '/' + path.join('/') + '/';
        }
    };
    
    /**
     * Keyboard navigation
     */
    $(document).on('keydown', function(e) {
        if (!$('.saw-sidebar').length) return;
        
        // Ctrl + Arrow Up - Previous
        if (e.ctrlKey && e.key === 'ArrowUp') {
            e.preventDefault();
            saw_navigate_sidebar('prev');
        }
        
        // Ctrl + Arrow Down - Next
        if (e.ctrlKey && e.key === 'ArrowDown') {
            e.preventDefault();
            saw_navigate_sidebar('next');
        }
        
        // Escape - Close sidebar
        if (e.key === 'Escape') {
            e.preventDefault();
            const closeUrl = $('.saw-sidebar-close').attr('href');
            if (closeUrl) window.location.href = closeUrl;
        }
    });
    
    /**
     * Initialize on DOM ready
     */
    $(document).ready(function() {
        const $sidebar = $('.saw-sidebar');
        if ($sidebar.length) {
            // Store current ID from URL for navigation
            const path = window.location.pathname.split('/').filter(p => p);
            for (let i = path.length - 1; i >= 0; i--) {
                if (!isNaN(path[i])) {
                    $sidebar.data('current-id', parseInt(path[i]));
                    break;
                }
            }
        }
    });
    
})(jQuery);