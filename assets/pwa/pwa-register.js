/**
 * SAW PWA Register - MOBILE STABILITY FIX
 * 
 * @version 7.0.0
 * 
 * Na mobilech:
 * - Service Worker VYPNUTÝ
 * - Reload při KAŽDÉM návratu z pozadí
 */

(function() {
    'use strict';
    
    var isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
    var isPWA = window.matchMedia('(display-mode: standalone)').matches;
    var isMobileOrPWA = isMobile || isPWA;
    
    // ============================================
    // MOBILE: Reload při návratu z pozadí
    // ============================================
    
    if (isMobileOrPWA) {
        var wasHidden = false;
        
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'hidden') {
                wasHidden = true;
            } else if (document.visibilityState === 'visible' && wasHidden) {
                // Byl na pozadí → RELOAD
                window.location.reload();
            }
        });
        
        // Hotovo - na mobilu nic víc nedělej
        return;
    }
    
    // ============================================
    // DESKTOP: Normální Service Worker
    // ============================================
    
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function() {});
    }
    
})();