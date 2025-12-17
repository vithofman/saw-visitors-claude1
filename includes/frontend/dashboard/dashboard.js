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
                top: (24 + ($('.saw-notice').length * 56)) + 'px',
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

    // Premium hover tilt (non-breaking, respects prefers-reduced-motion)
    (function initCardTilt(){
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduce) return;

        var $cards = $('.saw-card');
        if (!$cards.length) return;

        $cards.each(function(){
            var $card = $(this);
            $card.addClass('saw-tilt');

            $card.on('mousemove', function(e){
                var rect = this.getBoundingClientRect();
                var x = e.clientX - rect.left;
                var y = e.clientY - rect.top;
                var px = (x / rect.width) - 0.5;
                var py = (y / rect.height) - 0.5;

                // Gentle values; keep readable
                var ry = (px * 6).toFixed(2) + 'deg';
                var rx = (-py * 4).toFixed(2) + 'deg';

                this.style.setProperty('--ry', ry);
                this.style.setProperty('--rx', rx);
            });

            $card.on('mouseleave', function(){
                this.style.setProperty('--ry', '0deg');
                this.style.setProperty('--rx', '0deg');
            });
        });
    })();

});

// Inject animations
var style = document.createElement('style');
style.textContent = '@keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}.spin{animation:spin 0.6s ease-in-out}@keyframes slideInRight{from{transform:translateX(120%);opacity:0}to{transform:translateX(0);opacity:1}}';
document.head.appendChild(style);