<?php

require '../../vendor/autoload.php';
require '../../utils/middleware.php';

use src\Connectbd;
use src\Order;

verifyConnection("/management/orders/");
checkIsActive($_SESSION['user_id']);

header('Content-Type: application/json');

const PAGE_SIZE = 200;

const TAB_STATUSES = [
    'to-process'  => ['new', 'remind'],
    'unreachable' => ['unreachable'],
    'processing'  => ['processing'],
];

try {
    $tab = isset($_GET['tab']) && is_string($_GET['tab']) ? $_GET['tab'] : '';
    $offset = isset($_GET['offset']) && is_scalar($_GET['offset'])
        ? filter_var($_GET['offset'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])
        : 0;
    if ($offset === false) {
        $offset = 0;
    }

    if (!isset(TAB_STATUSES[$tab])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Onglet invalide.']);
        exit;
    }

    $cnx = Connectbd::getConnection();
    $orderManager = new Order($cnx);

    $role = isset($_SESSION['role']) ? (int) $_SESSION['role'] : 0;
    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    $statuses = TAB_STATUSES[$tab];

    if ($role === 1) {
        $orders = $orderManager->getOrdersByStatuses($statuses, PAGE_SIZE, $offset);
    } else {
        $orders = $orderManager->getOrdersByStatusesAndUserId($statuses, $userId, PAGE_SIZE, $offset);
    }

    $formattedOrders = array_map(function ($order) {
        return [
            'order_id'              => (int) $order['order_id'],
            'client_name'           => (string) ($order['client_name'] ?? 'Client'),
            'client_phone'          => (string) ($order['client_phone'] ?? ''),
            'client_adress'         => (string) ($order['client_adress'] ?? ''),
            'client_note'           => (string) ($order['client_note'] ?? ''),
            'manager_note'          => (string) ($order['manager_note'] ?? ''),
            'product_name'          => (string) ($order['product_name'] ?? 'Produit'),
            'quantity'              => (int) ($order['quantity'] ?? 1),
            'total_price'           => (int) ($order['total_price'] ?? 0),
            'unit_price'            => (float) ($order['unit_price'] ?? 0),
            'newstat'               => (string) ($order['newstat'] ?? 'new'),
            'assistant_name'        => (string) ($order['assistant_name'] ?? '—'),
            'created_at'            => $order['created_at'] ?? date('Y-m-d H:i:s'),
            'updated_at'            => $order['updated_at'] ?? ($order['created_at'] ?? date('Y-m-d H:i:s')),
        ];
    }, $orders);

    echo json_encode([
        'success'  => true,
        'orders'   => $formattedOrders,
        'has_more' => count($formattedOrders) === PAGE_SIZE,
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors du chargement des commandes.']);
}
