/**
 * SAW AJAX Recovery
 * 
 * Zachytává selhané AJAX requesty (expirovaný nonce, session timeout)
 * a automaticky obnovuje stránku.
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 */
(function() {
    'use strict';
    
    // Počet po sobě jdoucích AJAX chyb
    var consecutiveErrors = 0;
    var MAX_ERRORS_BEFORE_REFRESH = 3;
    
    // Flag zda už probíhá refresh
    var isRefreshing = false;
    
    // ============================================
    // JQUERY AJAX ERROR HANDLER
    // ============================================
    
    if (window.jQuery) {
        jQuery(document).ajaxError(function(event, xhr, settings, thrownError) {
            // Ignoruj zrušené requesty
            if (xhr.statusText === 'abort') {
                return;
            }
            
            // Zkontroluj typ chyby
            if (isNonceError(xhr) || isSessionError(xhr)) {
                handleExpiredSession();
                return;
            }
            
            // Obecná chyba
            consecutiveErrors++;
            
            if (consecutiveErrors >= MAX_ERRORS_BEFORE_REFRESH) {
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
    
    var originalFetch = window.fetch;
    
    window.fetch = function() {
        var args = arguments;
        
        return originalFetch.apply(this, args).then(function(response) {
            if (!response.ok) {
                var url = args[0] && args[0].url ? args[0].url : args[0];
                
                // Pokud je to AJAX endpoint a vrátil auth chybu
                if (isAjaxUrl(url) && (response.status === 403 || response.status === 401)) {
                    // Zkus přečíst response body
                    var clone = response.clone();
                    clone.json().then(function(data) {
                        if (isNonceErrorResponse(data)) {
                            handleExpiredSession();
                        }
                    }).catch(function() {
                        // Není JSON, ignoruj
                    });
                }
            } else {
                // Úspěch - reset error count
                consecutiveErrors = 0;
            }
            
            return response;
        }).catch(function(error) {
            consecutiveErrors++;
            
            if (consecutiveErrors >= MAX_ERRORS_BEFORE_REFRESH) {
                handleExpiredSession();
            }
            
            throw error;
        });
    };
    
    // ============================================
    // HELPER FUNCTIONS
    // ============================================
    
    function isAjaxUrl(url) {
        if (!url) return false;
        var urlStr = url.toString();
        return urlStr.indexOf('admin-ajax.php') !== -1 || 
               urlStr.indexOf('wp-json') !== -1 ||
               urlStr.indexOf('action=') !== -1;
    }
    
    function isNonceError(xhr) {
        // WordPress typicky vrací -1 nebo specifickou chybu při neplatném nonce
        if (xhr.status === 403 || xhr.status === 401) {
            return true;
        }
        
        // Zkontroluj response text
        var responseText = xhr.responseText || '';
        
        if (responseText === '-1' || responseText === '0') {
            return true;
        }
        
        // Zkus parsovat jako JSON
        try {
            var data = JSON.parse(responseText);
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
        if (data.success === false && data.data && data.data.message) {
            var msg = data.data.message.toLowerCase();
            if (msg.indexOf('nonce') !== -1 || 
                msg.indexOf('session') !== -1 || 
                msg.indexOf('expired') !== -1 ||
                msg.indexOf('unauthorized') !== -1 ||
                msg.indexOf('not logged in') !== -1) {
                return true;
            }
        }
        
        return false;
    }
    
    function isSessionError(xhr) {
        // Redirect na login stránku
        var responseText = xhr.responseText || '';
        if (responseText.indexOf('wp-login.php') !== -1 || 
            responseText.indexOf('/login/') !== -1) {
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
        
        // Zobraz uživateli info
        showRefreshNotification();
        
        // Clear SW cache
        if (navigator.serviceWorker && navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage('clearCache');
        }
        
        // Refresh - použij krátký delay jen pro zobrazení notifikace
        setTimeout(function() {
            window.location.reload(true);
        }, 800);
    }
    
    function showRefreshNotification() {
        // Odstraň existující
        var existing = document.getElementById('saw-ajax-recovery-notification');
        if (existing) existing.remove();
        
        var notification = document.createElement('div');
        notification.id = 'saw-ajax-recovery-notification';
        notification.innerHTML = 
            '<div style="' +
                'position: fixed;' +
                'top: 0;' +
                'left: 0;' +
                'right: 0;' +
                'bottom: 0;' +
                'background: rgba(0, 0, 0, 0.7);' +
                'display: flex;' +
                'align-items: center;' +
                'justify-content: center;' +
                'z-index: 999999;' +
            '">' +
                '<div style="' +
                    'background: white;' +
                    'padding: 24px 32px;' +
                    'border-radius: 12px;' +
                    'text-align: center;' +
                    'box-shadow: 0 10px 40px rgba(0,0,0,0.3);' +
                    'max-width: 90%;' +
                '">' +
                    '<div style="font-size: 48px; margin-bottom: 16px;">🔄</div>' +
                    '<div style="font-size: 18px; font-weight: 600; color: #1a202c; margin-bottom: 8px;">' +
                        'Obnovuji spojení...' +
                    '</div>' +
                    '<div style="font-size: 14px; color: #718096;">' +
                        'Stránka bude za chvíli znovu načtena' +
                    '</div>' +
                '</div>' +
            '</div>';
        
        document.body.appendChild(notification);
    }
    
})();