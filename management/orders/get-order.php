<?php

require '../../vendor/autoload.php';
require '../../utils/middleware.php';

use src\Connectbd;
use src\Order;

verifyConnection("/management/orders/");
checkIsActive($_SESSION['user_id']);

header('Content-Type: application/json');

try {
    $orderId = isset($_GET['id']) && is_scalar($_GET['id'])
        ? filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;
    if ($orderId === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Identifiant de commande invalide.']);
        exit;
    }

    $cnx = Connectbd::getConnection();
    $orderManager = new Order($cnx);
    $order = $orderManager->getOrderDetailsById($orderId);

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Commande introuvable.']);
        exit;
    }

    $role = isset($_SESSION['role']) ? (int) $_SESSION['role'] : 0;
    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

    // Un assistant ne peut récupérer que ses propres commandes.
    if ($role !== 1 && (int) $order['manager_id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accès refusé à cette commande.']);
        exit;
    }

    echo json_encode(['success' => true, 'order' => $order]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération de la commande.']);
}
