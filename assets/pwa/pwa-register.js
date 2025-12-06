/**
 * SAW Visitors - PWA Registration Script
 * 
 * Registruje service worker a zpracovává aktualizace.
 * 
 * @version 1.0.0
 */

(function() {
    'use strict';
    
    // Konfigurace
    const SW_PATH = '/sw.js'; // Bude servírován přes PHP rewrite
    const SW_SCOPE = '/';
    
    // ============================================
    // SERVICE WORKER REGISTRATION
    // ============================================
    
    if ('serviceWorker' in navigator) {
        // Počkej na load stránky
        window.addEventListener('load', () => {
            registerServiceWorker();
        });
    } else {
        console.log('[PWA] Service Worker není podporován v tomto prohlížeči');
    }
    
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
    // UPDATE NOTIFICATION
    // ============================================
    
    /**
     * Zobrazí notifikaci o dostupné aktualizaci
     */
    function showUpdateNotification(worker) {
        // Vytvoř notifikační banner
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
        
        // Přidej styly
        const style = document.createElement('style');
        style.textContent = `
            #saw-pwa-update-banner {
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
                #saw-pwa-update-banner {
                    left: 10px;
                    right: 10px;
                    transform: none;
                    bottom: 10px;
                }
                
                .saw-pwa-update-text {
                    display: none;
                }
            }
        `;
        
        document.head.appendChild(style);
        document.body.appendChild(banner);
        
        // Event handlers
        document.getElementById('saw-pwa-update-btn').addEventListener('click', () => {
            // Aktivuj nového workera
            worker.postMessage('skipWaiting');
            // Reload stránku
            window.location.reload();
        });
        
        document.getElementById('saw-pwa-update-close').addEventListener('click', () => {
            banner.remove();
        });
    }
    
    // ============================================
    // INSTALL PROMPT
    // ============================================
    
    let deferredPrompt = null;
    
    window.addEventListener('beforeinstallprompt', (e) => {
        // Zabraň automatickému zobrazení
        e.preventDefault();
        // Ulož event pro pozdější použití
        deferredPrompt = e;
        
        console.log('[PWA] Install prompt uložen');
        
        // Můžeš zobrazit vlastní tlačítko "Instalovat"
        showInstallButton();
    });
    
    /**
     * Zobrazí tlačítko pro instalaci (volitelné)
     */
    function showInstallButton() {
        // Tuto funkci můžeš rozšířit pro zobrazení install tlačítka v UI
        // Například přidat tlačítko do menu nebo sidebar
        
        // Pro teď jen loguj
        console.log('[PWA] Aplikace je připravena k instalaci');
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
        
        // Zobraz prompt
        deferredPrompt.prompt();
        
        // Počkej na odpověď
        const { outcome } = await deferredPrompt.userChoice;
        
        console.log('[PWA] Install prompt outcome:', outcome);
        
        // Vyčisti
        deferredPrompt = null;
        
        return outcome === 'accepted';
    };
    
    // ============================================
    // INSTALLED DETECTION
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
    
})();