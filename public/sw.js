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
    const data = event.notification.data || {};

    // Alerta de novo pedido do marketplace: tem botões de ação (Aprovar/
    // Recusar) e, ao clicar no corpo, precisa abrir o pedido específico
    // (não só focar qualquer aba do admin já aberta).
    if (data.type === 'comere-order-alert') {
        event.notification.close();

        if (event.action === 'approve' || event.action === 'reject') {
            event.waitUntil(handleOrderAlertAction(data, event.action));
            return;
        }

        event.waitUntil(openOrderDeepLink(data.url || '/admin'));
        return;
    }

    event.notification.close();

    const targetUrl = data.url || '/admin';

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

async function handleOrderAlertAction(data, action) {
    const endpoint = action === 'approve' ? data.approveUrl : data.rejectUrl;
    if (!endpoint) return;

    let csrfToken = null;
    try {
        if (self.cookieStore) {
            const cookie = await self.cookieStore.get('XSRF-TOKEN');
            csrfToken = cookie ? decodeURIComponent(cookie.value) : null;
        }
    } catch {
        csrfToken = null;
    }

    if (!csrfToken) {
        // Sem como montar o header CSRF a partir do service worker nesse
        // navegador — abre/foca o pedido pra decidir por lá em vez de
        // arriscar um POST sem token válido.
        return openOrderDeepLink(data.url || '/admin');
    }

    try {
        await fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': csrfToken,
            },
        });
    } catch {
        // Falha de rede: não há card visível pra mostrar erro, então apenas
        // desiste silenciosamente — o card (se a aba estiver aberta) ou a
        // tela de Pedidos continuam mostrando o pedido como pendente.
    }

    const windowClients = await clients.matchAll({ type: 'window', includeUncontrolled: true });
    windowClients.forEach((client) => {
        client.postMessage({ type: 'comere-order-resolved' });
    });
}

function openOrderDeepLink(targetUrl) {
    return clients
        .matchAll({ type: 'window', includeUncontrolled: true })
        .then((windowClients) => {
            const adminClient = windowClients.find((client) => client.url.includes('/admin'));

            if (adminClient && 'navigate' in adminClient) {
                return adminClient.navigate(targetUrl).then((client) => client && client.focus());
            }

            return clients.openWindow(targetUrl);
        });
}
