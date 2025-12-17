/**
 * SAW Frontend Dashboard JS
 * 
 * @version 7.0.0
 * 
 * CHANGELOG:
 * 7.0.0 - Major redesign update
 *       - Improved notification system with branded colors
 *       - Enhanced auto-refresh with page visibility detection
 *       - Better checkout handler feedback
 * 5.6.2 - REMOVED checkout handler - now handled by inline script in dashboard.php
 *         to prevent duplicate AJAX requests
 * 5.6.1 - Added proper log_date format
 */

jQuery(document).ready(function($) {
    
    // ============================================
    // CHECKOUT HANDLER - REMOVED
    // ============================================
    // The checkout functionality is handled by inline script in dashboard.php
    // Having both handlers caused duplicate AJAX requests where the second one
    // failed because the visitor was already checked out by the first request.
    // 
    // DO NOT ADD CHECKOUT HANDLER HERE!
    // ============================================
    
    /**
     * Update visitor count badge in multiple locations
     */
    function updateVisitorCount() {
        const count = $('.saw-person').length;
        
        // Update hero stat
        $('#sawPresentCount').text(count);
        
        // Update card badge
        if (count > 0) {
            $('#sawPresentBadge').text(count).show();
        } else {
            $('#sawPresentBadge').hide();
        }
        
        // Update emergency button
        const $emCount = $('.saw-em-count');
        if ($emCount.length) {
            if (count > 0) {
                $emCount.text(count);
            } else {
                $('.saw-emergency').fadeOut(300);
            }
        }
        
        // Update LIVE badge
        if (count === 0) {
            $('#sawLiveBadge').fadeOut(200);
        }
    }
    
    /**
     * Show notification with brand styling
     * 
     * @param {string} type - 'success', 'error', 'warning', or 'info'
     * @param {string} message - Notification message
     */
    function showNotice(type, message) {
        // Color mapping using brand colors
        const colors = {
            success: { bg: '#059669', border: '#047857' },
            error: { bg: '#dc2626', border: '#b91c1c' },
            warning: { bg: '#d97706', border: '#b45309' },
            info: { bg: '#005A8C', border: '#004A73' }
        };
        
        const color = colors[type] || colors.info;
        
        const $notice = $('<div class="saw-notice">')
            .text(message)
            .css({
                position: 'fixed',
                top: '24px',
                right: '24px',
                padding: '16px 28px',
                background: color.bg,
                borderLeft: `4px solid ${color.border}`,
                color: 'white',
                borderRadius: '12px',
                fontWeight: '600',
                fontSize: '15px',
                zIndex: 999999,
                boxShadow: '0 8px 32px rgba(0, 0, 0, 0.2)',
                animation: 'slideInRight 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
                maxWidth: '400px',
                lineHeight: '1.4'
            });
        
        $('body').append($notice);
        
        // Auto-dismiss after 4 seconds
        setTimeout(function() {
            $notice.css({
                animation: 'slideOutRight 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards'
            });
            setTimeout(function() {
                $notice.remove();
            }, 300);
        }, 4000);
    }
    
    // Make showNotice available globally for inline script
    window.sawShowNotice = showNotice;
    window.sawUpdateVisitorCount = updateVisitorCount;
    
    /**
     * Auto-refresh with page visibility detection
     * Only refreshes when:
     * 1. User is on dashboard page
     * 2. Page is visible (not in background tab)
     * 3. 60 seconds have passed since last refresh
     */
    const currentPath = window.location.pathname.replace(/\/+$/, '');
    const isDashboardPage = currentPath === '/admin' || currentPath === '/admin/dashboard';
    
    if (isDashboardPage) {
        console.log('[Dashboard v7.0.0] Auto-refresh enabled');
        
        let lastRefresh = Date.now();
        let refreshInterval = null;
        
        function startAutoRefresh() {
            if (refreshInterval) return;
            
            refreshInterval = setInterval(function() {
                // Only refresh if page is visible
                if (document.visibilityState === 'visible') {
                    const timeSinceLastRefresh = Date.now() - lastRefresh;
                    
                    // Only refresh if more than 55 seconds have passed
                    if (timeSinceLastRefresh >= 55000) {
                        console.log('[Dashboard] Auto-refreshing...');
                        lastRefresh = Date.now();
                        location.reload();
                    }
                }
            }, 60000);
        }
        
        function stopAutoRefresh() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
                refreshInterval = null;
            }
        }
        
        // Start auto-refresh
        startAutoRefresh();
        
        // Handle visibility changes
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                // Check if we should refresh when coming back to tab
                const timeSinceLastRefresh = Date.now() - lastRefresh;
                if (timeSinceLastRefresh >= 60000) {
                    console.log('[Dashboard] Refreshing after returning to tab...');
                    location.reload();
                }
            }
        });
    } else {
        console.log('[Dashboard] Auto-refresh disabled - not on dashboard:', currentPath);
    }
    
    /**
     * Enhanced refresh button with loading state
     */
    $('.saw-refresh').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        
        // Add loading state
        $btn.addClass('spin').prop('disabled', true);
        
        // Small delay for visual feedback
        setTimeout(function() {
            location.reload();
        }, 200);
    });
    
    /**
     * Keyboard shortcuts
     */
    $(document).on('keydown', function(e) {
        // Ctrl/Cmd + R = Refresh (handled by browser, but we track it)
        // Ctrl/Cmd + N = New visit (if on dashboard)
        if ((e.ctrlKey || e.metaKey) && e.key === 'n' && isDashboardPage) {
            e.preventDefault();
            window.location.href = '/admin/visits/create';
        }
    });
    
    /**
     * Touch-friendly interactions for mobile
     */
    if ('ontouchstart' in window) {
        // Add touch feedback to cards
        $('.saw-card').on('touchstart', function() {
            $(this).addClass('touch-active');
        }).on('touchend touchcancel', function() {
            $(this).removeClass('touch-active');
        });
    }
});

/* ============================================
   CSS ANIMATIONS (injected)
   ============================================ */
const dashboardStyles = document.createElement('style');
dashboardStyles.textContent = `
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .spin { 
        animation: spin 0.6s ease-in-out; 
    }
    
    @keyframes slideInRight {
        from {
            transform: translateX(120%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(120%);
            opacity: 0;
        }
    }
    
    /* Touch feedback for mobile */
    .saw-card.touch-active {
        transform: scale(0.98);
        transition: transform 0.1s ease;
    }
    
    /* Loading state for refresh button */
    .saw-refresh.spin {
        pointer-events: none;
        color: var(--dash-primary, #005A8C);
    }
`;
document.head.appendChild(dashboardStyles);