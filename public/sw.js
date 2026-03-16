const CACHE_NAME = 'comere-v1';

// ── Install ──────────────────────────────────────────────────────────────────
self.addEventListener('install', (event) => {
    self.skipWaiting();
});

// ── Activate ─────────────────────────────────────────────────────────────────
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => key !== CACHE_NAME)
                    .map((key) => caches.delete(key))
            )
        )
    );
    self.clients.claim();
});

// ── Push ─────────────────────────────────────────────────────────────────────
self.addEventListener('push', (event) => {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch {
        data = { title: 'Comere', body: event.data ? event.data.text() : '' };
    }

    const title = data.title || 'Comere';
    const options = {
        body: data.body || 'Você tem uma nova notificação.',
        icon: data.icon || '/icons/icon-192x192.png',
        badge: '/icons/icon-72x72.png',
        data: { url: data.url || '/admin' },
        vibrate: [200, 100, 200],
        requireInteraction: true,
        tag: data.tag || 'comere-notification',
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

// ── Notification click ────────────────────────────────────────────────────────
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = event.notification.data?.url || '/admin';

    event.waitUntil(
        clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((windowClients) => {
                for (const client of windowClients) {
                    if (client.url.includes(targetUrl) && 'focus' in client) {
                        return client.focus();
                    }
                }
                return clients.openWindow(targetUrl);
            })
    );
});
