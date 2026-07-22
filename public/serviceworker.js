// Deliberately no response caching: every page and API response can carry
// decrypted secrets, so the vault must never be readable from cache storage.
// The service worker exists for installability (PWA) and web push.

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    // Drop any caches a previous service worker version may have created.
    event.waitUntil(
        caches.keys().then((names) => Promise.all(names.map((name) => caches.delete(name)))),
    );
});

self.addEventListener('fetch', (event) => {
    event.respondWith(fetch(event.request));
});

// --- Web Push ---
self.addEventListener('push', (event) => {
    let payload = {};
    try {
        payload = event.data ? event.data.json() : {};
    } catch {
        payload = { title: 'Vault', body: event.data ? event.data.text() : '' };
    }

    const title = payload.title || 'Vault';
    const options = {
        body: payload.body || '',
        icon: payload.icon || '/icons/icon-192.png',
        badge: payload.badge || '/icons/icon-192.png',
        tag: payload.tag || undefined,
        data: { url: payload.url || (payload.data && payload.data.url) || '/vault' },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = (event.notification.data && event.notification.data.url) || '/vault';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if ('focus' in client) {
                    client.navigate(targetUrl);
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        }),
    );
});
