<?php

require '../../vendor/autoload.php';
require '../../utils/middleware.php';

use src\Connectbd;
use src\Order;

verifyConnection("/management/orders/");
checkIsActive($_SESSION['user_id']);

header('Content-Type: application/json');

try {
    $cnx = Connectbd::getConnection();
    $orderManager = new Order($cnx);

    $lastId = isset($_GET['last_id']) ? (int) $_GET['last_id'] : 0;
    $orders = [];

    if (isset($_SESSION['role']) && isset($_SESSION['user_id'])) {
        if ((int) $_SESSION['role'] === 1) {
            $orders = $orderManager->getAllOrders();
        } else {
            $orders = $orderManager->getOrdersByUserId((int) $_SESSION['user_id']);
        }
    }

    $maxId = 0;
    $newCount = 0;

    foreach ($orders as $order) {
        $id = isset($order['order_id']) ? (int) $order['order_id'] : 0;
        if ($id > $maxId) {
            $maxId = $id;
        }
        if ($lastId > 0 && $id > $lastId) {
            $newCount++;
        }
    }

    if ($lastId <= 0) {
        $newCount = 0;
    }

    // Toujours renvoyer le max id pour que le client ne reçoive qu'une seule notif par nouvelle commande
    echo json_encode([
        'success'   => true,
        'new_count' => $newCount,
        'last_id'   => $maxId,
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des notifications.',
    ]);
}

