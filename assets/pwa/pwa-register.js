/**
 * SAW Visitors - PWA Registration Script
 * 
 * Registruje service worker a zpracovává aktualizace.
 * 
 * @package SAW_Visitors
 * @version 3.1.0
 * 
 * CHANGELOG:
 * - 3.1.0: Added auto-reload after 5min background (self-healing for stale nonce/session)
 * - 3.0.0: Simplified - removed redundant health checks
 */

(function() {
    'use strict';
    
    var SW_PATH = '/sw.js';
    var SW_SCOPE = '/';
    var deferredPrompt = null;
    
    // ============================================
    // SERVICE WORKER REGISTRATION
    // ============================================
    
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            registerServiceWorker();
        });
    }
    
    /**
     * Registrace Service Workeru
     */
    function registerServiceWorker() {
        navigator.serviceWorker.register(SW_PATH, {
            scope: SW_SCOPE
        }).then(function(registration) {
            
            // Sleduj aktualizace
            registration.addEventListener('updatefound', function() {
                var newWorker = registration.installing;
                
                if (newWorker) {
                    newWorker.addEventListener('statechange', function() {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            showUpdateNotification(newWorker);
                        }
                    });
                }
            });
            
            // Kontroluj aktualizace každou hodinu
            setInterval(function() {
                registration.update();
            }, 60 * 60 * 1000);
            
        }).catch(function(error) {
            // SW registration failed - silently ignore
        });
        
        // Listen for SW messages
        if (navigator.serviceWorker) {
            navigator.serviceWorker.addEventListener('message', function(event) {
                if (event.data === 'refresh') {
                    window.location.reload(true);
                }
            });
        }
    }
    
    // ============================================
    // UPDATE NOTIFICATION
    // ============================================
    
    function showUpdateNotification(worker) {
        // Zkontroluj zda už není zobrazena
        if (document.getElementById('saw-pwa-update-banner')) {
            return;
        }
        
        var banner = document.createElement('div');
        banner.id = 'saw-pwa-update-banner';
        banner.innerHTML = 
            '<div class="saw-pwa-update-content">' +
                '<span class="saw-pwa-update-icon">🔄</span>' +
                '<span class="saw-pwa-update-text">Je dostupná nová verze aplikace</span>' +
                '<button class="saw-pwa-update-btn" id="saw-pwa-update-btn">Aktualizovat</button>' +
                '<button class="saw-pwa-update-close" id="saw-pwa-update-close">×</button>' +
            '</div>';
        
        addNotificationStyles();
        document.body.appendChild(banner);
        
        document.getElementById('saw-pwa-update-btn').addEventListener('click', function() {
            worker.postMessage('skipWaiting');
            window.location.reload();
        });
        
        document.getElementById('saw-pwa-update-close').addEventListener('click', function() {
            banner.remove();
        });
    }
    
    function addNotificationStyles() {
        if (document.getElementById('saw-pwa-styles')) {
            return;
        }
        
        var style = document.createElement('style');
        style.id = 'saw-pwa-styles';
        style.textContent = 
            '#saw-pwa-update-banner {' +
                'position: fixed;' +
                'bottom: 20px;' +
                'left: 50%;' +
                'transform: translateX(-50%);' +
                'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);' +
                'color: white;' +
                'padding: 12px 20px;' +
                'border-radius: 12px;' +
                'box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);' +
                'z-index: 999999;' +
                'font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;' +
                'font-size: 14px;' +
                'animation: sawPwaSlideUp 0.3s ease-out;' +
            '}' +
            '@keyframes sawPwaSlideUp {' +
                'from { transform: translateX(-50%) translateY(100px); opacity: 0; }' +
                'to { transform: translateX(-50%) translateY(0); opacity: 1; }' +
            '}' +
            '.saw-pwa-update-content {' +
                'display: flex;' +
                'align-items: center;' +
                'gap: 12px;' +
            '}' +
            '.saw-pwa-update-icon {' +
                'font-size: 20px;' +
            '}' +
            '.saw-pwa-update-btn {' +
                'background: white;' +
                'color: #667eea;' +
                'border: none;' +
                'padding: 8px 16px;' +
                'border-radius: 6px;' +
                'font-weight: 600;' +
                'cursor: pointer;' +
                'transition: transform 0.2s;' +
            '}' +
            '.saw-pwa-update-btn:hover {' +
                'transform: scale(1.05);' +
            '}' +
            '.saw-pwa-update-close {' +
                'background: transparent;' +
                'border: none;' +
                'color: white;' +
                'font-size: 20px;' +
                'cursor: pointer;' +
                'opacity: 0.7;' +
                'padding: 0 0 0 8px;' +
            '}' +
            '.saw-pwa-update-close:hover {' +
                'opacity: 1;' +
            '}' +
            '@media (max-width: 500px) {' +
                '#saw-pwa-update-banner {' +
                    'left: 10px;' +
                    'right: 10px;' +
                    'transform: none;' +
                    'bottom: 10px;' +
                '}' +
                '.saw-pwa-update-text { display: none; }' +
                '@keyframes sawPwaSlideUp {' +
                    'from { transform: translateY(100px); opacity: 0; }' +
                    'to { transform: translateY(0); opacity: 1; }' +
                '}' +
            '}';
        
        document.head.appendChild(style);
    }
    
    // ============================================
    // INSTALL PROMPT
    // ============================================
    
    window.addEventListener('beforeinstallprompt', function(e) {
        e.preventDefault();
        deferredPrompt = e;
    });
    
    /**
     * Spustí install prompt
     */
    window.sawPwaInstall = function() {
        if (!deferredPrompt) {
            return Promise.resolve(false);
        }
        
        deferredPrompt.prompt();
        
        return deferredPrompt.userChoice.then(function(result) {
            deferredPrompt = null;
            return result.outcome === 'accepted';
        });
    };
    
    // ============================================
    // INSTALLED DETECTION
    // ============================================
    
    window.addEventListener('appinstalled', function() {
        deferredPrompt = null;
    });
    
    // Detekce standalone módu
    if (window.matchMedia('(display-mode: standalone)').matches) {
        document.body.classList.add('saw-pwa-standalone');
    }
    
    // ============================================
    // RESUME HANDLING & SELF-HEALING
    // ============================================
    
    /**
     * Auto-reload after long background suspension
     * 
     * When Android/iOS puts PWA to background, it freezes the WebView.
     * After resume, nonce and session tokens may be stale/expired.
     * Instead of waiting for AJAX errors, proactively reload after
     * long suspension to ensure fresh tokens.
     * 
     * @since 3.1.0
     */
    var lastVisibleTime = Date.now();
    var REFRESH_THRESHOLD = 5 * 60 * 1000; // 5 minutes
    
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            var now = Date.now();
            var timeDiff = now - lastVisibleTime;
            
            // If app was in background longer than threshold
            if (timeDiff > REFRESH_THRESHOLD) {
                // Only reload if online (otherwise user would see error)
                if (navigator.onLine) {
                    // Soft reload to refresh nonce and sessions
                    // This prevents errors on first click after resume
                    window.location.reload();
                }
            }
            
            // Update last visible time (for next check)
            lastVisibleTime = now;
        } else {
            // App going to background - save current time
            lastVisibleTime = Date.now();
        }
    });
    
    // ============================================
    // GLOBAL API
    // ============================================
    
    window.SAW_PWA = {
        install: window.sawPwaInstall,
        clearCache: function() {
            if (navigator.serviceWorker && navigator.serviceWorker.controller) {
                navigator.serviceWorker.controller.postMessage('clearCache');
            }
        },
        canInstall: function() {
            return !!deferredPrompt;
        },
        isStandalone: function() {
            return window.matchMedia('(display-mode: standalone)').matches;
        }
    };
    
})();