<?php
require '../../vendor/autoload.php';
require '../../utils/middleware.php';

verifyConnection("/management/orders/");
checkIsActive($_SESSION['user_id']);

use src\Connectbd;
use src\Order;

$cnx = Connectbd::getConnection();
$orderManager = new Order($cnx);

$deliveredOrders = [];
$totalRevenue = 0;

if (isset($_SESSION['role']) && isset($_SESSION['user_id'])) {
      if ((int)$_SESSION['role'] === 1) {
            // Admin : voir toutes les commandes livrées
            $deliveredOrders = $orderManager->getAllDeliveredOrders();
      } else {
            // Assistant : voir uniquement ses commandes livrées
            $deliveredOrders = $orderManager->getDeliveredOrdersByUserId((int)$_SESSION['user_id']);
      }
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Archives des Commandes Livrées</title>
      <link rel="preload" href="https://unpkg.com/boxicons@2.1.4/fonts/boxicons.woff2" as="font" type="font/woff2" crossorigin>
      <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
      <link href="../../assets/css/index.css" rel="stylesheet">
      <link href="../../assets/css/admin.css" rel="stylesheet">
      <link href="../../assets/css/navbar.css" rel="stylesheet">
      <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
      <?php include '../../includes/pwa-head.php'; ?>
      <style>
          .pwa-install-banner {
              position: fixed;
              top: 0;
              left: 0;
              right: 0;
              z-index: 9999;
              background: linear-gradient(135deg, #1a1a2e, #16213e);
              color: #fff;
              padding: 12px 16px;
              box-shadow: 0 2px 12px rgba(0,0,0,0.3);
              animation: slideDown 0.3s ease-out;
          }
          @keyframes slideDown {
              from { transform: translateY(-100%); }
              to { transform: translateY(0); }
          }
          .pwa-install-content {
              display: flex;
              align-items: center;
              gap: 12px;
              max-width: 1200px;
              margin: 0 auto;
          }
          .pwa-install-icon {
              width: 40px;
              height: 40px;
              border-radius: 8px;
              flex-shrink: 0;
          }
          .pwa-install-text {
              flex: 1;
              min-width: 0;
              line-height: 1.3;
          }
          .pwa-install-text strong {
              display: block;
              font-size: 14px;
          }
          .pwa-install-text span {
              display: block;
              font-size: 12px;
              opacity: 0.8;
          }
          .pwa-install-content .btn-success {
              flex-shrink: 0;
              font-weight: 600;
              padding: 6px 16px;
              border-radius: 6px;
          }
          .pwa-install-content .btn-close {
              flex-shrink: 0;
              opacity: 0.7;
              filter: brightness(0) invert(1);
          }
          body.pwa-banner-shown {
              padding-top: 64px;
          }
      </style>
</head>

<body>
      <div id="pwa-install-banner" class="pwa-install-banner d-none">
          <div class="pwa-install-content">
              <img src="/assets/icons/icon-192x192.png" alt="LUXEMARKET" class="pwa-install-icon">
              <div class="pwa-install-text">
                  <strong>Installer l'application</strong>
                  <span>Gérez vos commandes plus rapidement</span>
              </div>
              <button type="button" id="pwa-install-btn" class="btn btn-sm btn-success">Installer</button>
              <button type="button" id="pwa-install-dismiss" class="btn-close btn-close-white" aria-label="Fermer"></button>
          </div>
      </div>

      <?php include '../../includes/navbar.php'; ?>

      <main class="container-fluid my-4">

            <!-- En-tête avec bouton retour -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                  <h4>Livraisons archives : <?= count($deliveredOrders) ?> </h4>
                  <a href="index.php" class="btn btn-order-primary border-1 border-black rounded-3">
                        <i class='bx bx-arrow-back me-2'></i>Retour
                  </a>
            </div>
            <!-- Tableau des commandes livrées -->
            <?php if (empty($deliveredOrders)): ?>
                  <div class="text-center py-5">
                        <i class='bx bx-box' style='font-size: 64px; color: #ccc;'></i>
                        <p class="text-muted mt-3">Aucune commande livrée pour le moment.</p>
                  </div>
            <?php else: ?>
                  <div class="table-responsive">
                        <table class="table table-bordered" id="orders-delivered-table">
                              <thead>
                                    <tr>
                                          <th scope="col">ID</th>
                                          <th scope="col">Client</th>
                                          <th scope="col">Produit</th>
                                          <th scope="col">Quantité</th>
                                          <th scope="col">Total</th>
                                          <th scope="col">Statut</th>
                                          <th scope="col">Date</th>
                                    </tr>
                              </thead>
                              <tbody>
                                    <?php foreach ($deliveredOrders as $order): ?>
                                          <tr>
                                                <td><strong>#<?= htmlspecialchars($order['order_id']) ?></strong></td>
                                                <td><?= htmlspecialchars($order['client_name']) ?></td>
                                                <td>
                                                      <?= htmlspecialchars($order['product_name']) ?>
                                                      <?php if (!empty($order['pack_name'])): ?>
                                                            <br><small class="text-muted">(<?= htmlspecialchars($order['pack_name']) ?>)</small>
                                                      <?php endif; ?>
                                                </td>
                                                <td><?= (int)$order['quantity'] ?></td>
                                                <td><strong><?= number_format($order['total_price'], 0, ',', ' ') ?> FCFA</strong></td>
                                                <td><span class="badge bg-success">Livré</span></td>
                                                <td><?= date('d/m/Y H:i', strtotime($order['updated_at'])) ?></td>
                                          </tr>
                                    <?php endforeach; ?>
                              </tbody>
                        </table>
                  </div>
            <?php endif; ?>

      </main>

      <?php include '../../includes/footer.php'; ?>

      <script src="../../assets/js/bootstrap.bundle.min.js"></script>

      <?php include '../../includes/push-notifications-init.php'; ?>
      <?php include '../../includes/pwa-script.php'; ?>
</body>

</html>