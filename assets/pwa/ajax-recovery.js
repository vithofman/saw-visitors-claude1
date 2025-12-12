/**
 * SAW AJAX Recovery
 * 
 * Zachytává selhané AJAX requesty (expirovaný nonce, session timeout)
 * a automaticky obnovuje stránku.
 * 
 * @version 1.0.0
 */
(function() {
    'use strict';
    
    console.log('[AJAX Recovery] Initialized');
    
    // Počet po sobě jdoucích AJAX chyb
    let consecutiveErrors = 0;
    const MAX_ERRORS_BEFORE_REFRESH = 2;
    
    // Flag zda už probíhá refresh
    let isRefreshing = false;
    
    // ============================================
    // JQUERY AJAX ERROR HANDLER
    // ============================================
    
    if (window.jQuery) {
        jQuery(document).ajaxError(function(event, xhr, settings, thrownError) {
            console.log('[AJAX Recovery] AJAX Error:', {
                url: settings.url,
                status: xhr.status,
                statusText: xhr.statusText,
                error: thrownError
            });
            
            // Ignoruj zrušené requesty
            if (xhr.statusText === 'abort') {
                return;
            }
            
            // Zkontroluj typ chyby
            if (isNonceError(xhr) || isSessionError(xhr)) {
                console.log('[AJAX Recovery] Session/Nonce expired detected');
                handleExpiredSession();
                return;
            }
            
            // Obecná chyba
            consecutiveErrors++;
            
            if (consecutiveErrors >= MAX_ERRORS_BEFORE_REFRESH) {
                console.log('[AJAX Recovery] Too many consecutive errors, refreshing...');
                handleExpiredSession();
            }
        });
        
        // Reset error count při úspěšném AJAX
        jQuery(document).ajaxSuccess(function() {
            consecutiveErrors = 0;
        });
    }
    
    // ============================================
    // FETCH API WRAPPER
    // ============================================
    
    // Přepiš globální fetch pro zachycení chyb
    const originalFetch = window.fetch;
    window.fetch = async function(...args) {
        try {
            const response = await originalFetch.apply(this, args);
            
            // Kontrola response
            if (!response.ok) {
                const url = args[0]?.url || args[0];
                
                // Pokud je to AJAX endpoint a vrátil chybu
                if (isAjaxUrl(url) && (response.status === 403 || response.status === 401)) {
                    console.log('[AJAX Recovery] Fetch auth error:', response.status);
                    
                    // Zkus přečíst response body
                    const clone = response.clone();
                    try {
                        const data = await clone.json();
                        if (isNonceErrorResponse(data)) {
                            handleExpiredSession();
                        }
                    } catch (e) {
                        // Není JSON, ignoruj
                    }
                }
            } else {
                // Úspěch - reset error count
                consecutiveErrors = 0;
            }
            
            return response;
        } catch (error) {
            consecutiveErrors++;
            console.log('[AJAX Recovery] Fetch error:', error);
            
            if (consecutiveErrors >= MAX_ERRORS_BEFORE_REFRESH) {
                handleExpiredSession();
            }
            
            throw error;
        }
    };
    
    // ============================================
    // HELPER FUNCTIONS
    // ============================================
    
    function isAjaxUrl(url) {
        if (!url) return false;
        const urlStr = url.toString();
        return urlStr.includes('admin-ajax.php') || 
               urlStr.includes('wp-json') ||
               urlStr.includes('action=');
    }
    
    function isNonceError(xhr) {
        // WordPress typicky vrací -1 nebo specifickou chybu při neplatném nonce
        if (xhr.status === 403 || xhr.status === 401) {
            return true;
        }
        
        // Zkontroluj response text
        const responseText = xhr.responseText || '';
        
        if (responseText === '-1' || responseText === '0') {
            return true;
        }
        
        // Zkus parsovat jako JSON
        try {
            const data = JSON.parse(responseText);
            return isNonceErrorResponse(data);
        } catch (e) {
            return false;
        }
    }
    
    function isNonceErrorResponse(data) {
        if (!data) return false;
        
        // WordPress REST API error
        if (data.code === 'rest_cookie_invalid_nonce' || 
            data.code === 'rest_forbidden') {
            return true;
        }
        
        // Custom SAW error responses
        if (data.success === false) {
            const msg = (data.data?.message || '').toLowerCase();
            if (msg.includes('nonce') || 
                msg.includes('session') || 
                msg.includes('expired') ||
                msg.includes('unauthorized') ||
                msg.includes('not logged in')) {
                return true;
            }
        }
        
        return false;
    }
    
    function isSessionError(xhr) {
        // Redirect na login stránku
        const responseText = xhr.responseText || '';
        if (responseText.includes('wp-login.php') || 
            responseText.includes('/login/')) {
            return true;
        }
        
        return false;
    }
    
    // ============================================
    // RECOVERY ACTIONS
    // ============================================
    
    function handleExpiredSession() {
        if (isRefreshing) return;
        isRefreshing = true;
        
        console.log('[AJAX Recovery] Handling expired session...');
        
        // Zobraz uživateli info
        showRefreshNotification();
        
        // Clear SW cache
        if (navigator.serviceWorker && navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage('clearCache');
        }
        
        // Refresh po malém delay (aby uživatel viděl notifikaci)
        setTimeout(function() {
            window.location.reload(true);
        }, 1500);
    }
    
    function showRefreshNotification() {
        // Odstraň existující
        const existing = document.getElementById('saw-ajax-recovery-notification');
        if (existing) existing.remove();
        
        const notification = document.createElement('div');
        notification.id = 'saw-ajax-recovery-notification';
        notification.innerHTML = `
            <div style="
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.7);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 999999;
            ">
                <div style="
                    background: white;
                    padding: 24px 32px;
                    border-radius: 12px;
                    text-align: center;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
                    max-width: 90%;
                ">
                    <div style="font-size: 48px; margin-bottom: 16px;">🔄</div>
                    <div style="font-size: 18px; font-weight: 600; color: #1a202c; margin-bottom: 8px;">
                        Obnovuji spojení...
                    </div>
                    <div style="font-size: 14px; color: #718096;">
                        Stránka bude za chvíli znovu načtena
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(notification);
    }
    
    // ============================================
    // VISIBILITY CHANGE - PROACTIVE CHECK
    // ============================================
    
    let lastHiddenTime = null;
    const SESSION_CHECK_THRESHOLD = 2 * 60 * 1000; // 2 minuty
    
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') {
            lastHiddenTime = Date.now();
        } else if (lastHiddenTime) {
            const hiddenDuration = Date.now() - lastHiddenTime;
            
            // Pokud byla stránka skrytá déle než 2 minuty, zkontroluj session
            if (hiddenDuration > SESSION_CHECK_THRESHOLD) {
                console.log('[AJAX Recovery] Page was hidden for', Math.round(hiddenDuration/1000), 'seconds, checking session...');
                checkSessionBeforeInteraction();
            }
            
            lastHiddenTime = null;
        }
    });
    
    /**
     * Proaktivní kontrola session PŘED tím, než uživatel něco udělá
     */
    function checkSessionBeforeInteraction() {
        // Tichý AJAX request pro kontrolu
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/wp-admin/admin-ajax.php?action=saw_heartbeat', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.timeout = 5000;
        
        xhr.onload = function() {
            if (xhr.status !== 200) {
                console.log('[AJAX Recovery] Session check failed, will refresh on interaction');
                // Nerefreshuj hned - počkej na první interakci
                markSessionExpired();
            } else {
                console.log('[AJAX Recovery] Session still valid');
            }
        };
        
        xhr.onerror = function() {
            console.log('[AJAX Recovery] Session check error');
            markSessionExpired();
        };
        
        xhr.send();
    }
    
    let sessionMarkedExpired = false;
    
    function markSessionExpired() {
        sessionMarkedExpired = true;
        
        // Při první interakci automaticky refresh
        const interactionHandler = function() {
            if (sessionMarkedExpired) {
                handleExpiredSession();
            }
        };
        
        // Zachyť první klik/tap
        document.addEventListener('click', interactionHandler, { once: true, capture: true });
        document.addEventListener('touchstart', interactionHandler, { once: true, capture: true });
    }
    
    // ============================================
    // DEBUG API
    // ============================================
    
    window.SAW_AjaxRecovery = {
        checkSession: checkSessionBeforeInteraction,
        forceRefresh: handleExpiredSession,
        getStatus: function() {
            return {
                consecutiveErrors: consecutiveErrors,
                isRefreshing: isRefreshing,
                sessionMarkedExpired: sessionMarkedExpired,
                lastHiddenTime: lastHiddenTime ? new Date(lastHiddenTime).toISOString() : null
            };
        }
    };
    
})();