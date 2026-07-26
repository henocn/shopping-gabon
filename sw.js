// LUXEMARKET Admin PWA - Service Worker v5
// Service Worker avec fetch handler ACTIF (obligatoire pour Chrome)

var CACHE_NAME = 'luxemarket-admin-v9';

var PRECACHE_URLS = [
  // Seules les ressources publiques et stables sont précachées. Les pages
  // d'authentification dépendent de cookies et ne doivent jamais être cachées.

  // CSS
  '/assets/css/bootstrap.min.css',
  '/assets/css/admin.css',
  '/assets/css/index.css',
  '/assets/css/navbar.css',
  '/assets/css/login.css',

  // JS
  '/assets/js/bootstrap.bundle.min.js',
  '/assets/js/offline-sync.js',

  // Images et icones
  '/assets/images/logo.jpg',
  '/assets/icons/icon-192x192.png',
  '/assets/icons/icon-512x512.png',

  // Manifest
  '/manifest.json'
];

// ============================================
// INSTALL: Précache toutes les URLs
// ============================================
self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function(cache) {
      return cache.addAll(PRECACHE_URLS);
    }).then(function() {
      return self.skipWaiting(); // Force l'activation immédiate
    })
  );
});

// ============================================
// ACTIVATE: Nettoie les anciens caches
// ============================================
self.addEventListener('activate', function(event) {
  event.waitUntil(
    caches.keys().then(function(cacheNames) {
      return Promise.all(
        cacheNames.map(function(name) {
          if (name !== CACHE_NAME && name.indexOf('luxemarket-admin-') === 0) {
            return caches.delete(name);
          }
        })
      );
    }).then(function() {
      return self.clients.claim(); // Prend le contrôle de tous les clients immédiatement
    })
  );
});

// ============================================
// FETCH: Stratégie Network-First avec fallback cache
// CE HANDLER EST OBLIGATOIRE POUR QUE CHROME GÉNÈRE UN WebAPK
// ============================================
self.addEventListener('fetch', function(event) {
  var requestUrl = new URL(event.request.url);

  // Ignorer les requêtes cross-origin
  if (requestUrl.origin !== location.origin) {
    return;
  }

  // IMPORTANT : Ignorer les requêtes non-GET (POST, PUT, DELETE, etc.) pour ne pas casser les formulaires
  if (event.request.method !== 'GET') {
    return;
  }

  // Cache-first pour les assets statiques (CSS, JS, images, polices)
  if (requestUrl.pathname.match(/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff2?|woff|ttf|eot|otf)$/)) {
    event.respondWith(
      caches.match(event.request).then(function(cached) {
        var fetchPromise = fetch(event.request).then(function(response) {
          if (response && response.status === 200 && !response.redirected) {
            var responseClone = response.clone();
            caches.open(CACHE_NAME).then(function(cache) {
              cache.put(event.request, responseClone);
            });
          }
          return response;
        });
        return cached || fetchPromise;
      })
    );
    return;
  }

  // Le manifest peut être servi depuis le cache, mais les pages et API admin
  // restent toujours réseau : aucune donnée privée ne doit devenir obsolète.
  if (requestUrl.pathname === '/manifest.json') {
    event.respondWith(
      fetch(event.request).then(function(response) {
        if (response && response.status === 200) {
          var responseClone = response.clone();
          caches.open(CACHE_NAME).then(function(cache) {
            cache.put(event.request, responseClone);
          });
        }
        return response;
      }).catch(function() {
        // Fallback vers le cache
        return caches.match(event.request).then(function(cached) {
          // Si pas dans le cache et que c'est une page management, fallback vers dashboard
          if (!cached && requestUrl.pathname.indexOf('/management/') === 0) {
            return caches.match('/management/dashboard.php');
          }
          return cached;
        });
      })
    );
    return;
  }

  if (requestUrl.pathname.indexOf('/management/') === 0) {
    event.respondWith(fetch(event.request));
    return;
  }

  // Default: Network-first avec fallback cache
  event.respondWith(
    fetch(event.request).catch(function() {
      return caches.match(event.request);
    })
  );
});

// ============================================
// PUSH NOTIFICATIONS: Pour les nouvelles commandes
// Fonctionne même quand l'app est FERMÉE
// ============================================
self.addEventListener('push', function(event) {
  // Définir un payload par défaut
  var payload = { 
    title: 'Nouvelle commande', 
    body: "Une nouvelle commande vient d'être passée.",
    data: { url: '/management/orders/' }
  };
  
  if (event.data) {
    try {
      var parsed = event.data.json();
      // Fusionner avec le payload par défaut
      payload.title = parsed.title || payload.title;
      payload.body = parsed.body || parsed.message || payload.body;
      payload.nonce = parsed.nonce || null;
      if (parsed.data) {
        payload.data = parsed.data;
      }
    } catch (e) {
      // Si ce n'est pas du JSON, essayer en texte brut
      try {
        var txt = event.data.text();
        if (txt) {
          payload.body = txt;
        }
      } catch (e2) {
        // Utiliser le payload par défaut
      }
    }
  }

  // Toujours afficher la notification, même en cas d'erreur de parsing
  event.waitUntil(showNotification(payload));
});

// Fonction pour afficher une notification
function showNotification(payload) {
  var nonce = payload.nonce || String(Date.now());
  
  var notificationOptions = {
    body: payload.body || payload.message || "Une nouvelle commande vient d'être passée.",
    icon: '/assets/icons/icon-192x192.png',
    badge: '/assets/icons/icon-192x192.png',
    tag: 'new-order-' + nonce,
    requireInteraction: true,
    renotify: true,
    vibrate: [200, 100, 200],
    data: payload.data || { url: '/management/orders/' }
  };

  // Ajouter un son si disponible
  if (payload.sound || payload.audio) {
    notificationOptions.audio = payload.sound || payload.audio;
  }

  return self.registration.showNotification(
    payload.title || 'Nouvelle commande',
    notificationOptions
  );
}

// ============================================
// NOTIFICATION CLICK: Ouvre la page appropriée
// ============================================
self.addEventListener('notificationclick', function(event) {
  event.notification.close();
  
  // Récupérer l'URL depuis les data de la notification
  var url = '/management/orders/';
  if (event.notification.data && event.notification.data.url) {
    url = event.notification.data.url;
  }
  
  try {
    var parsedUrl = new URL(url, self.registration.scope);
    if (parsedUrl.origin !== self.location.origin || parsedUrl.pathname.indexOf('/management/') !== 0) {
      throw new Error('URL de notification non autorisée');
    }
    url = parsedUrl.href;
  } catch (error) {
    url = new URL('/management/orders/', self.location.origin).href;
  }
  
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
      // Chercher un client déjà sur la bonne page
      for (var i = 0; i < clientList.length; i++) {
        if (clientList[i].url.indexOf(url) !== -1) {
          clientList[i].focus();
          // Envoyer un message pour notifier que la notification a été cliquée
          clientList[i].postMessage({
            type: 'NOTIFICATION_CLICKED',
            data: event.notification.data
          });
          return;
        }
      }
      
      // Sinon ouvrir un nouveau client
      if (clients.openWindow) {
        return clients.openWindow(url);
      }
    })
  );
});

// ============================================
// MESSAGE: Communication entre SW et pages
// ============================================
self.addEventListener('message', function(event) {
  if (event.data && event.data.type === 'SUBSCRIBE_PUSH') {
    // Gérer l'abonnement depuis la page
    var applicationServerKey = urlBase64ToUint8Array(event.data.applicationServerKey);
    
    event.waitUntil(
      self.registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: applicationServerKey
      }).then(function(subscription) {
        return event.source.postMessage({
          type: 'SUBSCRIPTION_SUCCESS',
          subscription: subscription
        });
      }).catch(function(error) {
        return event.source.postMessage({
          type: 'SUBSCRIPTION_ERROR',
          error: error.message
        });
      })
    );
  }
});

// Renouvelle automatiquement un abonnement que le navigateur aurait fait
// tourner, afin que les notifications continuent après un redémarrage.
self.addEventListener('pushsubscriptionchange', function(event) {
  event.waitUntil(
    fetch('/management/orders/push-public-key.php', { credentials: 'include' })
      .then(function(response) { return response.json(); })
      .then(function(data) {
        if (!data || !data.publicKey) {
          throw new Error('Clé VAPID indisponible');
        }
        return self.registration.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: urlBase64ToUint8Array(data.publicKey)
        });
      })
      .then(function(subscription) {
        var payload = subscription.toJSON();
        return fetch('/management/orders/push-subscribe.php', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
      })
  );
});

// Fonction utilitaire pour convertir URL Base64 en Uint8Array
function urlBase64ToUint8Array(base64String) {
  var padding = '='.repeat((4 - base64String.length % 4) % 4);
  var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  var rawData = self.atob(base64);
  return Uint8Array.from([].map.call(rawData, function(c) {
    return c.charCodeAt(0);
  }));
}
