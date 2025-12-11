/**
 * SAW Visitors - Service Worker
 * 
 * Cache strategie:
 * - Static assets (CSS, JS, fonts): Cache First
 * - HTML pages: Network First
 * - API/AJAX: Network Only (NIKDY necachovat)
 * - Images: Cache First with fallback
 * 
 * @version 1.1.0
 * @fix AJAX requesty nyní procházejí bez cache
 */

// ============================================
// KONFIGURACE
// ============================================

const CACHE_VERSION = 'v2';
const CACHE_STATIC = `saw-static-${CACHE_VERSION}`;
const CACHE_PAGES = `saw-pages-${CACHE_VERSION}`;
const CACHE_IMAGES = `saw-images-${CACHE_VERSION}`;

// Soubory k precache při instalaci
const PRECACHE_ASSETS = [
    '/wp-content/plugins/saw-visitors/assets/pwa/offline.html',
    '/wp-content/plugins/saw-visitors/assets/pwa/icons/icon-192x192.png',
    '/wp-content/plugins/saw-visitors/assets/css/foundation/variables.css',
    '/wp-content/plugins/saw-visitors/assets/css/foundation/reset.css',
    '/wp-content/plugins/saw-visitors/assets/css/components/base-components.css'
];

// URL patterns které NIKDY necachovat
const NEVER_CACHE_PATTERNS = [
    /\/wp-admin\//,
    /\/wp-json\//,
    /admin-ajax\.php/,
    /\?wc-ajax=/,
    /\/wp-login\.php/,
    /\/xmlrpc\.php/,
    /\?.*action=/,
    /\?.*nonce=/
];

// URL patterns pro SAW aplikaci (Network First - pouze HTML)
const APP_PATTERNS = [
    /\/terminal\//,
    /\/admin\//,
    /\/manager\//,
    /\/visitor-invitation\//,
    /\/login\//,
    /\/reset-password\//,
    /\/set-password\//
];

// ============================================
// INSTALL EVENT
// ============================================

self.addEventListener('install', (event) => {
    console.log('[SW] Installing Service Worker v' + CACHE_VERSION);
    
    event.waitUntil(
        caches.open(CACHE_STATIC)
            .then((cache) => {
                console.log('[SW] Precaching static assets...');
                return cache.addAll(PRECACHE_ASSETS);
            })
            .then(() => {
                console.log('[SW] Precache complete');
                return self.skipWaiting();
            })
            .catch((error) => {
                console.error('[SW] Precache failed:', error);
            })
    );
});

// ============================================
// ACTIVATE EVENT
// ============================================

self.addEventListener('activate', (event) => {
    console.log('[SW] Activating Service Worker v' + CACHE_VERSION);
    
    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames
                        .filter((cacheName) => {
                            return cacheName.startsWith('saw-') && 
                                   !cacheName.endsWith(CACHE_VERSION);
                        })
                        .map((cacheName) => {
                            console.log('[SW] Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        })
                );
            })
            .then(() => {
                console.log('[SW] Claiming clients...');
                return self.clients.claim();
            })
    );
});

// ============================================
// FETCH EVENT - FIXED VERSION
// ============================================

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    
    // 1. Ignoruj non-GET requesty
    if (request.method !== 'GET') {
        return;
    }
    
    // 2. Ignoruj cross-origin requesty
    if (url.origin !== location.origin) {
        return;
    }
    
    // 3. CRITICAL FIX: Nikdy nezachytávej AJAX/XHR requesty
    if (isAjaxRequest(request)) {
        console.log('[SW] Skipping AJAX request:', url.pathname);
        return;
    }
    
    // 4. Nikdy necachuj WordPress admin a API
    if (shouldNeverCache(url.pathname + url.search)) {
        return;
    }
    
    // 5. Rozhodnutí o strategii podle typu obsahu
    if (isStaticAsset(url.pathname)) {
        event.respondWith(cacheFirst(request, CACHE_STATIC));
    } else if (isImage(url.pathname)) {
        event.respondWith(cacheFirst(request, CACHE_IMAGES));
    } else if (isAppPage(url.pathname) && isHtmlRequest(request)) {
        // CRITICAL: Pouze pro HTML page load, ne AJAX
        event.respondWith(networkFirst(request, CACHE_PAGES));
    }
    // Ostatní requesty procházejí normálně bez zásahu SW
});

// ============================================
// CACHE STRATEGIES
// ============================================

/**
 * Cache First strategie
 */
async function cacheFirst(request, cacheName) {
    const cachedResponse = await caches.match(request);
    
    if (cachedResponse) {
        return cachedResponse;
    }
    
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.error('[SW] Cache First fetch failed:', error);
        return new Response('', { status: 404 });
    }
}

/**
 * Network First strategie
 */
async function networkFirst(request, cacheName) {
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.log('[SW] Network failed, trying cache:', request.url);
        
        const cachedResponse = await caches.match(request);
        
        if (cachedResponse) {
            return cachedResponse;
        }
        
        if (isHtmlRequest(request)) {
            return caches.match('/wp-content/plugins/saw-visitors/assets/pwa/offline.html');
        }
        
        return new Response('Offline', { status: 503 });
    }
}

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Detekce AJAX/XHR requestů
 * CRITICAL: Tyto requesty NIKDY necachovat
 */
function isAjaxRequest(request) {
    // Check X-Requested-With header (jQuery AJAX)
    if (request.headers.get('X-Requested-With') === 'XMLHttpRequest') {
        return true;
    }
    
    // Check Accept header for JSON
    const accept = request.headers.get('Accept') || '';
    if (accept.includes('application/json') && !accept.includes('text/html')) {
        return true;
    }
    
    // Check for fetch API with JSON
    if (request.headers.get('Content-Type')?.includes('application/json')) {
        return true;
    }
    
    return false;
}

/**
 * Detekce HTML page requestů
 */
function isHtmlRequest(request) {
    const accept = request.headers.get('Accept') || '';
    return accept.includes('text/html');
}

function shouldNeverCache(fullPath) {
    return NEVER_CACHE_PATTERNS.some(pattern => pattern.test(fullPath));
}

function isStaticAsset(pathname) {
    return /\.(css|js|woff2?|ttf|eot)(\?.*)?$/i.test(pathname);
}

function isImage(pathname) {
    return /\.(png|jpg|jpeg|gif|svg|webp|ico)(\?.*)?$/i.test(pathname);
}

function isAppPage(pathname) {
    return APP_PATTERNS.some(pattern => pattern.test(pathname));
}

// ============================================
// MESSAGE HANDLER
// ============================================

self.addEventListener('message', (event) => {
    if (event.data === 'skipWaiting') {
        self.skipWaiting();
    }
    
    if (event.data === 'clearCache') {
        event.waitUntil(
            caches.keys().then((cacheNames) => {
                return Promise.all(
                    cacheNames
                        .filter((name) => name.startsWith('saw-'))
                        .map((name) => caches.delete(name))
                );
            })
        );
    }
});

console.log('[SW] Service Worker v' + CACHE_VERSION + ' loaded');