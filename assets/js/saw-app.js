/**
 * SAW App JavaScript - VYČIŠTĚNÁ VERZE
 * 
 * @package SAW_Visitors
 * @version 5.4.0
 */

(function($) {
    'use strict';
    
    // ========================================
    // MOBILE MENU TOGGLE
    // ========================================
    
    function initMobileMenu() {
        $('#sawMobileMenuToggle').on('click', function() {
            $('#sawAppSidebar').toggleClass('open');
            $('#sawSidebarOverlay').toggleClass('active');
        });
        
        $('#sawSidebarClose, #sawSidebarOverlay').on('click', function() {
            $('#sawAppSidebar').removeClass('open');
            $('#sawSidebarOverlay').removeClass('active');
        });
    }
    
    // ========================================
    // SIDEBAR ACCORDION NAVIGATION
    // ========================================
    
    function initSidebarAccordion() {
        $(document).on('click', '.saw-nav-heading', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $section = $(this).closest('.saw-nav-section');
            $section.toggleClass('collapsed');
        });
    }
    
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
                    if (typeof sawShowToast === 'function') {
                        sawShowToast('Úspěšně smazáno', 'success');
                    }
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
    
    window.sawShowToast = function(message, type = 'success') {
        $('.saw-toast').remove();
        
        const $toast = $('<div class="saw-toast saw-toast-' + type + '">' + sawEscapeHtml(message) + '</div>');
        $('body').append($toast);
        
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
    
    window.sawValidateEmail = function(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(String(email).toLowerCase());
    };
    
    window.sawValidatePhone = function(phone) {
        const re = /^(\+420)?[0-9]{9}$/;
        return re.test(String(phone).replace(/\s/g, ''));
    };
    
    // ========================================
    // LOADING STATE HELPERS
    // ========================================
    
    window.sawButtonLoading = function($button, text = 'Načítám...') {
        if (!$button.data('original-text')) {
            $button.data('original-text', $button.html());
        }
        $button.prop('disabled', true).html(
            '<span class="dashicons dashicons-update saw-spin"></span> ' + text
        );
    };
    
    window.sawButtonReset = function($button) {
        const originalText = $button.data('original-text');
        if (originalText) {
            $button.prop('disabled', false).html(originalText);
        }
    };
    
    // ========================================
    // CONFIRMATION DIALOGS
    // ========================================
    
    window.sawConfirm = function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    };
    
    // ========================================
    // TABLE HELPERS
    // ========================================
    
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
        initMobileMenu();
        initSidebarAccordion();
        initUserMenu();
        initTableRowHover();
        
        document.body.classList.add('loaded');
        
        if (sawGlobal.debug) {
            console.log('🚀 SAW App initialized', {
                sawGlobal: typeof sawGlobal !== 'undefined',
                jQuery: !!$,
                modalSystem: typeof SAWModal !== 'undefined'
            });
        }
    });
    
})(jQuery);