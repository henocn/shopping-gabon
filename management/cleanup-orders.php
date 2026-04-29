<?php
require '../vendor/autoload.php';
require '../utils/middleware.php';

verifyConnection("/management/");
checkAdminAccess($_SESSION['user_id']);
checkIsActive($_SESSION['user_id']);

use src\Connectbd;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupérer et valider la date limite
$daysAgo = isset($_POST['days_ago']) ? (int)$_POST['days_ago'] : null;

if ($daysAgo === null || $daysAgo < 1 || $daysAgo > 365) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nombre de jours invalide (1-365)']);
    exit;
}

try {
    $cnx = Connectbd::getConnection();

    // Préparation de la requête sécurisée
    $sql = "
        DELETE FROM orders 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY) 
          AND newstat NOT IN ('deliver', 'processing')
        LIMIT 10000
    ";

    $stmt = $cnx->prepare($sql);
    $result = $stmt->execute([':days' => $daysAgo]);

    $deletedRows = $stmt->rowCount();

    if ($result) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $deletedRows . ' commande(s) supprimée(s)',
            'deleted_count' => $deletedRows
        ]);
    } else {
        throw new \Exception('Erreur lors de la suppression');
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur : ' . $e->getMessage()
    ]);
}
