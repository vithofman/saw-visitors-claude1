/**
 * SAW Visitors - Main App JavaScript
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 */

(function($) {
    'use strict';
    
    // ============================================
    // USER MENU DROPDOWN
    // ============================================
    const userMenuToggle = document.getElementById('sawUserMenuToggle');
    const userDropdown = document.getElementById('sawUserDropdown');
    
    if (userMenuToggle && userDropdown) {
        userMenuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('active');
            
            // Close customer switcher if open
            const customerDropdown = document.getElementById('sawCustomerSwitcherDropdown');
            if (customerDropdown) {
                customerDropdown.classList.remove('active');
            }
        });
    }
    
    // ============================================
    // CUSTOMER SWITCHER DROPDOWN
    // ============================================
    const customerSwitcherToggle = document.getElementById('sawCustomerSwitcherToggle');
    const customerDropdown = document.getElementById('sawCustomerSwitcherDropdown');
    
    if (customerSwitcherToggle && customerDropdown) {
        customerSwitcherToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            customerDropdown.classList.toggle('active');
            
            // Close user menu if open
            if (userDropdown) {
                userDropdown.classList.remove('active');
            }
        });
    }
    
    // ============================================
    // CUSTOMER SWITCH ACTION
    // ============================================
    const customerItems = document.querySelectorAll('.saw-customer-item');
    
    customerItems.forEach(function(item) {
        item.addEventListener('click', function() {
            const customerId = this.getAttribute('data-customer-id');
            
            if (!customerId) return;
            
            // Show loading
            this.style.opacity = '0.6';
            this.style.pointerEvents = 'none';
            
            // Send AJAX request
            $.ajax({
                url: sawApp.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'saw_switch_customer',
                    customer_id: customerId,
                    nonce: sawApp.nonce
                },
                success: function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        alert('Chyba při přepínání zákazníka: ' + response.data.message);
                        item.style.opacity = '1';
                        item.style.pointerEvents = 'auto';
                    }
                },
                error: function() {
                    alert('Chyba při komunikaci se serverem');
                    item.style.opacity = '1';
                    item.style.pointerEvents = 'auto';
                }
            });
        });
    });
    
    // ============================================
    // CLOSE DROPDOWNS ON OUTSIDE CLICK
    // ============================================
    document.addEventListener('click', function(e) {
        if (userDropdown && !userMenuToggle.contains(e.target)) {
            userDropdown.classList.remove('active');
        }
        
        if (customerDropdown && !customerSwitcherToggle.contains(e.target)) {
            customerDropdown.classList.remove('active');
        }
    });
    
    // ============================================
    // MOBILE MENU TOGGLE
    // ============================================
    const mobileMenuToggle = document.createElement('button');
    mobileMenuToggle.className = 'saw-mobile-menu-toggle';
    mobileMenuToggle.innerHTML = '☰';
    document.body.appendChild(mobileMenuToggle);
    
    const sidebar = document.querySelector('.saw-app-sidebar');
    
    if (sidebar) {
        mobileMenuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('mobile-open');
            this.innerHTML = sidebar.classList.contains('mobile-open') ? '✕' : '☰';
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(e.target) && !mobileMenuToggle.contains(e.target)) {
                    sidebar.classList.remove('mobile-open');
                    mobileMenuToggle.innerHTML = '☰';
                }
            }
        });
    }
    
    // ============================================
    // ESCAPE KEY TO CLOSE DROPDOWNS
    // ============================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (userDropdown) userDropdown.classList.remove('active');
            if (customerDropdown) customerDropdown.classList.remove('active');
            if (sidebar && sidebar.classList.contains('mobile-open')) {
                sidebar.classList.remove('mobile-open');
                mobileMenuToggle.innerHTML = '☰';
            }
        }
    });
    
})(jQuery);
