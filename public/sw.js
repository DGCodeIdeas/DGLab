/**
 * DGLab PWA - Service Worker
 * 
 * Provides offline support, caching strategies, and background sync.
 */

const CACHE_VERSION = '1.0.0';
const STATIC_CACHE = `dglab-static-${CACHE_VERSION}`;
const DYNAMIC_CACHE = `dglab-dynamic-${CACHE_VERSION}`;
const API_CACHE = `dglab-api-${CACHE_VERSION}`;

// App shell assets to cache on install
const APP_SHELL = [
    '/',
    '/services',
    '/manifest.json',
    '/assets/css/app.css',
    '/assets/js/app.js',
    '/assets/css/bootstrap.min.css',
    '/assets/js/bootstrap.bundle.min.js',
    '/assets/css/all.min.css',
    '/assets/js/jquery.min.js',
    '/offline.html'
];

// Install event - cache app shell
self.addEventListener('install', (event) => {
    console.log('[SW] Installing...');
    
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => {
                console.log('[SW] Caching app shell');
                return cache.addAll(APP_SHELL);
            })
            .then(() => {
                console.log('[SW] App shell cached');
                return self.skipWaiting();
            })
            .catch((error) => {
                console.error('[SW] Failed to cache app shell:', error);
            })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating...');
    
    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames
                        .filter((name) => {
                            return name.startsWith('dglab-') && 
                                   !name.includes(CACHE_VERSION);
                        })
                        .map((name) => {
                            console.log('[SW] Deleting old cache:', name);
                            return caches.delete(name);
                        })
                );
            })
            .then(() => {
                console.log('[SW] Activated');
                return self.clients.claim();
            })
    );
});

// Fetch event - routing and caching strategies
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }
    
    // Skip cross-origin requests
    if (url.origin !== self.location.origin) {
        return;
    }
    
    // Route-specific strategies
    
    // API requests - Network First with timeout
    if (url.pathname.startsWith('/api/')) {
        event.respondWith(networkFirstWithTimeout(request, API_CACHE, 5000));
        return;
    }
    
    // Static assets - Cache First
    if (isStaticAsset(url.pathname)) {
        event.respondWith(cacheFirst(request, STATIC_CACHE));
        return;
    }
    
    // HTML pages - Stale While Revalidate
    if (request.headers.get('Accept')?.includes('text/html')) {
        event.respondWith(staleWhileRevalidate(request, DYNAMIC_CACHE));
        return;
    }
    
    // Default - Network First
    event.respondWith(networkFirst(request, DYNAMIC_CACHE));
});

// Background Sync - for upload resume
self.addEventListener('sync', (event) => {
    if (event.tag === 'upload-resume') {
        event.waitUntil(handleUploadResume());
    }
});

// Push notification handling (preparation)
self.addEventListener('push', (event) => {
    const data = event.data?.json() ?? {};
    
    const options = {
        body: data.body || 'New notification from DGLab',
        icon: '/assets/images/icon-192x192.png',
        badge: '/assets/images/icon-72x72.png',
        tag: data.tag || 'default',
        requireInteraction: data.requireInteraction ?? false,
        actions: data.actions || [],
        data: data.data || {}
    };
    
    event.waitUntil(
        self.registration.showNotification(
            data.title || 'DGLab',
            options
        )
    );
});

// Notification click handling
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    
    const notificationData = event.notification.data;
    const action = event.action;
    
    if (action === 'open' || !action) {
        event.waitUntil(
            clients.openWindow(notificationData.url || '/')
        );
    }
});

// Message handling from client
self.addEventListener('message', (event) => {
    if (event.data === 'skipWaiting') {
        self.skipWaiting();
    }
});

// ==================== Caching Strategies ====================

/**
 * Cache First - Serve from cache, fallback to network
 */
async function cacheFirst(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);
    
    if (cached) {
        return cached;
    }
    
    try {
        const response = await fetch(request);
        
        if (response.ok) {
            cache.put(request, response.clone());
        }
        
        return response;
    } catch (error) {
        // Return offline fallback for HTML
        if (request.headers.get('Accept')?.includes('text/html')) {
            return caches.match('/offline.html');
        }
        
        throw error;
    }
}

/**
 * Network First - Try network, fallback to cache
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
        const cache = await caches.open(cacheName);
        const cached = await cache.match(request);
        
        if (cached) {
            return cached;
        }
        
        throw error;
    }
}

/**
 * Network First with Timeout - Fail fast to cache
 */
async function networkFirstWithTimeout(request, cacheName, timeout) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);
    
    const networkPromise = fetch(request)
        .then((response) => {
            if (response.ok) {
                cache.put(request, response.clone());
            }
            return response;
        })
        .catch(() => cached);
    
    const timeoutPromise = new Promise((resolve) => {
        setTimeout(() => resolve(cached), timeout);
    });
    
    return Promise.race([networkPromise, timeoutPromise])
        .then((response) => response || cached);
}

/**
 * Stale While Revalidate - Serve cache, update in background
 */
async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);
    
    const networkFetch = fetch(request)
        .then((response) => {
            if (response.ok) {
                cache.put(request, response.clone());
            }
            return response;
        })
        .catch(() => null);
    
    // Return cached immediately if available
    if (cached) {
        // Update cache in background
        networkFetch;
        return cached;
    }
    
    // Wait for network if no cache
    const networkResponse = await networkFetch;
    
    if (networkResponse) {
        return networkResponse;
    }
    
    // Return offline page for HTML
    if (request.headers.get('Accept')?.includes('text/html')) {
        return caches.match('/offline.html');
    }
    
    throw new Error('Network error and no cache available');
}

// ==================== Helpers ====================

/**
 * Check if path is a static asset
 */
function isStaticAsset(pathname) {
    const extensions = ['.css', '.js', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.woff', '.woff2', '.ttf', '.otf'];
    return extensions.some((ext) => pathname.endsWith(ext));
}

/**
 * Handle upload resume (background sync)
 */
async function handleUploadResume() {
    // Get pending uploads from IndexedDB
    const pendingUploads = await getPendingUploads();
    
    for (const upload of pendingUploads) {
        try {
            await resumeUpload(upload);
        } catch (error) {
            console.error('[SW] Failed to resume upload:', error);
        }
    }
}

/**
 * Get pending uploads from IndexedDB
 */
async function getPendingUploads() {
    // Placeholder - implement with IndexedDB
    return [];
}

/**
 * Resume a pending upload
 */
async function resumeUpload(upload) {
    // Placeholder - implement upload resume logic
    console.log('[SW] Resuming upload:', upload);
}

// ==================== Console Logging ====================

console.log('[SW] Service Worker loaded, version:', CACHE_VERSION);
