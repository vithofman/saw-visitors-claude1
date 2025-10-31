/**
 * SAW App JavaScript
 * 
 * Main app functionality for frontend layout
 * - User menu dropdown
 * - Body loaded class
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 */

(function() {
    'use strict';
    
    /**
     * User menu dropdown toggle
     * 
     * Toggles the user dropdown menu in header
     * Closes when clicking outside
     */
    function initUserMenu() {
        const toggle = document.getElementById('sawUserMenuToggle');
        const dropdown = document.getElementById('sawUserDropdown');
        
        if (!toggle || !dropdown) return;
        
        // Toggle dropdown on button click
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('active');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            dropdown.classList.remove('active');
        });
        
        // Prevent closing when clicking inside dropdown
        dropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
    
    /**
     * Initialize on DOM ready
     */
    document.addEventListener('DOMContentLoaded', function() {
        initUserMenu();
        
        // Add loaded class to body for CSS fade-in effects
        document.body.classList.add('loaded');
    });
    
})();