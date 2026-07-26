/**
 * LUXEMARKET - Offline Sync Manager
 * Gère la file d'attente des modifications de commandes quand l'appareil est hors ligne.
 */

var OfflineSyncManager = (function() {
    var dbName = 'LuxemarketOfflineDB';
    var storeName = 'offlineActions';
    var db;
    var dbReadyPromise;

    // Initialisation IndexedDB
    function initDB() {
        return new Promise(function(resolve, reject) {
            if (!window.indexedDB) {
                console.warn('[OfflineSync] IndexedDB non supporté');
                resolve(null);
                return;
            }

            var request = window.indexedDB.open(dbName, 1);
            
            request.onerror = function(event) {
                console.error('[OfflineSync] Erreur ouverture DB', event);
                reject(event);
            };

            request.onsuccess = function(event) {
                db = event.target.result;
                resolve(db);
                updatePendingCountUI();
            };

            request.onupgradeneeded = function(event) {
                var db = event.target.result;
                if (!db.objectStoreNames.contains(storeName)) {
                    db.createObjectStore(storeName, { keyPath: 'id', autoIncrement: true });
                }
            };
        });
    }

    // Sauvegarder une action hors ligne
    function saveAction(url, formData, orderId, formValues) {
        var ready = db ? Promise.resolve(db) : (dbReadyPromise || initDB());
        return ready.then(function() {
            if (!db) throw new Error('DB non initialisée');
            var transaction = db.transaction([storeName], 'readwrite');
            var store = transaction.objectStore(storeName);
            
            var action = {
                url: url,
                formData: formData,
                orderId: orderId,
                formValues: formValues,
                timestamp: new Date().getTime()
            };

            return new Promise(function(resolve, reject) {
                var request = store.add(action);
                request.onsuccess = function() {
                    console.log('[OfflineSync] Action sauvegardée pour la commande ' + orderId);
                    updatePendingCountUI();
                    if (typeof window.showNotification === 'function') {
                        window.showNotification('Mode hors-ligne : Modification sauvegardée localement.', 'warning');
                    }
                    resolve();
                };
                request.onerror = function() {
                    reject(new Error('Erreur de sauvegarde'));
                };
            });
        });
    }

    // Récupérer toutes les actions
    function getAllActions() {
        return new Promise(function(resolve, reject) {
            if (!db) return resolve([]);
            var transaction = db.transaction([storeName], 'readonly');
            var store = transaction.objectStore(storeName);
            var request = store.getAll();
            
            request.onsuccess = function() {
                resolve(request.result || []);
            };
            request.onerror = function() {
                reject(new Error("Erreur lecture actions"));
            };
        });
    }

    // Supprimer une action
    function deleteAction(id) {
        return new Promise(function(resolve, reject) {
            if (!db) return resolve();
            var transaction = db.transaction([storeName], 'readwrite');
            var store = transaction.objectStore(storeName);
            var request = store.delete(id);
            request.onsuccess = resolve;
            request.onerror = reject;
        });
    }

    // Synchroniser vers le serveur
    var isSyncing = false;
    function syncAll() {
        if (!navigator.onLine || isSyncing) return;
        isSyncing = true;

        getAllActions().then(function(actions) {
            if (actions.length === 0) {
                isSyncing = false;
                return;
            }

            console.log('[OfflineSync] Début de la synchronisation de ' + actions.length + ' actions...');
            showSyncingUI(true);

            // On traite les requêtes séquentiellement pour éviter les conflits
            var promiseChain = Promise.resolve();

            actions.forEach(function(action) {
                promiseChain = promiseChain.then(function() {
                    return fetch(action.url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: action.formData
                    }).then(function(response) {
                        if (response.ok) {
                            console.log('[OfflineSync] Succès sync commande ' + action.orderId);
                            return deleteAction(action.id);
                        } else {
                            throw new Error('Erreur serveur HTTP ' + response.status);
                        }
                    }).catch(function(err) {
                        console.error('[OfflineSync] Échec sync commande ' + action.orderId, err);
                        // Ne pas supprimer si erreur réseau, on retentera plus tard
                    });
                });
            });

            promiseChain.then(function() {
                isSyncing = false;
                updatePendingCountUI();
                showSyncingUI(false);
                
                // Si tout a réussi (ou partiellement), on rafraîchit la page
                getAllActions().then(function(remaining) {
                    if (remaining.length === 0 && actions.length > 0) {
                        if (typeof window.showNotification === 'function') {
                            window.showNotification('Synchronisation terminée avec succès !', 'success', 5000);
                        }
                    }
                });
            });
        });
    }

    // Mettre à jour l'UI (le badge)
    function updatePendingCountUI() {
        getAllActions().then(function(actions) {
            var badge = document.getElementById('offline-sync-badge');
            if (!badge) return;
            
            if (actions.length > 0) {
                badge.classList.remove('d-none');
                var offlineIcon = document.createElement('i');
                offlineIcon.className = 'bx bx-wifi-off me-1';
                badge.replaceChildren(offlineIcon, document.createTextNode(' ' + String(actions.length) + ' en attente'));
                badge.className = 'badge bg-danger rounded-pill d-flex align-items-center ms-2';
            } else {
                badge.classList.add('d-none');
            }
        });
    }

    function showSyncingUI(isSyncing) {
        var badge = document.getElementById('offline-sync-badge');
        if (!badge) return;
        if (isSyncing) {
            badge.classList.remove('d-none', 'bg-danger');
            badge.classList.add('bg-warning', 'text-dark');
            var loadingIcon = document.createElement('i');
            loadingIcon.className = 'bx bx-loader-alt bx-spin me-1';
            badge.replaceChildren(loadingIcon, document.createTextNode(' Sync...'));
        } else {
            updatePendingCountUI();
        }
    }

    // Listeners réseau
    window.addEventListener('online', function() {
        console.log('[OfflineSync] Retour en ligne détecté');
        syncAll();
    });

    window.addEventListener('offline', function() {
        console.log('[OfflineSync] Perte de connexion détectée');
        if (typeof window.showNotification === 'function') {
            window.showNotification('Vous êtes hors-ligne. Vos actions seront sauvegardées.', 'warning');
        }
    });

    // Init
    dbReadyPromise = initDB().then(function() {
        // Tenter une synchro au démarrage au cas où
        setTimeout(syncAll, 2000);
    });

    return {
        saveAction: saveAction,
        syncAll: syncAll
    };
})();
