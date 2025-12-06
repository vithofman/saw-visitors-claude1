/**
 * SAW Visitors - Service Worker
 * 
 * Cache strategie:
 * - Static assets (CSS, JS, fonts): Cache First
 * - HTML pages: Network First
 * - API/AJAX: Network Only
 * - Images: Cache First with fallback
 * 
 * @version 1.0.0
 */

// ============================================
// KONFIGURACE
// ============================================

const CACHE_VERSION = 'v1';
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
    /\/xmlrpc\.php/
];

// URL patterns pro SAW aplikaci (Network First)
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
    console.log('[SW] Installing Service Worker...');
    
    event.waitUntil(
        caches.open(CACHE_STATIC)
            .then((cache) => {
                console.log('[SW] Precaching static assets...');
                return cache.addAll(PRECACHE_ASSETS);
            })
            .then(() => {
                console.log('[SW] Precache complete');
                // Aktivuj ihned bez čekání na zavření starých tabs
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
    console.log('[SW] Activating Service Worker...');
    
    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames
                        .filter((cacheName) => {
                            // Smaž staré cache verze
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
                // Převezmi kontrolu nad všemi otevřenými tabs
                return self.clients.claim();
            })
    );
});

// ============================================
// FETCH EVENT
// ============================================

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    
    // Ignoruj non-GET requesty
    if (request.method !== 'GET') {
        return;
    }
    
    // Ignoruj cross-origin requesty
    if (url.origin !== location.origin) {
        return;
    }
    
    // Nikdy necachuj WordPress admin a API
    if (shouldNeverCache(url.pathname)) {
        return;
    }
    
    // Rozhodnutí o strategii
    if (isStaticAsset(url.pathname)) {
        // Cache First pro static assets
        event.respondWith(cacheFirst(request, CACHE_STATIC));
    } else if (isImage(url.pathname)) {
        // Cache First pro obrázky
        event.respondWith(cacheFirst(request, CACHE_IMAGES));
    } else if (isAppPage(url.pathname)) {
        // Network First pro app stránky
        event.respondWith(networkFirst(request, CACHE_PAGES));
    }
    // Ostatní requesty jdou normálně přes síť
});

// ============================================
// CACHE STRATEGIES
// ============================================

/**
 * Cache First strategie
 * Vrátí cache pokud existuje, jinak fetch a ulož
 */
async function cacheFirst(request, cacheName) {
    const cachedResponse = await caches.match(request);
    
    if (cachedResponse) {
        return cachedResponse;
    }
    
    try {
        const networkResponse = await fetch(request);
        
        // Ulož do cache pouze úspěšné odpovědi
        if (networkResponse.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.error('[SW] Cache First fetch failed:', error);
        // Pro obrázky vrať placeholder nebo nic
        return new Response('', { status: 404 });
    }
}

/**
 * Network First strategie
 * Zkusí síť, při selhání vrátí cache nebo offline stránku
 */
async function networkFirst(request, cacheName) {
    try {
        const networkResponse = await fetch(request);
        
        // Ulož úspěšnou odpověď do cache
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
        
        // Vrať offline stránku pro HTML requesty
        if (request.headers.get('Accept')?.includes('text/html')) {
            return caches.match('/wp-content/plugins/saw-visitors/assets/pwa/offline.html');
        }
        
        return new Response('Offline', { status: 503 });
    }
}

// ============================================
// HELPER FUNCTIONS
// ============================================

function shouldNeverCache(pathname) {
    return NEVER_CACHE_PATTERNS.some(pattern => pattern.test(pathname));
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

console.log('[SW] Service Worker loaded');