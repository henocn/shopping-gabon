<?php

session_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../utils/middleware.php';

use src\Connectbd;

verifyConnection("/management/orders/");
checkIsActive($_SESSION['user_id'] ?? 0);

header('Content-Type: application/json');

// Créer la table si elle n'existe pas
$cnx = Connectbd::getConnection();
$cnx->exec("
    CREATE TABLE IF NOT EXISTS push_subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        endpoint VARCHAR(512) NOT NULL,
        p256dh VARCHAR(255) NOT NULL,
        auth VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_endpoint (endpoint)
    )
");

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || empty($data['endpoint']) || empty($data['keys']['p256dh']) || empty($data['keys']['auth'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Subscription invalide.']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$endpoint = $data['endpoint'];
$p256dh = $data['keys']['p256dh'];
$auth = $data['keys']['auth'];

$stmt = $cnx->prepare("
    INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth)
    VALUES (:user_id, :endpoint, :p256dh, :auth)
    ON DUPLICATE KEY UPDATE user_id = :user_id2, p256dh = :p256dh2, auth = :auth2
");
$stmt->execute([
    'user_id' => $userId,
    'endpoint' => $endpoint,
    'p256dh' => $p256dh,
    'auth' => $auth,
    'user_id2' => $userId,
    'p256dh2' => $p256dh,
    'auth2' => $auth,
]);

echo json_encode(['success' => true]);
exit;
