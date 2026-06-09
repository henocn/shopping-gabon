/* Service Worker pour les Web Push (nouvelles commandes) */
self.addEventListener('push', function (event) {
    var payload = { title: 'Nouvelle commande', body: "Une nouvelle commande vient d'être passée." };
    if (event.data) {
        // Robustesse : le payload peut être du JSON ou du texte selon l'envoi WebPush
        try {
            payload = event.data.json();
        } catch (e) {
            try {
                // event.data.text() est asynchrone mais dans un try simple on peut tenter un fallback
                // (si ça échoue on gardera le payload par défaut)
                var txt = event.data.text ? event.data.text() : null;
                if (txt && typeof txt.then === 'function') {
                    event.waitUntil(txt.then(function (t) {
                        payload.body = t;
                        var nonce = payload.nonce || String(Date.now());
                        return self.registration.showNotification(payload.title || 'Nouvelle commande', {
                            body: payload.body || "Une nouvelle commande vient d'être passée.",
                            tag: 'new-order-' + nonce,
                            requireInteraction: false
                        });
                    }));
                    return;
                }
            } catch (e2) {}
        }
    }

    var nonce = payload.nonce || String(Date.now());
    event.waitUntil(
        self.registration.showNotification(payload.title || 'Nouvelle commande', {
            body: payload.body || payload.message || "Une nouvelle commande vient d'être passée.",
            tag: 'new-order-' + nonce,
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
