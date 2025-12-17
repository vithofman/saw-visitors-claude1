/**
 * SAW Frontend Dashboard JS
 * 
 * @version 8.0.0
 * 
 * CHANGELOG:
 * 8.0.0 - Modern redesign, compact hero, blue background
 */

jQuery(document).ready(function($) {
    
    // Notifications
    function showNotice(type, message) {
        var colors = {
            success: '#10b981',
            error: '#ef4444',
            warning: '#f59e0b',
            info: '#005A8C'
        };
        
        var $notice = $('<div class="saw-notice">')
            .text(message)
            .css({
                position: 'fixed',
                top: '24px',
                right: '24px',
                padding: '14px 24px',
                background: colors[type] || colors.info,
                color: 'white',
                borderRadius: '10px',
                fontWeight: '600',
                fontSize: '14px',
                zIndex: 999999,
                boxShadow: '0 8px 32px rgba(0, 0, 0, 0.2)',
                animation: 'slideInRight 0.3s ease'
            });
        
        $('body').append($notice);
        
        setTimeout(function() {
            $notice.fadeOut(300, function() { $(this).remove(); });
        }, 4000);
    }
    
    window.sawShowNotice = showNotice;
    
    // Auto-refresh (dashboard only)
    var currentPath = window.location.pathname.replace(/\/+$/, '');
    if (currentPath === '/admin' || currentPath === '/admin/dashboard') {
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 60000);
    }
    
    // Refresh button animation
    $('.saw-refresh').on('click', function() {
        $(this).addClass('spin');
    });
});

// Inject animations
var style = document.createElement('style');
style.textContent = '@keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}.spin{animation:spin 0.6s ease-in-out}@keyframes slideInRight{from{transform:translateX(120%);opacity:0}to{transform:translateX(0);opacity:1}}';
document.head.appendChild(style);