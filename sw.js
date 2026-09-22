/**
 * Main Pintar - Service Worker
 * Offline-first caching for better multiplatform experience.
 * Semua path RELATIF terhadap lokasi sw.js, jadi aman di-host di
 * domain root maupun subfolder (mis. /main-pintar/).
 */
const CACHE_NAME = 'main-pintar-v1.1.4';

// Path relatif di-resolve terhadap URL sw.js (root aplikasi)
const STATIC_ASSETS = [
    './',
    './index.php',
    './kuis.php',
    './join.php',
    './leaderboard.php',
    './offline.html',
    './assets/css/style.css',
    './assets/css/responsive.css',
    './assets/js/quiz.js',
    './assets/img/logo-kukar.png',
    './assets/img/icon-arsip.svg',
    './manifest.json'
];

// Path prefix aplikasi (mis. '/' atau '/main-pintar/') dari scope registrasi
const BASE = new URL(self.registration.scope).pathname;

// Install - cache static assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS).catch((err) => {
                console.log('[SW] Some assets failed to cache:', err);
            });
        })
    );
    self.skipWaiting();
});

// Activate - clean old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames
                    .filter((name) => name !== CACHE_NAME)
                    .map((name) => caches.delete(name))
            );
        })
    );
    self.clients.claim();
});

// Fetch - network first for API, cache first for static
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET requests
    if (request.method !== 'GET') return;

    // Skip cross-origin requests
    if (url.origin !== location.origin) return;

    // API & halaman dinamis PHP lain - selalu network (jangan pernah cache:
    // berisi data sesi/soal yang berubah tiap detik)
    if (
        url.pathname.startsWith(BASE + 'api/') ||
        url.pathname.startsWith(BASE + 'admin/') ||
        url.pathname.startsWith(BASE + 'auth/') ||
        url.pathname === BASE + 'main.php' ||
        url.pathname === BASE + 'hasil.php'
    ) {
        return;
    }

    // Static assets - cache first, fallback to network
    if (
        url.pathname.startsWith(BASE + 'assets/') ||
        url.pathname === BASE + 'manifest.json' ||
        url.pathname.endsWith('.css') ||
        url.pathname.endsWith('.js') ||
        url.pathname.endsWith('.png') ||
        url.pathname.endsWith('.svg')
    ) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) return cached;
                return fetch(request).then((response) => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                    }
                    return response;
                });
            })
        );
        return;
    }

    // HTML pages - network first for fresh content, fallback to cache/offline
    event.respondWith(
        fetch(request)
            .then((response) => {
                if (response.ok) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                }
                return response;
            })
            .catch(() =>
                caches.match(request).then((cached) => cached || caches.match('./offline.html'))
            )
    );
});

// Background sync for pending answers (if supported)
self.addEventListener('sync', (event) => {
    if (event.tag === 'pending-answers') {
        event.waitUntil(syncPendingAnswers());
    }
});

async function syncPendingAnswers() {
    // Implementation for syncing offline answers when back online
    console.log('[SW] Syncing pending answers...');
}

// Push notification support (for future)
self.addEventListener('push', (event) => {
    if (!event.data) return;
    const data = event.data.json();
    const options = {
        body: data.body || 'Notifikasi baru dari Main Pintar',
        icon: './assets/img/logo-kukar.png',
        badge: './assets/img/icon-arsip.svg',
        vibrate: [100, 50, 100],
        data: { url: data.url || './' },
        actions: [
            { action: 'open', title: 'Buka' },
            { action: 'close', title: 'Tutup' }
        ]
    };
    event.waitUntil(self.registration.showNotification(data.title || 'Main Pintar', options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    if (event.action === 'open' || !event.action) {
        event.waitUntil(clients.openWindow(event.notification.data.url || './'));
    }
});
