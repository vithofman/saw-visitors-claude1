/**
 * SAW App JavaScript - HOTFIX EDITION
 * 
 * HOTFIX v5.4.2:
 * - ODSTRANĚN: Delete button handler (přesunut výhradně do sidebar.js)
 * - Opraveno: Duplicitní delete handlers
 * - Opraveno: Dvakrát confirm dialog
 * 
 * @package SAW_Visitors
 * @version 5.4.2 - HOTFIX: Removed duplicate delete handler
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
    // ✅ HOTFIX: REMOVED - Delete handler je nyní pouze v sidebar.js
    // Důvod: Duplicitní handlers způsobovaly 2x confirm dialog a 2x AJAX request
    // ========================================
    
    // DELETE HANDLER JE NYNÍ POUZE V: includes/components/admin-table/sidebar.js
    // Řádek ~280-380 v sidebar.js: initDeleteButton()
    
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
            console.log('🚀 SAW App initialized v5.4.2 HOTFIX', {
                sawGlobal: typeof sawGlobal !== 'undefined',
                jQuery: !!$,
                modalSystem: typeof SAWModal !== 'undefined',
                deleteHandler: '✅ Moved to sidebar.js'
            });
        }
    });
    
})(jQuery);