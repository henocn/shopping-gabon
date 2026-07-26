<?php
/**
 * LUXEMARKET - Test d'envoi de notification push
 * Accédez à cette page depuis le navigateur pour envoyer une notification test.
 * IMPORTANT : Supprimez ce fichier après le test en production !
 */

require_once __DIR__ . '/../../utils/admin-session.php';
startAdminSession();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../utils/middleware.php';

use src\Connectbd;
use src\PushNotification;

verifyConnection("/management/orders/");
checkAdminAccess($_SESSION['user_id']);

$cnx = Connectbd::getConnection();

// Vérifier les abonnements existants
$stmt = $cnx->query("SELECT COUNT(*) FROM push_subscriptions");
$subCount = $stmt ? $stmt->fetchColumn() : 0;

// Vérifier la config VAPID
$vapid = include __DIR__ . '/../../config/vapid.php';
$vapidOk = is_array($vapid) && !empty($vapid['publicKey']) && !empty($vapid['privateKey']);

$result = null;
$error = null;

if (isset($_POST['send_test'])) {
    try {
        verifyCsrfToken();
        $push = new PushNotification($cnx);
        $push->notifyNewOrder('Client Test', 'Produit Test', 5000);
        $result = 'Notification envoyée avec succès à ' . $subCount . ' abonné(s) !';
    } catch (\Throwable $e) {
        $error = 'Erreur : ' . $e->getMessage();
    }
}

// Récupérer les abonnements pour debug
$subs = [];
try {
    $stmt = $cnx->query("SELECT id, user_id, LEFT(endpoint, 80) as endpoint_short, created_at FROM push_subscriptions ORDER BY created_at DESC LIMIT 10");
    if ($stmt) {
        $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (\Throwable $e) {
    // Table n'existe peut-être pas encore
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Push Notifications</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #1a1a2e; color: #fff; padding: 20px; font-family: sans-serif; }
        .card { background: #16213e; border: none; border-radius: 12px; padding: 24px; margin-bottom: 16px; }
        .badge-ok { background: #00c853; color: #fff; padding: 4px 10px; border-radius: 6px; font-size: 13px; }
        .badge-ko { background: #ff1744; color: #fff; padding: 4px 10px; border-radius: 6px; font-size: 13px; }
        table { width: 100%; font-size: 13px; }
        table td, table th { padding: 8px; border-bottom: 1px solid rgba(255,255,255,0.1); }
    </style>
</head>
<body>
    <div class="container" style="max-width: 700px;">
        <h1 class="mb-4">🔔 Test Push Notifications</h1>

        <?php if ($result): ?>
            <div class="alert alert-success"><?= htmlspecialchars($result) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <h5>Diagnostic</h5>
            <table>
                <tr>
                    <td>Clés VAPID configurées</td>
                    <td><?= $vapidOk ? '<span class="badge-ok">OK</span>' : '<span class="badge-ko">NON</span>' ?></td>
                </tr>
                <tr>
                    <td>Bibliothèque web-push</td>
                    <td><?= class_exists('Minishlink\WebPush\WebPush') ? '<span class="badge-ok">OK</span>' : '<span class="badge-ko">MANQUANTE</span>' ?></td>
                </tr>
                <tr>
                    <td>Abonnements push actifs</td>
                    <td><strong><?= $subCount ?></strong> abonné(s)</td>
                </tr>
            </table>
        </div>

        <?php if ($subCount > 0): ?>
        <div class="card">
            <h5>Abonnements récents</h5>
            <table>
                <tr><th>ID</th><th>User</th><th>Endpoint (tronqué)</th><th>Date</th></tr>
                <?php foreach ($subs as $sub): ?>
                <tr>
                    <td><?= $sub['id'] ?></td>
                    <td><?= $sub['user_id'] ?></td>
                    <td style="font-size:11px; word-break:break-all;"><?= htmlspecialchars($sub['endpoint_short']) ?>…</td>
                    <td><?= $sub['created_at'] ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>

        <div class="card">
            <h5>Envoyer une notification test</h5>
            <p class="text-muted small">
                Ceci enverra une vraie notification push à tous les abonnés.<br>
                <strong>Fermez l'onglet/l'app après avoir cliqué</strong> pour vérifier que la notif arrive quand même.
            </p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" name="send_test" class="btn btn-primary btn-lg w-100" <?= $subCount === 0 ? 'disabled' : '' ?>>
                    🔔 Envoyer une notification test
                </button>
            </form>
            <?php if ($subCount === 0): ?>
                <p class="text-warning mt-2 small">⚠️ Aucun abonnement trouvé. Ouvrez d'abord la page <a href="index.php" class="text-info">Gestion des commandes</a> et acceptez les notifications.</p>
            <?php endif; ?>
        </div>

        <a href="index.php" class="btn btn-outline-light mt-3">← Retour aux commandes</a>
    </div>
</body>
</html>
