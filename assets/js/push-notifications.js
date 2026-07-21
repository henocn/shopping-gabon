/**
 * LUXEMARKET Push Notifications Manager
 * Gestion complète des notifications push pour l'application admin
 * Fonctionne même quand l'app est fermée
 */

var LUXEMARKET_PUSH = (function() {
    'use strict';

    var subscription = null;
    var applicationServerKey = null;
    var isSubscribed = false;
    var isInitialized = false;
    var userId = null;

    // ============================================
    // INITIALISATION
    // ============================================
    
    function init(options) {
        if (isInitialized) return;
        isInitialized = true;

        // Récupérer l'ID utilisateur depuis la fenêtre ou les options
        userId = options && options.userId ? options.userId : (window.LUXEMARKET_USER_ID || null);

        // Vérifier si les notifications sont supportées
        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
            console.log('[LUXEMARKET PUSH] Notifications push not supported in this browser');
            return;
        }

        // Récupérer la clé publique VAPID depuis le serveur
        fetchVAPIDKey();
    }

    // ============================================
    // RÉCUPÉRER LA CLÉ VAPID
    // ============================================
    
    function fetchVAPIDKey() {
        fetch('/management/orders/push-public-key.php')
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Failed to fetch VAPID key: ' + response.status);
                }
                return response.json();
            })
            .then(function(data) {
                if (data && data.publicKey) {
                    applicationServerKey = data.publicKey;
                    checkSubscription();
                } else {
                    console.error('[LUXEMARKET PUSH] No VAPID key returned from server');
                }
            })
            .catch(function(error) {
                console.error('[LUXEMARKET PUSH] Error fetching VAPID key:', error);
                // Réessayer après 5 secondes
                setTimeout(fetchVAPIDKey, 5000);
            });
    }

    // ============================================
    // VÉRIFIER L'ABONNEMENT EXISTANT
    // ============================================
    
    function checkSubscription() {
        if (!applicationServerKey) {
            setTimeout(checkSubscription, 500);
            return;
        }

        navigator.serviceWorker.ready.then(function(registration) {
            registration.pushManager.getSubscription().then(function(existingSubscription) {
                if (existingSubscription) {
                    subscription = existingSubscription;
                    isSubscribed = true;
                    sendSubscriptionToServer(subscription);
                    console.log('[LUXEMARKET PUSH] Already subscribed to push notifications');
                    triggerEvent('subscribed', subscription);
                } else {
                    isSubscribed = false;
                    // Demander la permission si on est sur une page sécurisée
                    if (window.location.protocol === 'https:' || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                        requestPermissionAndSubscribe();
                    }
                }
            }).catch(function(error) {
                console.error('[LUXEMARKET PUSH] Error checking subscription:', error);
            });
        });
    }

    // ============================================
    // DEMANDER LA PERMISSION ET S'ABONNER
    // ============================================
    
    function requestPermissionAndSubscribe() {
        // Demander la permission
        Notification.requestPermission(function(permission) {
            if (permission === 'granted') {
                console.log('[LUXEMARKET PUSH] Notification permission granted');
                subscribeToPush();
            } else {
                console.log('[LUXEMARKET PUSH] Notification permission denied');
                triggerEvent('permissionDenied');
            }
        });
    }

    // ============================================
    // S'ABONNER AUX NOTIFICATIONS PUSH
    // ============================================
    
    function subscribeToPush() {
        if (!applicationServerKey) {
            console.log('[LUXEMARKET PUSH] VAPID key not loaded yet, retrying...');
            setTimeout(subscribeToPush, 1000);
            return;
        }

        navigator.serviceWorker.ready.then(function(registration) {
            // Convertir la clé VAPID (Base64 -> Uint8Array)
            var applicationServerKeyBytes = urlBase64ToUint8Array(applicationServerKey);

            registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: applicationServerKeyBytes
            }).then(function(newSubscription) {
                subscription = newSubscription;
                isSubscribed = true;
                return sendSubscriptionToServer(newSubscription);
            }).then(function() {
                console.log('[LUXEMARKET PUSH] Successfully subscribed to push notifications');
                triggerEvent('subscribed', subscription);
                showSuccessMessage('Notifications activées !');
            }).catch(function(error) {
                console.error('[LUXEMARKET PUSH] Failed to subscribe:', error);
                triggerEvent('subscribeError', error);
                showErrorMessage('Erreur lors de l\'abonnement: ' + error.message);
            });
        });
    }

    // ============================================
    // ENVoyer L'ABONNEMENT AU SERVEUR
    // ============================================
    
    function sendSubscriptionToServer(subscription) {
        var subscriptionData = {
            endpoint: subscription.endpoint,
            keys: {
                p256dh: arrayBufferToBase64(subscription.getKey('p256dh')),
                auth: arrayBufferToBase64(subscription.getKey('auth'))
            },
            userId: userId
        };

        return fetch('/management/orders/push-subscribe.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(subscriptionData)
        }).then(function(response) {
            if (!response.ok) {
                throw new Error('Server subscription failed: ' + response.status);
            }
            return response.json();
        });
    }

    // ============================================
    // SE DÉSABONNER
    // ============================================
    
    function unsubscribe() {
        if (!isSubscribed) {
            return Promise.resolve();
        }

        return navigator.serviceWorker.ready.then(function(registration) {
            return registration.pushManager.getSubscription().then(function(existingSubscription) {
                if (existingSubscription) {
                    return existingSubscription.unsubscribe().then(function() {
                        isSubscribed = false;
                        subscription = null;
                        
                        // Envoyer au serveur pour désabonnement
                        return fetch('/management/orders/push-unsubscribe.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({
                                userId: userId,
                                endpoint: existingSubscription.endpoint
                            })
                        }).then(function(response) {
                            if (!response.ok) {
                                console.error('[LUXEMARKET PUSH] Failed to unsubscribe from server');
                            }
                            triggerEvent('unsubscribed');
                            showSuccessMessage('Notifications désactivées');
                        }).catch(function(error) {
                            console.error('[LUXEMARKET PUSH] Error unsubscribing from server:', error);
                        });
                    });
                }
                return Promise.resolve();
            });
        });
    }

    // ============================================
    // UTILITAIRES
    // ============================================
    
    function arrayBufferToBase64(buffer) {
        var binary = '';
        var bytes = new Uint8Array(buffer);
        for (var i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return window.btoa(binary);
    }

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = window.atob(base64);
        return Uint8Array.from([].map.call(rawData, function(c) {
            return c.charCodeAt(0);
        }));
    }

    function showSuccessMessage(message) {
        var banner = document.getElementById('push-notif-banner');
        if (banner) {
            banner.classList.remove('d-none');
            var span = banner.querySelector('span');
            if (span) span.textContent = message;
            setTimeout(function() {
                banner.classList.add('d-none');
            }, 5000);
        }
        triggerEvent('success', { message: message });
    }

    function showErrorMessage(message) {
        var banner = document.getElementById('push-notif-banner');
        if (banner) {
            banner.classList.remove('d-none');
            var span = banner.querySelector('span');
            if (span) span.textContent = message;
        }
        triggerEvent('error', { message: message });
    }

    function triggerEvent(eventName, data) {
        var event = new CustomEvent(eventName, { detail: data });
        document.dispatchEvent(event);
    }

    // ============================================
    // API PUBLIQUE
    // ============================================
    
    return {
        // Initialisation
        init: init,
        
        // Abonnement
        subscribe: function() {
            if (isSubscribed) {
                return Promise.resolve(subscription);
            }
            return requestPermissionAndSubscribe();
        },
        
        // Désabonnement
        unsubscribe: unsubscribe,
        
        // Vérifier l'état
        isSubscribed: function() { return isSubscribed; },
        getSubscription: function() { return subscription; },
        
        // Forcer la mise à jour de l'abonnement
        updateSubscription: function() {
            if (isSubscribed && subscription) {
                return sendSubscriptionToServer(subscription);
            }
            return Promise.reject(new Error('Not subscribed'));
        }
    };
})();

// Initialisation automatique quand le DOM est prêt
if (document.readyState === 'complete' || document.readyState === 'interactive') {
    setTimeout(function() {
        LUXEMARKET_PUSH.init();
    }, 1000);
} else {
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            LUXEMARKET_PUSH.init();
        }, 1000);
    });
}

// Écouteur d'événements pour la page
window.addEventListener('load', function() {
    // Événement quand l'utilisateur clique sur "Activer" dans le banner
    var enableBtn = document.getElementById('push-enable-btn');
    if (enableBtn) {
        enableBtn.addEventListener('click', function() {
            LUXEMARKET_PUSH.subscribe().catch(function(error) {
                console.error('Error subscribing:', error);
            });
        });
    }
});
