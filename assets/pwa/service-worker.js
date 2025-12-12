/**
 * SAW Visitors - Service Worker
 * 
 * Cache strategie:
 * - Static assets (CSS, JS, fonts): Cache First
 * - HTML pages: Network Only (NIKDY necachovat - prevence zombie stavu)
 * - API/AJAX: Network Only (NIKDY necachovat)
 * - Images: Cache First with fallback
 * 
 * @package SAW_Visitors
 * @version 3.0.0
 */

// ============================================
// KONFIGURACE
// ============================================

var CACHE_VERSION = 'v4';
var CACHE_STATIC = 'saw-static-' + CACHE_VERSION;
var CACHE_IMAGES = 'saw-images-' + CACHE_VERSION;

// Soubory k precache při instalaci
var PRECACHE_ASSETS = [
    '/wp-content/plugins/saw-visitors/assets/pwa/offline.html',
    '/wp-content/plugins/saw-visitors/assets/pwa/icons/icon-192x192.png'
];

// URL patterns které NIKDY necachovat
var NEVER_CACHE_PATTERNS = [
    /\/wp-admin\//,
    /\/wp-json\//,
    /admin-ajax\.php/,
    /\?wc-ajax=/,
    /\/wp-login\.php/,
    /\/xmlrpc\.php/,
    /\?.*action=/,
    /\?.*nonce=/,
    /\?.*logout/,
    /\?.*login/
];

// ============================================
// INSTALL EVENT
// ============================================

self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(CACHE_STATIC)
            .then(function(cache) {
                return cache.addAll(PRECACHE_ASSETS);
            })
            .then(function() {
                return self.skipWaiting();
            })
            .catch(function() {
                // Precache failed - continue anyway
                return self.skipWaiting();
            })
    );
});

// ============================================
// ACTIVATE EVENT
// ============================================

self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys()
            .then(function(cacheNames) {
                return Promise.all(
                    cacheNames
                        .filter(function(cacheName) {
                            return cacheName.indexOf('saw-') === 0 && 
                                   cacheName.indexOf(CACHE_VERSION) === -1;
                        })
                        .map(function(cacheName) {
                            return caches.delete(cacheName);
                        })
                );
            })
            .then(function() {
                return self.clients.claim();
            })
    );
});

// ============================================
// FETCH EVENT
// ============================================

self.addEventListener('fetch', function(event) {
    var request = event.request;
    var url = new URL(request.url);
    
    // 1. Ignoruj non-GET requesty
    if (request.method !== 'GET') {
        return;
    }
    
    // 2. Ignoruj cross-origin requesty
    if (url.origin !== location.origin) {
        return;
    }
    
    // 3. CRITICAL: Nikdy nezachytávej AJAX/XHR requesty
    if (isAjaxRequest(request)) {
        return;
    }
    
    // 4. Nikdy necachuj WordPress admin a API
    if (shouldNeverCache(url.pathname + url.search)) {
        return;
    }
    
    // 5. CRITICAL: HTML stránky NIKDY necachovat (prevence zombie stavu)
    if (isHtmlRequest(request)) {
        // Network only - při offline zobraz offline stránku
        event.respondWith(
            fetch(request).catch(function() {
                return caches.match('/wp-content/plugins/saw-visitors/assets/pwa/offline.html');
            })
        );
        return;
    }
    
    // 6. Rozhodnutí o strategii podle typu obsahu
    if (isStaticAsset(url.pathname)) {
        event.respondWith(cacheFirst(request, CACHE_STATIC));
    } else if (isImage(url.pathname)) {
        event.respondWith(cacheFirst(request, CACHE_IMAGES));
    }
    // Ostatní requesty procházejí normálně bez zásahu SW
});

// ============================================
// CACHE STRATEGIES
// ============================================

/**
 * Cache First strategie (pouze pro static assets a images)
 */
function cacheFirst(request, cacheName) {
    return caches.match(request).then(function(cachedResponse) {
        if (cachedResponse) {
            return cachedResponse;
        }
        
        return fetch(request).then(function(networkResponse) {
            if (networkResponse.ok) {
                var responseClone = networkResponse.clone();
                caches.open(cacheName).then(function(cache) {
                    cache.put(request, responseClone);
                });
            }
            return networkResponse;
        }).catch(function() {
            return new Response('', { status: 404 });
        });
    });
}

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Detekce AJAX/XHR requestů
 */
function isAjaxRequest(request) {
    // Check X-Requested-With header (jQuery AJAX)
    if (request.headers.get('X-Requested-With') === 'XMLHttpRequest') {
        return true;
    }
    
    // Check Accept header for JSON
    var accept = request.headers.get('Accept') || '';
    if (accept.indexOf('application/json') !== -1 && accept.indexOf('text/html') === -1) {
        return true;
    }
    
    // Check Content-Type for JSON
    var contentType = request.headers.get('Content-Type') || '';
    if (contentType.indexOf('application/json') !== -1) {
        return true;
    }
    
    return false;
}

/**
 * Detekce HTML page requestů
 */
function isHtmlRequest(request) {
    var accept = request.headers.get('Accept') || '';
    return accept.indexOf('text/html') !== -1;
}

function shouldNeverCache(fullPath) {
    return NEVER_CACHE_PATTERNS.some(function(pattern) {
        return pattern.test(fullPath);
    });
}

function isStaticAsset(pathname) {
    return /\.(css|js|woff2?|ttf|eot)(\?.*)?$/i.test(pathname);
}

function isImage(pathname) {
    return /\.(png|jpg|jpeg|gif|svg|webp|ico)(\?.*)?$/i.test(pathname);
}

// ============================================
// MESSAGE HANDLER
// ============================================

self.addEventListener('message', function(event) {
    if (event.data === 'skipWaiting') {
        self.skipWaiting();
    }
    
    if (event.data === 'clearCache') {
        event.waitUntil(
            caches.keys().then(function(cacheNames) {
                return Promise.all(
                    cacheNames
                        .filter(function(name) {
                            return name.indexOf('saw-') === 0;
                        })
                        .map(function(name) {
                            return caches.delete(name);
                        })
                );
            })
        );
    }
    
    if (event.data === 'refreshClients') {
        self.clients.matchAll().then(function(clients) {
            clients.forEach(function(client) {
                client.postMessage('refresh');
            });
        });
    }
});