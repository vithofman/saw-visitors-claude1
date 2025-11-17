/**
 * Dashboard Widget - Current Visitors JS
 * 
 * Handles manual checkout functionality
 */

jQuery(document).ready(function($) {
    
    /**
     * Manual checkout button click
     */
    $(document).on('click', '.saw-manual-checkout-btn', function(e) {
        e.preventDefault();
        
        const $btn = $(this);
        const $card = $btn.closest('.saw-visitor-card');
        const visitorId = $btn.data('visitor-id');
        
        // Prompt for reason
        const reason = prompt('Důvod ručního odhlášení (volitelné):');
        
        if (reason === null) {
            return; // User cancelled
        }
        
        // Add loading state
        $card.addClass('saw-loading');
        $btn.prop('disabled', true);
        
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
                        } else {
                            // Update counter
                            const newCount = $list.children().length;
                            $('.saw-visitor-count').text(newCount);
                            
                            let label = 'osob';
                            if (newCount === 1) label = 'osoba';
                            else if (newCount < 5) label = 'osoby';
                            $('.saw-visitor-label').text(label + ' uvnitř');
                        }
                    });
                    
                    // Show success message
                    showNotice('success', 'Návštěvník odhlášen');
                } else {
                    $card.removeClass('saw-loading');
                    $btn.prop('disabled', false);
                    showNotice('error', response.data.message || 'Chyba při odhlašování');
                }
            },
            error: function() {
                $card.removeClass('saw-loading');
                $btn.prop('disabled', false);
                showNotice('error', 'Chyba připojení k serveru');
            }
        });
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