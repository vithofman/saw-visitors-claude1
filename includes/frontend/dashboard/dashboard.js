/**
 * SAW Frontend Dashboard JS
 * 
 * @version 5.6.2
 * 
 * CHANGELOG:
 * 5.6.2 - REMOVED checkout handler - now handled by inline script in dashboard.php
 *         to prevent duplicate AJAX requests
 * 5.6.1 - Added proper log_date format
 */

jQuery(document).ready(function($) {
    
    // ============================================
    // CHECKOUT HANDLER - REMOVED in v5.6.2
    // ============================================
    // The checkout functionality is now handled by inline script in dashboard.php
    // Having both handlers caused duplicate AJAX requests where the second one
    // failed because the visitor was already checked out by the first request.
    // 
    // DO NOT ADD CHECKOUT HANDLER HERE!
    // ============================================
    
    /**
     * Update visitor count badge
     */
    function updateVisitorCount() {
        const count = $('.saw-visitor-card').length;
        
        let label = 'osob';
        if (count === 1) label = 'osoba';
        else if (count < 5) label = 'osoby';
        
        $('.saw-visitor-count-badge .saw-count').text(count);
        $('.saw-visitor-count-badge .saw-label').text(label);
    }
    
    /**
     * Show notification
     */
    function showNotice(type, message) {
        const bgColor = type === 'success' ? '#10b981' : '#ef4444';
        
        const $notice = $('<div class="saw-notice">')
            .text(message)
            .css({
                position: 'fixed',
                top: '20px',
                right: '20px',
                padding: '16px 24px',
                background: bgColor,
                color: 'white',
                borderRadius: '8px',
                fontWeight: '600',
                fontSize: '15px',
                zIndex: 999999,
                boxShadow: '0 4px 12px rgba(0,0,0,0.2)',
                animation: 'slideInRight 0.3s ease'
            });
        
        $('body').append($notice);
        
        setTimeout(function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    // Make showNotice available globally for inline script
    window.sawShowNotice = showNotice;
    
    /**
     * Auto-refresh every 60 seconds - ONLY on dashboard page
     * CRITICAL: Check exact path to prevent reload on other pages
     */
    const currentPath = window.location.pathname.replace(/\/+$/, ''); // Remove trailing slashes
    if (currentPath === '/admin' || currentPath === '/admin/dashboard') {
        console.log('[Dashboard] Auto-refresh enabled for dashboard only');
        setInterval(function() {
            // Double-check we're still on dashboard before reloading
            const checkPath = window.location.pathname.replace(/\/+$/, '');
            if (checkPath === '/admin' || checkPath === '/admin/dashboard') {
                console.log('[Dashboard] Auto-refreshing dashboard...');
                location.reload();
            }
        }, 60000);
    } else {
        console.log('[Dashboard] Auto-refresh disabled - not on dashboard:', currentPath);
    }
});

/* Spin animation for loading */
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    .spin { animation: spin 1s linear infinite; }
    
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
`;
document.head.appendChild(style);