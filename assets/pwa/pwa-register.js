/**
 * SAW Visitors - PWA Registration Script
 * 
 * Registruje service worker a zpracovává aktualizace.
 * 
 * @version 2.1.0
 * @fix 2.1.0 - Přidána ochrana proti duplikaci event listenerů při bfcache restore
 * @fix Přidána detekce návratu z pozadí
 * @fix Auto-refresh při stale stránce
 * @fix Lepší error recovery
 */

(function() {
    'use strict';
    
    // ============================================
    // KONFIGURACE
    // ============================================
    
    const SW_PATH = '/sw.js';
    const SW_SCOPE = '/';
    
    // Po kolika minutách neaktivity považovat stránku za "stale"
    const STALE_THRESHOLD_MINUTES = 30;
    
    // Tracking proměnné
    let lastActivityTime = Date.now();
    let isPageVisible = true;
    let deferredPrompt = null;
    
    // ============================================
    // SERVICE WORKER REGISTRATION
    // ============================================
    
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            registerServiceWorker();
            setupVisibilityDetection();
            setupActivityTracking();
            setupMessageListener();
        });
    } else {
        console.log('[PWA] Service Worker není podporován v tomto prohlížeči');
    }
    
    // CRITICAL FIX: Handle bfcache restore - ensure setup functions aren't called again
    // When page is restored from bfcache, 'load' event doesn't fire again,
    // but if script is re-evaluated, we need to prevent duplicate listeners
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            console.log('[PWA] Page restored from bfcache - checking initialization state');
            // Event listeners should persist through bfcache, but if script
            // was re-evaluated, flags will be reset - check and re-initialize if needed
            if (!window._sawPwaInitialized) {
                console.log('[PWA] Script re-evaluated, re-initializing...');
                if ('serviceWorker' in navigator) {
                    // Only re-initialize if not already done
                    if (!window._sawPwaVisibilityInitialized) {
                        setupVisibilityDetection();
                    }
                    if (!window._sawPwaActivityInitialized) {
                        setupActivityTracking();
                    }
                    if (!window._sawPwaMessageInitialized) {
                        setupMessageListener();
                    }
                }
                window._sawPwaInitialized = true;
            }
        }
    });
    
    /**
     * Registrace Service Workeru
     */
    async function registerServiceWorker() {
        try {
            const registration = await navigator.serviceWorker.register(SW_PATH, {
                scope: SW_SCOPE
            });
            
            console.log('[PWA] Service Worker registrován:', registration.scope);
            
            // Sleduj aktualizace
            registration.addEventListener('updatefound', () => {
                const newWorker = registration.installing;
                
                console.log('[PWA] Nalezena nová verze Service Workeru...');
                
                newWorker.addEventListener('statechange', () => {
                    if (newWorker.state === 'installed') {
                        if (navigator.serviceWorker.controller) {
                            // Nová verze je připravena, stará stále běží
                            console.log('[PWA] Nová verze připravena');
                            showUpdateNotification(newWorker);
                        } else {
                            // První instalace
                            console.log('[PWA] Service Worker nainstalován poprvé');
                        }
                    }
                });
            });
            
            // Kontroluj aktualizace každou hodinu
            setInterval(() => {
                registration.update();
            }, 60 * 60 * 1000);
            
        } catch (error) {
            console.error('[PWA] Registrace Service Workeru selhala:', error);
        }
    }
    
    // ============================================
    // VISIBILITY DETECTION - CRITICAL FIX
    // ============================================
    
    /**
     * Detekce kdy uživatel opustí a vrátí se na stránku
     * 
     * @fix 2.1.0 - Přidána ochrana proti duplikaci listenerů
     */
    function setupVisibilityDetection() {
        // CRITICAL FIX: Check if already initialized to prevent duplicates
        if (window._sawPwaVisibilityInitialized) {
            console.log('[PWA] Visibility detection already initialized, skipping');
            return;
        }
        
        window._sawPwaVisibilityInitialized = true;
        
        // Use named functions so we can remove them if needed
        function visibilityChangeHandler() {
            if (document.visibilityState === 'visible') {
                onPageBecameVisible();
            } else {
                onPageBecameHidden();
            }
        }
        
        function pageshowHandler(event) {
            if (event.persisted) {
                console.log('[PWA] Page restored from bfcache');
                onPageBecameVisible();
            }
        }
        
        document.addEventListener('visibilitychange', visibilityChangeHandler);
        
        // Fallback pro starší prohlížeče
        window.addEventListener('focus', onPageBecameVisible);
        window.addEventListener('blur', onPageBecameHidden);
        
        // iOS Safari - bfcache handling
        window.addEventListener('pageshow', pageshowHandler);
        
        // Store handlers for potential cleanup
        window._sawPwaVisibilityHandlers = {
            visibilitychange: visibilityChangeHandler,
            pageshow: pageshowHandler
        };
    }
    
    function onPageBecameVisible() {
        if (isPageVisible) return;
        
        isPageVisible = true;
        const hiddenDuration = Date.now() - lastActivityTime;
        const hiddenMinutes = Math.round(hiddenDuration / 1000 / 60);
        
        console.log(`[PWA] Page became visible after ${hiddenMinutes} minutes`);
        
        if (hiddenMinutes >= STALE_THRESHOLD_MINUTES) {
            console.log('[PWA] Page is stale, checking health...');
            checkPageHealth();
        } else if (hiddenMinutes >= 5) {
            checkConnectivity();
        }
        
        lastActivityTime = Date.now();
    }
    
    function onPageBecameHidden() {
        isPageVisible = false;
        lastActivityTime = Date.now();
    }
    
    // ============================================
    // PAGE HEALTH CHECK
    // ============================================
    
    async function checkPageHealth() {
        try {
            // 1. Zkontroluj zda DOM není prázdný
            const mainContent = document.querySelector('main, #app, .saw-container, [data-saw-module]');
            if (!mainContent || mainContent.innerHTML.trim() === '') {
                console.log('[PWA] Main content is empty, refreshing...');
                hardRefresh();
                return;
            }
            
            // 2. Zkontroluj error stav
            const errorIndicators = document.querySelectorAll('.fatal-error, .js-error, [data-error="true"]');
            if (errorIndicators.length > 0) {
                console.log('[PWA] Error state detected, refreshing...');
                hardRefresh();
                return;
            }
            
            // 3. Zkontroluj connectivity
            const isOnline = await checkServerConnectivity();
            if (!isOnline) {
                showOfflineNotification();
                return;
            }
            
            // 4. Zkontroluj session
            const hasAuthElements = document.querySelector('[data-requires-auth], form[data-autosave]');
            if (hasAuthElements) {
                const sessionValid = await checkSession();
                if (!sessionValid) {
                    showSessionExpiredNotification();
                    return;
                }
            }
            
            console.log('[PWA] Page health check passed');
            
        } catch (error) {
            console.error('[PWA] Health check failed:', error);
            hardRefresh();
        }
    }
    
    async function checkServerConnectivity() {
        try {
            const response = await fetch('/wp-admin/admin-ajax.php?action=saw_heartbeat', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store'
            });
            return response.ok;
        } catch {
            return navigator.onLine;
        }
    }
    
    async function checkSession() {
        try {
            const response = await fetch('/wp-admin/admin-ajax.php?action=saw_check_session', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                cache: 'no-store',
                credentials: 'same-origin'
            });
            
            if (!response.ok) return false;
            
            const data = await response.json();
            return data.success && data.data?.logged_in;
        } catch {
            return true; // Pokud selže, předpokládej OK
        }
    }
    
    function checkConnectivity() {
        if (!navigator.onLine) {
            showOfflineNotification();
        }
    }
    
    // ============================================
    // ACTIVITY TRACKING
    // ============================================
    
    /**
     * Setup activity tracking for user interactions
     * 
     * @fix 2.1.0 - Přidána ochrana proti duplikaci listenerů
     */
    function setupActivityTracking() {
        // CRITICAL FIX: Check if already initialized to prevent duplicates
        if (window._sawPwaActivityInitialized) {
            console.log('[PWA] Activity tracking already initialized, skipping');
            return;
        }
        
        window._sawPwaActivityInitialized = true;
        
        // Use named function so we can remove it if needed
        function activityHandler() {
            lastActivityTime = Date.now();
        }
        
        const events = ['mousedown', 'keydown', 'touchstart', 'scroll'];
        events.forEach(event => {
            document.addEventListener(event, activityHandler, { passive: true });
        });
        
        // Store handler for potential cleanup
        window._sawPwaActivityHandler = activityHandler;
    }
    
    // ============================================
    // MESSAGE LISTENER
    // ============================================
    
    /**
     * Setup message listener for service worker communication
     * 
     * @fix 2.1.0 - Přidána ochrana proti duplikaci listenerů
     */
    function setupMessageListener() {
        // CRITICAL FIX: Check if already initialized to prevent duplicates
        if (window._sawPwaMessageInitialized) {
            console.log('[PWA] Message listener already initialized, skipping');
            return;
        }
        
        // Check if service worker is available
        if (!navigator.serviceWorker) {
            return;
        }
        
        window._sawPwaMessageInitialized = true;
        
        // Use named function so we can remove it if needed
        function messageHandler(event) {
            if (event.data === 'refresh') {
                hardRefresh();
            }
        }
        
        navigator.serviceWorker.addEventListener('message', messageHandler);
        
        // Store handler for potential cleanup
        window._sawPwaMessageHandler = messageHandler;
    }
    
    // ============================================
    // REFRESH FUNCTIONS
    // ============================================
    
    function hardRefresh() {
        console.log('[PWA] Performing hard refresh');
        
        if (navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage('clearCache');
        }
        
        setTimeout(() => {
            window.location.reload(true);
        }, 100);
    }
    
    // ============================================
    // UPDATE NOTIFICATION
    // ============================================
    
    function showUpdateNotification(worker) {
        const banner = document.createElement('div');
        banner.id = 'saw-pwa-update-banner';
        banner.innerHTML = `
            <div class="saw-pwa-update-content">
                <span class="saw-pwa-update-icon">🔄</span>
                <span class="saw-pwa-update-text">Je dostupná nová verze aplikace</span>
                <button class="saw-pwa-update-btn" id="saw-pwa-update-btn">Aktualizovat</button>
                <button class="saw-pwa-update-close" id="saw-pwa-update-close">×</button>
            </div>
        `;
        
        addNotificationStyles();
        document.body.appendChild(banner);
        
        document.getElementById('saw-pwa-update-btn').addEventListener('click', () => {
            worker.postMessage('skipWaiting');
            window.location.reload();
        });
        
        document.getElementById('saw-pwa-update-close').addEventListener('click', () => {
            banner.remove();
        });
    }
    
    function showOfflineNotification() {
        if (document.getElementById('saw-pwa-offline-banner')) return;
        
        const banner = document.createElement('div');
        banner.id = 'saw-pwa-offline-banner';
        banner.innerHTML = `
            <div class="saw-pwa-update-content">
                <span class="saw-pwa-update-icon">📡</span>
                <span class="saw-pwa-update-text">Jste offline</span>
                <button class="saw-pwa-update-btn" id="saw-pwa-retry-btn">Zkusit znovu</button>
                <button class="saw-pwa-update-close" id="saw-pwa-offline-close">×</button>
            </div>
        `;
        
        addNotificationStyles();
        document.body.appendChild(banner);
        
        document.getElementById('saw-pwa-retry-btn').addEventListener('click', () => {
            banner.remove();
            checkPageHealth();
        });
        
        document.getElementById('saw-pwa-offline-close').addEventListener('click', () => {
            banner.remove();
        });
        
        window.addEventListener('online', () => banner.remove(), { once: true });
    }
    
    function showSessionExpiredNotification() {
        const banner = document.createElement('div');
        banner.id = 'saw-pwa-session-banner';
        banner.innerHTML = `
            <div class="saw-pwa-update-content">
                <span class="saw-pwa-update-icon">🔐</span>
                <span class="saw-pwa-update-text">Vaše přihlášení vypršelo</span>
                <button class="saw-pwa-update-btn" id="saw-pwa-login-btn">Přihlásit se</button>
            </div>
        `;
        
        addNotificationStyles();
        document.body.appendChild(banner);
        
        document.getElementById('saw-pwa-login-btn').addEventListener('click', () => {
            const currentUrl = encodeURIComponent(window.location.href);
            window.location.href = '/login/?redirect=' + currentUrl;
        });
    }
    
    function addNotificationStyles() {
        if (document.getElementById('saw-pwa-styles')) return;
        
        const style = document.createElement('style');
        style.id = 'saw-pwa-styles';
        style.textContent = `
            #saw-pwa-update-banner,
            #saw-pwa-offline-banner,
            #saw-pwa-session-banner {
                position: fixed;
                bottom: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 12px 20px;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
                z-index: 999999;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                font-size: 14px;
                animation: slideUp 0.3s ease-out;
            }
            
            @keyframes slideUp {
                from {
                    transform: translateX(-50%) translateY(100px);
                    opacity: 0;
                }
                to {
                    transform: translateX(-50%) translateY(0);
                    opacity: 1;
                }
            }
            
            .saw-pwa-update-content {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            
            .saw-pwa-update-icon {
                font-size: 20px;
            }
            
            .saw-pwa-update-btn {
                background: white;
                color: #667eea;
                border: none;
                padding: 8px 16px;
                border-radius: 6px;
                font-weight: 600;
                cursor: pointer;
                transition: transform 0.2s;
            }
            
            .saw-pwa-update-btn:hover {
                transform: scale(1.05);
            }
            
            .saw-pwa-update-close {
                background: transparent;
                border: none;
                color: white;
                font-size: 20px;
                cursor: pointer;
                opacity: 0.7;
                padding: 0 0 0 8px;
            }
            
            .saw-pwa-update-close:hover {
                opacity: 1;
            }
            
            @media (max-width: 500px) {
                #saw-pwa-update-banner,
                #saw-pwa-offline-banner,
                #saw-pwa-session-banner {
                    left: 10px;
                    right: 10px;
                    transform: none;
                    bottom: 10px;
                }
                
                .saw-pwa-update-text {
                    display: none;
                }
                
                @keyframes slideUp {
                    from { transform: translateY(100px); opacity: 0; }
                    to { transform: translateY(0); opacity: 1; }
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    // ============================================
    // INSTALL PROMPT (PŮVODNÍ FUNKCIONALITA)
    // ============================================
    
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        console.log('[PWA] Install prompt uložen');
        showInstallButton();
    });
    
    function showInstallButton() {
        console.log('[PWA] Aplikace je připravena k instalaci');
        // Můžeš rozšířit pro zobrazení install tlačítka v UI
    }
    
    /**
     * Spustí install prompt
     * Volej tuto funkci z tlačítka v UI
     */
    window.sawPwaInstall = async function() {
        if (!deferredPrompt) {
            console.log('[PWA] Install prompt není k dispozici');
            return false;
        }
        
        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        console.log('[PWA] Install prompt outcome:', outcome);
        deferredPrompt = null;
        
        return outcome === 'accepted';
    };
    
    // ============================================
    // INSTALLED DETECTION (PŮVODNÍ FUNKCIONALITA)
    // ============================================
    
    window.addEventListener('appinstalled', () => {
        console.log('[PWA] Aplikace byla nainstalována');
        deferredPrompt = null;
    });
    
    // Detekce standalone módu
    if (window.matchMedia('(display-mode: standalone)').matches) {
        console.log('[PWA] Běží jako nainstalovaná aplikace');
        document.body.classList.add('saw-pwa-standalone');
    }
    
    // ============================================
    // GLOBAL API (PRO DEBUGGING)
    // ============================================
    
    window.SAW_PWA = {
        refresh: hardRefresh,
        checkHealth: checkPageHealth,
        clearCache: () => {
            if (navigator.serviceWorker.controller) {
                navigator.serviceWorker.controller.postMessage('clearCache');
            }
        },
        install: window.sawPwaInstall,
        getStatus: () => ({
            isPageVisible,
            lastActivity: new Date(lastActivityTime).toISOString(),
            minutesSinceActivity: Math.round((Date.now() - lastActivityTime) / 1000 / 60),
            online: navigator.onLine,
            canInstall: !!deferredPrompt,
            isStandalone: window.matchMedia('(display-mode: standalone)').matches
        })
    };
    
})();