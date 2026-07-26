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

    $lastId = isset($_GET['last_id']) && is_scalar($_GET['last_id'])
        ? filter_var($_GET['last_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])
        : 0;
    if ($lastId === false) {
        $lastId = 0;
    }

    $maxId = 0;
    $newCount = 0;
    $newOrders = [];

    $role = isset($_SESSION['role']) ? (int)$_SESSION['role'] : 0;
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

    if ($role === 1) {
        $maxId = $orderManager->getMaxOrderId();
        if ($lastId > 0 && $maxId > $lastId) {
            $orders = $orderManager->getOrdersAfterId($lastId);
            $newCount = count($orders);
            $newOrders = $orders;
        }
    } else {
        $maxId = $orderManager->getMaxOrderIdByUserId($userId);
        if ($lastId > 0 && $maxId > $lastId) {
            $orders = $orderManager->getOrdersAfterIdByUserId($lastId, $userId);
            $newCount = count($orders);
            $newOrders = $orders;
        }
    }

    if ($lastId <= 0) {
        $newCount = 0;
        $newOrders = [];
    }
    
    // Format orders for the response
    $formattedOrders = [];
    foreach ($newOrders as $order) {
        $formattedOrders[] = [
            'order_id' => (int) $order['order_id'],
            'client_name' => (string) ($order['client_name'] ?? 'Client'),
            'client_phone' => (string) ($order['client_phone'] ?? ''),
            'client_adress' => (string) ($order['client_adress'] ?? ''),
            'client_note' => (string) ($order['client_note'] ?? ''),
            'manager_note' => (string) ($order['manager_note'] ?? ''),
            'product_name' => (string) ($order['product_name'] ?? 'Produit'),
            'quantity' => (int) ($order['quantity'] ?? 1),
            'total_price' => (int) ($order['total_price'] ?? 0),
            'unit_price' => (float) ($order['unit_price'] ?? 0),
            'newstat' => (string) ($order['newstat'] ?? 'new'),
            'created_at' => $order['created_at'] ?? date('Y-m-d H:i:s'),
            'updated_at' => $order['updated_at'] ?? ($order['created_at'] ?? date('Y-m-d H:i:s')),
        ];
    }

    echo json_encode([
        'success'   => true,
        'new_count' => $newCount,
        'last_id'   => $maxId,
        'orders'    => $formattedOrders,
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des notifications.',
    ]);
}
