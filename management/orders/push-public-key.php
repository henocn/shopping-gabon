<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use src\PushNotification;

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$publicKey = PushNotification::getPublicKey();

if ($publicKey === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'VAPID key not configured']);
    exit;
}

echo json_encode(['success' => true, 'publicKey' => $publicKey]);
exit;
