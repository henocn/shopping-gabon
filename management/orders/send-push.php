<?php
/**
 * LUXEMARKET Push Notifications - Send Notification Endpoint
 * Envoie une notification push à un ou plusieurs utilisateurs
 */

require '../../vendor/autoload.php';
require '../../utils/middleware.php';

verifyConnection('/management/orders/');
checkAdminAccess($_SESSION['user_id']);
checkIsActive($_SESSION['user_id']);
verifyCsrfToken();

use src\Connectbd;

$cnx = Connectbd::getConnection();

header('Content-Type: application/json');

// Récupérer les données POST
$input = json_decode(file_get_contents('php://input'), true);

// Vérifier les paramètres requis
if (!isset($input['title']) && !isset($input['userId']) && !isset($input['orderId'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required parameters'
    ]);
    exit;
}

// Récupérer la clé privée VAPID
$stmt = $cnx->prepare("SELECT private_key FROM push_keys ORDER BY id DESC LIMIT 1");
$stmt->execute();
$privateKey = $stmt->fetchColumn();

if (!$privateKey) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'VAPID private key not configured'
    ]);
    exit;
}

// Construire le payload de la notification
$title = $input['title'] ?? 'Nouvelle commande LUXEMARKET';
$body = $input['body'] ?? 'Une nouvelle commande vient d\'être passée.';
$orderId = $input['orderId'] ?? null;
$userId = $input['userId'] ?? null;
$url = $input['url'] ?? '/management/orders/';
$title = is_string($title) ? mb_substr(trim($title), 0, 120) : 'Nouvelle commande LUXEMARKET';
$body = is_string($body) ? mb_substr(trim($body), 0, 500) : 'Une nouvelle commande vient d\'être passée.';
$url = is_string($url) && str_starts_with($url, '/management/') ? $url : '/management/orders/';

// Si userId n'est pas fourni, envoyer à tous les administrateurs
if (!$userId) {
    $stmt = $cnx->prepare("SELECT DISTINCT user_id FROM push_subscriptions WHERE user_id IN (SELECT user_id FROM users WHERE role = 1)");
    $stmt->execute();
} else {
    $stmt = $cnx->prepare("SELECT user_id FROM push_subscriptions WHERE user_id = ?");
    $stmt->execute([$userId]);
}

if ($userId) {
    $stmt->execute([$userId]);
}

$userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

$sentCount = 0;
$errors = [];

foreach ($userIds as $uid) {
    // Récupérer les abonnements pour cet utilisateur
    $stmt = $cnx->prepare("SELECT * FROM push_subscriptions WHERE user_id = ?");
    $stmt->execute([$uid]);
    $subscriptions = $stmt->fetchAll();

    foreach ($subscriptions as $subscription) {
        $result = sendPushNotification($subscription, [
            'title' => $title,
            'body' => $body . ($orderId ? ' (Commande #' . $orderId . ')' : ''),
            'data' => [
                'url' => $url . ($orderId ? '?id=' . $orderId : ''),
                'orderId' => $orderId
            ]
        ], $privateKey);

        if ($result) {
            $sentCount++;
        } else {
            $errors[] = 'Failed to send to user ' . $uid;
        }
    }
}

echo json_encode([
    'success' => true,
    'sent' => $sentCount,
    'errors' => $errors
]);

/**
 * Envoie une notification push à un abonnement
 */
function sendPushNotification($subscription, $payload, $privateKey) {
    $endpoint = $subscription['endpoint'];
    $p256dh = $subscription['p256dh'];
    $auth = $subscription['auth'];

    // Créer le JWT pour l'authentification VAPID
    $authToken = generateVAPIDAuthToken($privateKey);

    // Payload de la notification
    $notificationPayload = [
        'notification' => [
            'title' => $payload['title'],
            'body' => $payload['body'],
            'icon' => '/assets/icons/icon-192x192.png',
            'badge' => '/assets/icons/icon-192x192.png',
            'requireInteraction' => true,
            'renotify' => true,
            'vibrate' => [200, 100, 200],
            'data' => $payload['data'] ?? ['url' => '/management/orders/']
        ]
    ];

    // Envoi de la notification via cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $authToken,
        'TTL: 86400',
        'Urgency: high'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notificationPayload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Retourne true si la notification a été envoyée avec succès
    return $httpCode >= 200 && $httpCode < 300;
}

/**
 * Génère un token d'authentification VAPID
 */
function generateVAPIDAuthToken($privateKey) {
    $header = [
        'typ' => 'JWT',
        'alg' => 'ES256'
    ];

    $token = [
        'aud' => 'https://fcm.googleapis.com',
        'exp' => time() + 3600,
        'sub' => 'mailto:contact@luxemarket.com'
    ];

    $headerEncoded = base64UrlEncode(json_encode($header));
    $tokenEncoded = base64UrlEncode(json_encode($token));
    $signature = base64UrlEncode(hash_hmac('sha256', $headerEncoded . '.' . $tokenEncoded, $privateKey, true));

    return $headerEncoded . '.' . $tokenEncoded . '.' . $signature;
}

/**
 * Encode en Base64 URL-safe
 */
function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
?>
