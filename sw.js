/* Service Worker pour les Web Push (nouvelles commandes) */
self.addEventListener('push', function (event) {
    var payload = { title: 'Nouvelle commande', body: "Une nouvelle commande vient d'être passée." };
    if (event.data) {
        try {
            payload = event.data.json();
        } catch (e) {}
    }
    event.waitUntil(
        self.registration.showNotification(payload.title || 'Nouvelle commande', {
            body: payload.body || "Une nouvelle commande vient d'être passée.",
            icon: new URL('assets/images/idx121.png', self.registration.scope).href,
            tag: 'new-order',
            requireInteraction: false
        })
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var ordersUrl = new URL('management/orders/', self.registration.scope).href;
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (var i = 0; i < clientList.length; i++) {
                if (clientList[i].url.indexOf('management/orders') !== -1) {
                    clientList[i].focus();
                    return;
                }
            }
            if (clients.openWindow) {
                clients.openWindow(ordersUrl);
            }
        })
    );
});
