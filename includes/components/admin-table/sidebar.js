/**
 * Sidebar Navigation
 *
 * Handles keyboard navigation and prev/next controls for sidebar.
 *
 * @package     SAW_Visitors
 * @subpackage  Components/AdminTable
 * @version     1.1.0
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
        if (!$sidebar.length) {
            console.log('No sidebar found');
            return;
        }
        
        const currentId = parseInt($sidebar.data('current-id') || 0);
        
        // CRITICAL: Get fresh list every time
        const $rows = $('.saw-table-panel tbody tr[data-id]').toArray();
        
        console.log('Navigate', direction, '- Current ID:', currentId, '- Rows:', $rows.length);
        
        if (!$rows.length) {
            console.log('No rows found');
            return;
        }
        
        if (!currentId) {
            console.log('No current ID set');
            return;
        }
        
        // Build array of IDs in table order
        const rowIds = $rows.map(row => parseInt($(row).data('id')));
        const currentIndex = rowIds.indexOf(currentId);
        
        console.log('Row IDs:', rowIds);
        console.log('Current index:', currentIndex);
        
        if (currentIndex === -1) {
            console.log('Current ID not found in table');
            return;
        }
        
        let targetId = null;
        
        if (direction === 'prev' && currentIndex > 0) {
            targetId = rowIds[currentIndex - 1];
        } else if (direction === 'next' && currentIndex < rowIds.length - 1) {
            targetId = rowIds[currentIndex + 1];
        }
        
        if (targetId) {
            console.log('Navigating to ID:', targetId);
            
            // Build new URL
            const url = new URL(window.location);
            const path = url.pathname.split('/').filter(p => p);
            
            // Find and replace ID in path
            for (let i = path.length - 1; i >= 0; i--) {
                if (!isNaN(path[i]) && parseInt(path[i]) > 0) {
                    path[i] = targetId;
                    break;
                }
            }
            
            const newUrl = '/' + path.join('/') + '/';
            console.log('New URL:', newUrl);
            window.location.href = newUrl;
        } else {
            console.log('Already at', direction === 'prev' ? 'first' : 'last', 'item');
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
            // Get current ID from data attribute (set in PHP)
            let currentId = parseInt($sidebar.data('current-id') || 0);
            
            // If not set, try to extract from URL
            if (!currentId) {
                const path = window.location.pathname.split('/').filter(p => p);
                for (let i = path.length - 1; i >= 0; i--) {
                    if (!isNaN(path[i]) && parseInt(path[i]) > 0) {
                        currentId = parseInt(path[i]);
                        $sidebar.data('current-id', currentId);
                        break;
                    }
                }
            }
            
            console.log('Sidebar initialized with ID:', currentId);
        }
    });
    
})(jQuery);