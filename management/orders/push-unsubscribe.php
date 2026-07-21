<?php
/**
 * LUXEMARKET Push Notifications - Unsubscribe Endpoint
 * Supprime l'abonnement aux notifications push
 */

require '../../vendor/autoload.php';
require '../../utils/middleware.php';

use src\Connectbd;

// Vérifier la connexion
verifyConnection("/management/orders/");

$cnx = Connectbd::getConnection();

header('Content-Type: application/json');

// Récupérer les données POST
$input = json_decode(file_get_contents('php://input'), true);

$userId = $_SESSION['user_id'] ?? null;

// Vérifier que l'utilisateur est connecté
if (!$userId) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'User not authenticated'
    ]);
    exit;
}

// Si un endpoint est fourni, supprimer cet abonnement spécifique
if (isset($input['endpoint']) && !empty($input['endpoint'])) {
    $stmt = $cnx->prepare("DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?");
    $stmt->execute([$userId, $input['endpoint']]);
} else {
    // Sinon supprimer tous les abonnements de l'utilisateur
    $stmt = $cnx->prepare("DELETE FROM push_subscriptions WHERE user_id = ?");
    $stmt->execute([$userId]);
}

echo json_encode([
    'success' => true,
    'userId' => $userId
]);
?>