/**
 * SAW App JavaScript - VYČIŠTĚNÁ VERZE
 * 
 * Utility funkce pro aplikaci:
 * - Delete confirmations
 * - Toast notifications
 * - User menu dropdown
 * - Form validation helpers
 * - Loading state helpers
 * 
 * POZNÁMKA: Modal systém je v samostatném souboru saw-modal.js
 * POZNÁMKA: Starý SAW_Modal objekt byl odstraněn - používejte SAWModal
 * 
 * @package SAW_Visitors
 * @version 5.2.0
 * @since   4.6.1
 */

(function($) {
    'use strict';
    
    // ========================================
    // DELETE CONFIRMATION
    // ========================================
    
    $(document).on('click', '.saw-delete-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const id = $(this).data('id');
        const name = $(this).data('name');
        const entity = $(this).data('entity') || 'customers';
        
        if (!confirm('Opravdu chcete smazat "' + name + '"?')) {
            return;
        }
        
        const $btn = $(this);
        const originalText = $btn.text();
        $btn.prop('disabled', true).text('Mažu...');
        
        $.ajax({
            url: sawGlobal.ajaxurl,
            method: 'POST',
            data: {
                action: 'saw_delete_' + entity,
                nonce: sawGlobal.deleteNonce || sawGlobal.nonce,
                entity: entity,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    if (typeof sawShowToast === 'function') {
                        sawShowToast('Úspěšně smazáno', 'success');
                    }
                    
                    // Reload page after short delay
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                } else {
                    alert('Chyba: ' + (response.data?.message || 'Neznámá chyba'));
                    $btn.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.error('Delete error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                alert('Chyba při mazání');
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // ========================================
    // TOAST NOTIFICATIONS
    // ========================================
    
    /**
     * Show toast notification
     * 
     * @param {string} message - Message to display
     * @param {string} type - Type: success, danger, warning, info
     */
    window.sawShowToast = function(message, type = 'success') {
        // Remove existing toasts
        $('.saw-toast').remove();
        
        const $toast = $('<div class="saw-toast saw-toast-' + type + '">' + sawEscapeHtml(message) + '</div>');
        $('body').append($toast);
        
        // Trigger reflow for animation
        $toast[0].offsetHeight;
        
        setTimeout(function() {
            $toast.addClass('saw-toast-show');
        }, 10);
        
        setTimeout(function() {
            $toast.removeClass('saw-toast-show');
            setTimeout(function() {
                $toast.remove();
            }, 300);
        }, 3000);
    };
    
    /**
     * Escape HTML to prevent XSS
     * 
     * @param {string} text - Text to escape
     * @return {string} Escaped text
     */
    window.sawEscapeHtml = function(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
    };
    
    // ========================================
    // USER MENU DROPDOWN
    // ========================================
    
    function initUserMenu() {
        const toggle = document.getElementById('sawUserMenuToggle');
        const dropdown = document.getElementById('sawUserDropdown');
        
        if (!toggle || !dropdown) {
            return;
        }
        
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('active');
        });
        
        document.addEventListener('click', function() {
            dropdown.classList.remove('active');
        });
        
        dropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
    
    // ========================================
    // FORM VALIDATION HELPERS
    // ========================================
    
    /**
     * Validate email format
     * 
     * @param {string} email - Email to validate
     * @return {boolean} Is valid
     */
    window.sawValidateEmail = function(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(String(email).toLowerCase());
    };
    
    /**
     * Validate phone format (Czech)
     * 
     * @param {string} phone - Phone to validate
     * @return {boolean} Is valid
     */
    window.sawValidatePhone = function(phone) {
        const re = /^(\+420)?[0-9]{9}$/;
        return re.test(String(phone).replace(/\s/g, ''));
    };
    
    // ========================================
    // LOADING STATE HELPERS
    // ========================================
    
    /**
     * Show loading state on button
     * 
     * @param {jQuery} $button - Button element
     * @param {string} text - Loading text (optional)
     */
    window.sawButtonLoading = function($button, text = 'Načítám...') {
        if (!$button.data('original-text')) {
            $button.data('original-text', $button.html());
        }
        $button.prop('disabled', true).html(
            '<span class="dashicons dashicons-update saw-spin"></span> ' + text
        );
    };
    
    /**
     * Reset button to original state
     * 
     * @param {jQuery} $button - Button element
     */
    window.sawButtonReset = function($button) {
        const originalText = $button.data('original-text');
        if (originalText) {
            $button.prop('disabled', false).html(originalText);
        }
    };
    
    // ========================================
    // CONFIRMATION DIALOGS
    // ========================================
    
    /**
     * Show confirmation dialog using native confirm
     * In future, this can be replaced with a custom modal
     * 
     * @param {string} message - Confirmation message
     * @param {function} callback - Callback if confirmed
     */
    window.sawConfirm = function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    };
    
    // ========================================
    // TABLE HELPERS
    // ========================================
    
    /**
     * Highlight table row on hover
     */
    function initTableRowHover() {
        $('.saw-admin-table tbody tr').hover(
            function() {
                $(this).addClass('saw-row-hover');
            },
            function() {
                $(this).removeClass('saw-row-hover');
            }
        );
    }
    
    // ========================================
    // INITIALIZE ON DOM READY
    // ========================================
    
    $(document).ready(function() {
        initUserMenu();
        initTableRowHover();
        
        // Add loaded class to body for animations
        document.body.classList.add('loaded');
        
        console.log('🚀 SAW App initialized', {
            sawGlobal: typeof sawGlobal !== 'undefined' ? sawGlobal : 'not defined',
            jQuery: !!$,
            newModalSystem: typeof SAWModal !== 'undefined',
            oldModalSystem: typeof SAW_Modal !== 'undefined' ? 'WARNING: Old SAW_Modal still present!' : 'removed'
        });
    });
    
})(jQuery);
