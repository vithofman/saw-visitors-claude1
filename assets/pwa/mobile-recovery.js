/**
 * SAW Mobile Recovery Script
 * 
 * Detekuje "zombie" stav stránky na mobilech a automaticky refreshuje.
 * Přidej do layoutu před </body> nebo do hlavního JS souboru.
 * 
 * @version 1.1.0
 * @fix 1.1.0 - Přidána ochrana proti duplikaci event listenerů při bfcache restore
 */
(function() {
    'use strict';
    
    // CRITICAL FIX: Prevent duplicate initialization if script is re-evaluated
    if (window._sawMobileRecoveryInitialized) {
        console.log('[Mobile Recovery] Already initialized, skipping');
        return;
    }
    window._sawMobileRecoveryInitialized = true;
    
    // Pouze pro mobilní zařízení
    const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
    if (!isMobile) return;
    
    console.log('[Mobile Recovery] Initialized');
    
    let lastVisibleTime = Date.now();
    let pageHiddenTime = null;
    
    // Kratší threshold pro mobily (5 minut místo 30)
    const MOBILE_STALE_THRESHOLD = 5 * 60 * 1000; // 5 minut
    
    // Use named functions so we can remove them if needed
    function visibilityChangeHandler() {
        if (document.visibilityState === 'hidden') {
            pageHiddenTime = Date.now();
            console.log('[Mobile Recovery] Page hidden');
        } else {
            const hiddenDuration = pageHiddenTime ? Date.now() - pageHiddenTime : 0;
            console.log('[Mobile Recovery] Page visible after ' + Math.round(hiddenDuration/1000) + 's');
            
            if (hiddenDuration > MOBILE_STALE_THRESHOLD) {
                console.log('[Mobile Recovery] Page stale, checking...');
                checkAndRecover();
            }
            
            lastVisibleTime = Date.now();
            pageHiddenTime = null;
        }
    }
    
    function pageshowHandler(event) {
        if (event.persisted) {
            console.log('[Mobile Recovery] Restored from bfcache');
            // Stránka byla obnovena z bfcache - zkontroluj stav
            setTimeout(checkAndRecover, 100);
        }
    }
    
    // Detekce visibility change
    document.addEventListener('visibilitychange', visibilityChangeHandler);
    
    // iOS Safari: pageshow event pro bfcache
    window.addEventListener('pageshow', pageshowHandler);
    
    // Store handlers for potential cleanup
    window._sawMobileRecoveryHandlers = {
        visibilitychange: visibilityChangeHandler,
        pageshow: pageshowHandler
    };
    
    // Hlavní recovery funkce
    function checkAndRecover() {
        // 1. Zkontroluj zda je hlavní obsah viditelný
        const mainContent = document.querySelector('main, .saw-container, [data-saw-module], .saw-page-content');
        const hasContent = mainContent && mainContent.offsetHeight > 100;
        
        // 2. Zkontroluj zda není error overlay
        const hasError = document.querySelector('.fatal-error, .js-error, .saw-error-overlay');
        
        // 3. Zkontroluj zda nejsou všechny AJAX requesty mrtvé
        const isResponsive = checkPageResponsive();
        
        console.log('[Mobile Recovery] Check:', {
            hasContent: hasContent,
            hasError: !!hasError,
            isResponsive: isResponsive
        });
        
        // Pokud je problém, refreshni
        if (!hasContent || hasError) {
            console.log('[Mobile Recovery] Refreshing page...');
            forceRefresh();
            return;
        }
        
        // Pokud obsah existuje ale stránka neodpovídá, zkus soft recovery
        if (!isResponsive) {
            console.log('[Mobile Recovery] Attempting soft recovery...');
            softRecover();
        }
    }
    
    // Kontrola zda stránka odpovídá
    function checkPageResponsive() {
        // Zkus jednoduchý AJAX request
        try {
            const xhr = new XMLHttpRequest();
            xhr.open('HEAD', window.location.href + '?_=' + Date.now(), false); // Synchronní
            xhr.timeout = 3000;
            xhr.send();
            return xhr.status === 200;
        } catch (e) {
            return navigator.onLine; // Fallback na online status
        }
    }
    
    // Soft recovery - zkus obnovit bez full refresh
    function softRecover() {
        // Trigger custom event pro aplikaci
        const event = new CustomEvent('saw:mobile-recovery', { detail: { timestamp: Date.now() } });
        document.dispatchEvent(event);
        
        // Pokud existuje SAW router, zkus ho
        if (window.SAWRouter && typeof window.SAWRouter.refresh === 'function') {
            window.SAWRouter.refresh();
            return;
        }
        
        // Pokud existuje globální reload handler
        if (window.sawReloadContent && typeof window.sawReloadContent === 'function') {
            window.sawReloadContent();
            return;
        }
        
        // Fallback: full refresh
        forceRefresh();
    }
    
    // Force refresh
    function forceRefresh() {
        // Vymaž SW cache před refreshem
        if (navigator.serviceWorker && navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage('clearCache');
        }
        
        // Malý delay pro SW message
        setTimeout(function() {
            window.location.reload(true);
        }, 150);
    }
    
    // Heartbeat - kontroluj stav každých 30 sekund když je stránka viditelná
    setInterval(function() {
        if (document.visibilityState !== 'visible') return;
        
        // Pokud stránka běží déle než 30 minut, zkontroluj session
        const pageAge = Date.now() - performance.timing.navigationStart;
        if (pageAge > 30 * 60 * 1000) {
            checkSessionQuietly();
        }
    }, 30000);
    
    // Tichá kontrola session
    function checkSessionQuietly() {
        fetch('/wp-admin/admin-ajax.php?action=saw_heartbeat', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store'
        })
        .then(function(response) {
            if (!response.ok) {
                console.log('[Mobile Recovery] Server not responding, will refresh on next visibility');
            }
        })
        .catch(function() {
            // Tiše ignoruj - refreshne se při dalším visibility change
        });
    }
    
    // Debug API
    window.SAW_MobileRecovery = {
        check: checkAndRecover,
        refresh: forceRefresh,
        status: function() {
            return {
                isMobile: isMobile,
                lastVisible: new Date(lastVisibleTime).toISOString(),
                pageHidden: pageHiddenTime ? new Date(pageHiddenTime).toISOString() : null,
                online: navigator.onLine
            };
        }
    };
    
})();