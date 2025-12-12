/**
 * SAW Debug - Diagnostika bílé obrazovky
 * 
 * DOČASNÝ SCRIPT - po nalezení problému smazat!
 * Zobrazuje alerty s chybami přímo na mobilu.
 */
(function() {
    'use strict';
    
    console.log('[SAW DEBUG] Diagnostic script loaded');
    
    // ============================================
    // 1. ZACHYŤ VŠECHNY JS CHYBY
    // ============================================
    
    window.onerror = function(message, source, lineno, colno, error) {
        const info = 'JS ERROR!\n\n' +
            'Message: ' + message + '\n' +
            'File: ' + (source || '').split('/').pop() + '\n' +
            'Line: ' + lineno + ':' + colno + '\n' +
            'Stack: ' + (error?.stack || 'N/A').substring(0, 200);
        
        console.error('[SAW DEBUG]', info);
        alert(info);
        
        return false; // Nechej error propagovat
    };
    
    window.addEventListener('unhandledrejection', function(event) {
        const info = 'PROMISE REJECTION!\n\n' +
            'Reason: ' + (event.reason?.message || event.reason || 'Unknown') + '\n' +
            'Stack: ' + (event.reason?.stack || 'N/A').substring(0, 200);
        
        console.error('[SAW DEBUG]', info);
        alert(info);
    });
    
    // ============================================
    // 2. ZACHYŤ AJAX CHYBY
    // ============================================
    
    if (window.jQuery) {
        jQuery(document).ajaxError(function(event, xhr, settings, error) {
            if (xhr.statusText === 'abort') return;
            
            const info = 'AJAX ERROR!\n\n' +
                'URL: ' + settings.url + '\n' +
                'Status: ' + xhr.status + ' ' + xhr.statusText + '\n' +
                'Error: ' + error + '\n' +
                'Response: ' + (xhr.responseText || '').substring(0, 100);
            
            console.error('[SAW DEBUG]', info);
            alert(info);
        });
    }
    
    // ============================================
    // 3. SLEDUJ ZMĚNY V DOM (detekce zbělání)
    // ============================================
    
    let lastBodyLength = document.body?.innerHTML?.length || 0;
    
    setInterval(function() {
        const currentLength = document.body?.innerHTML?.length || 0;
        
        // Pokud se obsah dramaticky zmenšil (zbělání)
        if (lastBodyLength > 1000 && currentLength < 500) {
            const info = 'BODY CLEARED!\n\n' +
                'Before: ' + lastBodyLength + ' chars\n' +
                'After: ' + currentLength + ' chars\n' +
                'Content: ' + (document.body?.innerHTML || '').substring(0, 100);
            
            console.error('[SAW DEBUG]', info);
            alert(info);
        }
        
        lastBodyLength = currentLength;
    }, 500);
    
    // ============================================
    // 4. SLEDUJ NAVIGACI
    // ============================================
    
    // Zachyť změny URL
    const originalPushState = history.pushState;
    history.pushState = function() {
        console.log('[SAW DEBUG] pushState:', arguments[2]);
        return originalPushState.apply(this, arguments);
    };
    
    const originalReplaceState = history.replaceState;
    history.replaceState = function() {
        console.log('[SAW DEBUG] replaceState:', arguments[2]);
        return originalReplaceState.apply(this, arguments);
    };
    
    window.addEventListener('popstate', function(event) {
        console.log('[SAW DEBUG] popstate event', event.state);
    });
    
    // ============================================
    // 5. ZACHYŤ VŠECHNY KLIKNUTÍ
    // ============================================
    
    document.addEventListener('click', function(e) {
        const target = e.target;
        const info = {
            tag: target.tagName,
            id: target.id,
            class: target.className,
            href: target.href || target.closest('a')?.href,
            onclick: target.onclick ? 'yes' : 'no',
            dataAction: target.dataset?.action
        };
        console.log('[SAW DEBUG] Click:', info);
    }, true);
    
    // ============================================
    // 6. ZACHYŤ AJAX REQUESTY (před odesláním)
    // ============================================
    
    if (window.jQuery) {
        jQuery(document).ajaxSend(function(event, xhr, settings) {
            console.log('[SAW DEBUG] AJAX Send:', settings.url);
        });
        
        jQuery(document).ajaxComplete(function(event, xhr, settings) {
            console.log('[SAW DEBUG] AJAX Complete:', settings.url, 'Status:', xhr.status);
        });
    }
    
    // ============================================
    // 7. FETCH WRAPPER
    // ============================================
    
    const originalFetch = window.fetch;
    window.fetch = async function(...args) {
        const url = args[0]?.url || args[0];
        console.log('[SAW DEBUG] Fetch start:', url);
        
        try {
            const response = await originalFetch.apply(this, args);
            console.log('[SAW DEBUG] Fetch done:', url, 'Status:', response.status);
            return response;
        } catch (error) {
            console.error('[SAW DEBUG] Fetch error:', url, error);
            alert('FETCH ERROR!\n\nURL: ' + url + '\nError: ' + error.message);
            throw error;
        }
    };
    
    // ============================================
    // 8. VISIBILITY CHANGE LOG
    // ============================================
    
    document.addEventListener('visibilitychange', function() {
        console.log('[SAW DEBUG] Visibility:', document.visibilityState, 'at', new Date().toLocaleTimeString());
    });
    
    // ============================================
    // DEBUG INFO NA OBRAZOVCE
    // ============================================
    
    // Přidej malý debug panel
    const debugPanel = document.createElement('div');
    debugPanel.id = 'saw-debug-panel';
    debugPanel.style.cssText = 'position:fixed;bottom:0;left:0;right:0;background:rgba(0,0,0,0.8);color:#0f0;font-family:monospace;font-size:10px;padding:4px 8px;z-index:999999;max-height:80px;overflow:auto;';
    debugPanel.innerHTML = 'SAW Debug Active | Tap to see status';
    document.body?.appendChild(debugPanel);
    
    debugPanel.addEventListener('click', function() {
        const status = {
            bodyLength: document.body?.innerHTML?.length,
            online: navigator.onLine,
            sw: navigator.serviceWorker?.controller ? 'active' : 'none',
            url: location.href,
            errors: window._sawDebugErrors?.length || 0
        };
        alert('DEBUG STATUS:\n\n' + JSON.stringify(status, null, 2));
    });
    
    // Sbírej errory
    window._sawDebugErrors = [];
    
})();