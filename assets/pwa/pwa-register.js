/**
 * SAW Visitors - PWA Registration Script
 * 
 * Registruje service worker a zpracovává aktualizace.
 * 
 * @package SAW_Visitors
 * @version 5.0.0
 * 
 * CHANGELOG:
 * - 5.0.0: NUCLEAR OPTION - Service Worker DISABLED on mobile/PWA to prevent freeze
 * - 4.0.0: Aggressive mobile reload after 30s background
 * - 3.0.0: Simplified - removed redundant health checks
 * 
 * WHY DISABLE SW ON MOBILE:
 * Android aggressively freezes WebView when PWA goes to background.
 * After resume, cached content from SW may cause "white screen of death".
 * Disabling SW ensures always fresh content = no zombie state possible.
 */

(function() {
    'use strict';
    
    var SW_PATH = '/sw.js';
    var SW_SCOPE = '/';
    var deferredPrompt = null;
    
    // ============================================
    // PLATFORM DETECTION
    // ============================================
    
    var isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    var isPWA = window.matchMedia('(display-mode: standalone)').matches;
    var isMobileOrPWA = isMobile || isPWA;
    
    // ============================================
    // SERVICE WORKER REGISTRATION
    // ============================================
    
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            if (isMobileOrPWA) {
                // NUCLEAR OPTION: Unregister any existing SW on mobile/PWA
                // This prevents cached content from causing zombie state
                unregisterServiceWorker();
            } else {
                // Desktop: Register SW normally
                registerServiceWorker();
            }
        });
    }
    
    /**
     * Register Service Worker (Desktop only)
     */
    function registerServiceWorker() {
        navigator.serviceWorker.register(SW_PATH, {
            scope: SW_SCOPE
        }).then(function(registration) {
            
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
            
            setInterval(function() {
                registration.update();
            }, 60 * 60 * 1000);
            
        }).catch(function() {
            // SW registration failed - silently ignore
        });
        
        if (navigator.serviceWorker) {
            navigator.serviceWorker.addEventListener('message', function(event) {
                if (event.data === 'refresh') {
                    window.location.reload(true);
                }
            });
        }
    }
    
    /**
     * Unregister Service Worker (Mobile/PWA)
     * 
     * Removes any existing service worker to prevent cached content issues.
     */
    function unregisterServiceWorker() {
        navigator.serviceWorker.getRegistrations().then(function(registrations) {
            registrations.forEach(function(registration) {
                registration.unregister();
            });
        });
        
        // Also clear all caches
        if ('caches' in window) {
            caches.keys().then(function(names) {
                names.forEach(function(name) {
                    caches.delete(name);
                });
            });
        }
    }
    
    // ============================================
    // UPDATE NOTIFICATION (Desktop only)
    // ============================================
    
    function showUpdateNotification(worker) {
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
    
    if (isPWA) {
        document.body.classList.add('saw-pwa-standalone');
    }
    
    // ============================================
    // AGGRESSIVE MOBILE RESUME HANDLER
    // ============================================
    
    /**
     * On mobile/PWA: Reload after ANY background period > 30 seconds
     * 
     * Even without SW, the WebView state may be corrupted after resume.
     * Aggressive reload ensures always fresh, working page.
     */
    
    var hiddenTimestamp = null;
    var MOBILE_THRESHOLD = 30 * 1000;      // 30 seconds
    var DESKTOP_THRESHOLD = 10 * 60 * 1000; // 10 minutes (desktop more stable)
    
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') {
            hiddenTimestamp = Date.now();
        } 
        else if (document.visibilityState === 'visible' && hiddenTimestamp !== null) {
            var hiddenDuration = Date.now() - hiddenTimestamp;
            var threshold = isMobileOrPWA ? MOBILE_THRESHOLD : DESKTOP_THRESHOLD;
            
            if (hiddenDuration > threshold && navigator.onLine) {
                window.location.reload();
            }
            
            hiddenTimestamp = null;
        }
    });
    
    // Handle bfcache restore
    window.addEventListener('pageshow', function(event) {
        if (event.persisted && isMobileOrPWA && navigator.onLine) {
            // bfcache on mobile is problematic - always reload
            window.location.reload();
        }
    });
    
    // ============================================
    // GLOBAL API
    // ============================================
    
    window.SAW_PWA = {
        install: window.sawPwaInstall,
        clearCache: function() {
            if ('caches' in window) {
                caches.keys().then(function(names) {
                    names.forEach(function(name) {
                        caches.delete(name);
                    });
                });
            }
        },
        canInstall: function() {
            return !!deferredPrompt;
        },
        isStandalone: function() {
            return isPWA;
        },
        isMobile: function() {
            return isMobile;
        },
        isServiceWorkerEnabled: function() {
            return !isMobileOrPWA; // SW only on desktop
        },
        forceReload: function() {
            window.location.reload();
        }
    };
    
})();