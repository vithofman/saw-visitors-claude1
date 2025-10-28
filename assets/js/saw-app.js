/**
 * SAW App JavaScript
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 */

(function() {
    'use strict';
    
    /**
     * User menu dropdown toggle
     */
    function initUserMenu() {
        const toggle = document.getElementById('sawUserMenuToggle');
        const dropdown = document.getElementById('sawUserDropdown');
        
        if (!toggle || !dropdown) return;
        
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('active');
        });
        
        // Close on click outside
        document.addEventListener('click', function() {
            dropdown.classList.remove('active');
        });
        
        dropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
    
    /**
     * Initialize on DOM ready
     */
    document.addEventListener('DOMContentLoaded', function() {
        initUserMenu();
        
        // Add loaded class to body (for fade-in effect)
        document.body.classList.add('loaded');
    });
    
})();