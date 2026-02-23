<?php
/**
 * DGLab PWA - PWA Controller
 * 
 * Handles PWA manifest and service worker.
 * 
 * @package DGLab\Controllers
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Controllers;

use DGLab\Core\Controller;

/**
 * PwaController Class
 * 
 * Controller for PWA resources.
 */
class PwaController extends Controller
{
    /**
     * Generate and serve manifest.json
     * 
     * @return void
     */
    public function manifest(): void
    {
        $pwaConfig = $this->config['pwa'] ?? [];
        
        $manifest = [
            'name'             => $pwaConfig['name'] ?? APP_NAME,
            'short_name'       => $pwaConfig['short_name'] ?? 'DGLab',
            'description'      => $pwaConfig['description'] ?? 'Digital Lab - Web Tools Platform',
            'start_url'        => $pwaConfig['start_url'] ?? '/',
            'display'          => $pwaConfig['display'] ?? 'standalone',
            'background_color' => $pwaConfig['background_color'] ?? '#ffffff',
            'theme_color'      => $pwaConfig['theme_color'] ?? '#4f46e5',
            'orientation'      => $pwaConfig['orientation'] ?? 'any',
            'scope'            => '/',
            'icons'            => $pwaConfig['icons'] ?? [],
        ];
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($manifest, JSON_PRETTY_PRINT);
    }

    /**
     * Generate and serve service worker
     * 
     * @return void
     */
    public function serviceWorker(): void
    {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: /');
        
        $cacheName = 'dglab-v' . APP_VERSION;
        $baseUrl = $this->config['app']['base_url'] ?? '';
        
        echo <<<JS
/**
 * DGLab PWA - Service Worker
 * @version {$cacheName}
 */

const CACHE_NAME = '{$cacheName}';
const STATIC_ASSETS = [
    '{$baseUrl}/',
    '{$baseUrl}/offline',
    '{$baseUrl}/assets/css/app.css',
    '{$baseUrl}/assets/js/app.js',
];

// Install event - cache static assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => {
                return self.skipWaiting();
            })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => {
            return self.clients.claim();
        })
    );
});

// Fetch event - serve from cache or network
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }
    
    // Skip API requests
    if (url.pathname.startsWith('/api/')) {
        return;
    }
    
    // Skip upload/processing endpoints
    if (url.pathname.includes('/upload/') || url.pathname.includes('/process')) {
        return;
    }
    
    // Strategy: Cache First, then Network
    event.respondWith(
        caches.match(request).then((cachedResponse) => {
            if (cachedResponse) {
                // Return cached response and update cache in background
                fetch(request)
                    .then((networkResponse) => {
                        if (networkResponse.ok) {
                            caches.open(CACHE_NAME).then((cache) => {
                                cache.put(request, networkResponse);
                            });
                        }
                    })
                    .catch(() => {
                        // Network failed, but we have cached response
                    });
                
                return cachedResponse;
            }
            
            // Not in cache, fetch from network
            return fetch(request)
                .then((networkResponse) => {
                    // Cache successful responses
                    if (networkResponse.ok && networkResponse.status === 200) {
                        const responseClone = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, responseClone);
                        });
                    }
                    
                    return networkResponse;
                })
                .catch(() => {
                    // Network failed, serve offline page for navigation requests
                    if (request.mode === 'navigate') {
                        return caches.match('{$baseUrl}/offline');
                    }
                    
                    return new Response('Network error', {
                        status: 408,
                        headers: { 'Content-Type': 'text/plain' }
                    });
                });
        })
    );
});

// Message event - handle messages from client
self.addEventListener('message', (event) => {
    if (event.data === 'skipWaiting') {
        self.skipWaiting();
    }
});
JS;
    }

    /**
     * Offline page
     * 
     * @return void
     */
    public function offline(): void
    {
        $this->render('pwa/offline', [
            'title' => 'Offline',
        ], null);
    }
}
