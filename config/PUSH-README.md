# Notifications Web Push (nouvelles commandes)

## Fonctionnement

- Les **admins/assistants** peuvent s’abonner aux notifications push depuis la page **Gestion des commandes** (bouton « Activer » ou bannière).
- Quand un **client** passe une commande, le serveur envoie une **notification push** à tous les abonnés (comme WhatsApp / les sites qui demandent « Autoriser les notifications »).

## Activation

1. **Générer les clés VAPID** (une seule fois, sur un environnement avec OpenSSL) :
   ```bash
   cd management/orders
   php generate-vapid.php
   ```
   Cela crée ou met à jour `config/vapid.php` avec `enabled => true` et les clés.

2. Si la génération échoue (ex. OpenSSL manquant), vous pouvez créer les clés sur un autre serveur, puis copier le contenu de `config/vapid.php` (avec `enabled => true`, `subject`, `publicKey`, `privateKey`).

3. La **table** `push_subscriptions` est créée automatiquement au premier abonnement (lors de l’appel à `push-subscribe.php`). Sinon vous pouvez l’exécuter à la main :
   ```sql
   CREATE TABLE IF NOT EXISTS push_subscriptions (
       id INT AUTO_INCREMENT PRIMARY KEY,
       user_id INT NOT NULL,
       endpoint VARCHAR(512) NOT NULL,
       p256dh VARCHAR(255) NOT NULL,
       auth VARCHAR(255) NOT NULL,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       UNIQUE KEY unique_endpoint (endpoint)
   );
   ```

## Test

1. Ouvrir **Gestion des commandes** en étant connecté.
2. Si les push sont activés et que la permission n’est pas encore accordée, cliquer sur **Activer** et accepter les notifications.
3. Passer une commande depuis le site client (formulaire de commande).
4. Une **notification système** doit s’afficher (même si l’onglet admin n’est pas actif).

## Fichiers concernés

- `config/vapid.php` : clés VAPID (généré par `generate-vapid.php`).
- `management/orders/generate-vapid.php` : script de génération des clés.
- `management/orders/push-public-key.php` : renvoie la clé publique (frontend).
- `management/orders/push-subscribe.php` : enregistre l’abonnement.
- `src/PushNotification.php` : envoi des push à tous les abonnés.
- `management/orders/save.php` : appelle `notifyNewOrder()` après création d’une commande.
- `sw.js` (racine du projet) : Service Worker qui reçoit la push et affiche la notification.
