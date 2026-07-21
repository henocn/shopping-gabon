<?php

namespace src;

use PDO;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * Envoi des notifications Web Push aux abonnés (admins / assistants).
 */
class PushNotification
{
    private PDO $db;
    private string $configPath;

    public function __construct(PDO $db, ?string $configPath = null)
    {
        $this->db = $db;
        $this->configPath = $configPath ?? dirname(__DIR__) . '/config/vapid.php';
    }

    /**
     * Envoie une notification "nouvelle commande" à tous les abonnés.
     * $clientName et $productName sont optionnels, mais permettent un message plus détaillé.
     */
    public function notifyNewOrder(?string $clientName = null, ?string $productName = null, ?int $totalPrice = null): void
    {
        $vapid = $this->loadVapid();
        if (!$vapid || empty($vapid['publicKey']) || empty($vapid['privateKey'])) {
            return;
        }
        if (isset($vapid['enabled']) && !$vapid['enabled']) {
            return;
        }

        $subs = $this->getSubscriptions();
        if (empty($subs)) {
            return;
        }

        $nonce = bin2hex(random_bytes(8));
        $title = 'Nouvelle commande';
        if ($clientName && $productName) {
            $body = sprintf("%s vient de passer une commande pour %s.", $clientName, $productName);
        } elseif ($productName) {
            $body = sprintf("Une nouvelle commande a été passée pour %s.", $productName);
        } else {
            $body = "Une nouvelle commande vient d'être passée.";
        }
        if ($totalPrice) {
            $body .= sprintf(" (%s FCFA)", number_format($totalPrice, 0, ',', ' '));
        }

        $payload = json_encode([
            'title' => $title,
            'body'  => $body,
            'nonce' => $nonce,
        ]);

        $auth = [
            'VAPID' => [
                'subject'   => $vapid['subject'],
                'publicKey' => $vapid['publicKey'],
                'privateKey'=> $vapid['privateKey'],
            ],
        ];

        $webPush = new WebPush($auth, [
            'TTL' => 86400,       // 24 heures — la notification restera en file d'attente
            'urgency' => 'high',  // Priorité haute — réveille le téléphone même en mode veille
            'topic' => 'new-order',
        ]);

        foreach ($subs as $row) {
            try {
                $sub = Subscription::create([
                    'endpoint' => $row['endpoint'],
                    'keys' => [
                        'p256dh' => $row['p256dh'],
                        'auth'   => $row['auth'],
                    ],
                ]);
                $webPush->queueNotification($sub, $payload);
            } catch (\Throwable $e) {
                // Abonnement invalide : on peut le supprimer plus tard
                continue;
            }
        }

        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess() && $report->isSubscriptionExpired()) {
                $this->deleteSubscription($report->getEndpoint());
            }
        }
    }

    /**
     * Supprime un abonnement push devenu invalide (410/404, ou clé VAPID obsolète).
     */
    private function deleteSubscription(string $endpoint): void
    {
        $stmt = $this->db->prepare("DELETE FROM push_subscriptions WHERE endpoint = :endpoint");
        $stmt->execute(['endpoint' => $endpoint]);
    }

    /**
     * Retourne la clé publique VAPID si les push sont activés (pour le frontend).
     */
    public static function getPublicKey(?string $configPath = null): ?string
    {
        $path = $configPath ?? dirname(__DIR__) . '/config/vapid.php';
        if (!is_file($path)) {
            return null;
        }
        $vapid = include $path;
        if (!is_array($vapid)) {
            return null;
        }
        if (isset($vapid['enabled']) && !$vapid['enabled']) {
            return null;
        }
        if (empty($vapid['publicKey'])) {
            return null;
        }
        return $vapid['publicKey'];
    }

    private function loadVapid(): ?array
    {
        if (!is_file($this->configPath)) {
            return null;
        }
        $vapid = include $this->configPath;
        return is_array($vapid) ? $vapid : null;
    }

    private function getSubscriptions(): array
    {
        $sql = "SELECT endpoint, p256dh, auth FROM push_subscriptions";
        $stmt = $this->db->query($sql);
        if (!$stmt) {
            return [];
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
