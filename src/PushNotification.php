<?php

namespace src;

use PDO;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\ContentEncoding;

/**
 * Envoi des notifications Web Push aux abonnés (admins / assistants).
 */
class PushNotification
{
    private PDO $db;
    private string $configPath;

    public function __construct(PDO $db, string $configPath = null)
    {
        $this->db = $db;
        $this->configPath = $configPath ?? dirname(__DIR__) . '/config/vapid.php';
    }

    /**
     * Envoie une notification "nouvelle commande" à tous les abonnés.
     */
    public function notifyNewOrder(): void
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

        $payload = json_encode([
            'title' => 'Nouvelle commande',
            'body'  => "Une nouvelle commande vient d'être passée.",
        ]);

        $auth = [
            'VAPID' => [
                'subject'   => $vapid['subject'],
                'publicKey' => $vapid['publicKey'],
                'privateKey'=> $vapid['privateKey'],
            ],
        ];

        $webPush = new WebPush($auth);

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
            if (!$report->isSuccess()) {
                // Optionnel : supprimer l’abonnement en cas d’erreur 410/404
            }
        }
    }

    /**
     * Retourne la clé publique VAPID si les push sont activés (pour le frontend).
     */
    public static function getPublicKey(string $configPath = null): ?string
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
