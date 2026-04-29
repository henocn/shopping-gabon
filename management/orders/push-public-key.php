<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use src\PushNotification;

header('Content-Type: application/json');

$publicKey = PushNotification::getPublicKey();

if ($publicKey === null) {
    echo json_encode(['enabled' => false]);
    exit;
}

echo json_encode(['enabled' => true, 'publicKey' => $publicKey]);
exit;
