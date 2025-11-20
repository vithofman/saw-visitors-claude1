/**
 * SAW Frontend Dashboard JS
 */

jQuery(document).ready(function($) {
    
    /**
     * Manual checkout handler
     */
    $(document).on('click', '.saw-checkout-btn', function(e) {
        e.preventDefault();
        
        const $btn = $(this);
        const $card = $btn.closest('.saw-visitor-card');
        const visitorId = $btn.data('visitor-id');
        
        console.log('Checkout button clicked, visitor ID:', visitorId); // ✅ DEBUG
        
        // Confirm action
        if (!confirm('Opravdu chcete odhlásit tohoto návštěvníka?')) {
            return;
        }
        
        // Prompt for reason
        const reason = prompt('Důvod ručního odhlášení (volitelné):') || 'Ruční odhlášení';
        
        // Disable button
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Odhlašuji...');
        
        console.log('Sending AJAX request...'); // ✅ DEBUG
        
        // AJAX checkout
        $.ajax({
            url: sawDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'saw_checkout',
                nonce: sawDashboard.nonce,
                visitor_id: visitorId,
                log_date: new Date().toISOString().split('T')[0], // ✅ OPRAVENO - JS formát
                manual: 1,
                reason: reason
            },
            success: function(response) {
                console.log('AJAX response:', response); // ✅ DEBUG
                
                if (response.success) {
                    // Remove card with animation
                    $card.fadeOut(300, function() {
                        $(this).remove();
                        
                        // Check if list is empty
                        if ($('.saw-visitor-card').length === 0) {
                            location.reload();
                        } else {
                            // Update count
                            updateVisitorCount();
                        }
                    });
                    
                    showNotice('success', 'Návštěvník úspěšně odhlášen');
                } else {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-exit"></span> Check-out');
                    showNotice('error', response.data?.message || 'Chyba při odhlašování');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', xhr, status, error); // ✅ DEBUG
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-exit"></span> Check-out');
                showNotice('error', 'Chyba připojení');
            }
        });
    });
    
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