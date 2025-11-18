/**
 * Dashboard Widget - Current Visitors JS
 * 
 * Handles manual checkout functionality
 */
jQuery(document).ready(function($) {
    
    /**
     * ✅ KRITICKÉ: Prevent card click when clicking actions area
     */
    $(document).on('click', '.saw-visitor-actions', function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();  // ✅ Zastaví i další handlery
        return false;  // ✅ Extra pojistka
    });
    
    /**
     * Manual checkout button click
     */
    $(document).on('click', '.saw-manual-checkout-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();  // ✅ Zastaví i další handlery
        
        const $btn = $(this);
        const $card = $btn.closest('.saw-visitor-card');
        const visitorId = $btn.data('visitor-id');
        
        // Confirm action
        if (!confirm('Opravdu chcete odhlásit tohoto návštěvníka?')) {
            return false;
        }
        
        // Prompt for reason (optional)
        const reason = prompt('Důvod ručního odhlášení (volitelné):');
        
        if (reason === null) {
            return false; // User cancelled
        }
        
        // Add loading state
        $card.addClass('saw-loading');
        $btn.prop('disabled', true).text('Odhlašuji...');
        
        // AJAX request
        $.ajax({
            url: sawCurrentVisitors.ajaxurl,
            type: 'POST',
            data: {
                action: 'saw_manual_checkout_visitor',
                nonce: sawCurrentVisitors.nonce,
                visitor_id: visitorId,
                reason: reason || 'Ruční odhlášení administrátorem'
            },
            success: function(response) {
                if (response.success) {
                    // Animate card removal
                    $card.slideUp(300, function() {
                        $(this).remove();
                        
                        // Check if list is empty
                        const $list = $('.saw-visitors-list');
                        if ($list.children().length === 0) {
                            // Reload widget to show empty state
                            location.reload();
                        }
                    });
                    
                    // Show success message
                    showNotice('success', 'Návštěvník odhlášen');
                } else {
                    $card.removeClass('saw-loading');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-exit"></span> Check-out');
                    showNotice('error', response.data.message || 'Chyba při odhlašování');
                }
            },
            error: function() {
                $card.removeClass('saw-loading');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-exit"></span> Check-out');
                showNotice('error', 'Chyba připojení k serveru');
            }
        });
        
        return false;  // ✅ Extra pojistka
    });
    
    /**
     * Show notice message
     */
    function showNotice(type, message) {
        const $notice = $('<div class="saw-notice saw-notice-' + type + '">' + message + '</div>');
        $notice.css({
            position: 'fixed',
            top: '32px',
            right: '20px',
            padding: '12px 20px',
            background: type === 'success' ? '#10b981' : '#ef4444',
            color: 'white',
            borderRadius: '6px',
            fontWeight: '600',
            zIndex: 999999,
            boxShadow: '0 4px 12px rgba(0,0,0,0.15)'
        });
        
        $('body').append($notice);
        
        setTimeout(function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    /**
     * Auto-refresh widget every 60 seconds
     */
    if ($('.saw-current-visitors-widget').length) {
        setInterval(function() {
            location.reload();
        }, 60000); // 60 seconds
    }
});