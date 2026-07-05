<?php
require '../../vendor/autoload.php';
require '../../utils/middleware.php';

verifyConnection("/management/orders/");
checkIsActive($_SESSION['user_id']);

use src\Connectbd;
use src\Order;

$cnx = Connectbd::getConnection();
$orderManager = new Order($cnx);

$groupedOrders = [
      'to-process' => [],
      'unreachable' => [],
      'processing' => [],
      'delivered' => []
];

$ordersForModals = [];

if (isset($_SESSION['role']) && isset($_SESSION['user_id'])) {
      if ((int)$_SESSION['role'] === 1) {
            $groupedOrders['to-process'] = $orderManager->getOrdersByStatuses(['new', 'remind']);
            $groupedOrders['unreachable'] = $orderManager->getOrdersByStatuses(['unreachable']);
            $groupedOrders['processing'] = $orderManager->getOrdersByStatuses(['processing']);
            $groupedOrders['delivered'] = $orderManager->getOrdersToDay();
      } else {
            $managerId = (int)$_SESSION['user_id'];
            $groupedOrders['to-process'] = $orderManager->getOrdersByStatusesAndUserId(['new', 'remind'], $managerId);
            $groupedOrders['unreachable'] = $orderManager->getOrdersByStatusesAndUserId(['unreachable'], $managerId);
            $groupedOrders['processing'] = $orderManager->getOrdersByStatusesAndUserId(['processing'], $managerId);
            $groupedOrders['delivered'] = $orderManager->getOrdersToDayByUserId($managerId);
      }

      $ordersForModals = array_merge(
            $groupedOrders['to-process'],
            $groupedOrders['unreachable'],
            $groupedOrders['processing']
      );
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Gestion des Commandes</title>
      <link rel="preload" href="https://unpkg.com/boxicons@2.1.4/fonts/boxicons.woff2" as="font" type="font/woff2" crossorigin>
      <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
      <link href="../../assets/css/index.css" rel="stylesheet">
      <link href="../../assets/css/admin.css" rel="stylesheet">
      <link href="../../assets/css/navbar.css" rel="stylesheet">
      <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>

<body>

      <?php include '../../includes/navbar.php'; ?>

      <main class="container-fluid my-4">

            <!-- En-tête avec bouton Archives et bannière notifications push -->
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                  <h4 class="mb-0">Gestion des Commandes</h4>
                  <div class="d-flex align-items-center gap-2">
                        <div id="push-notif-banner" class="d-none align-items-center gap-2 py-1 px-2 rounded bg-light border">
                              <span class="small text-muted">Recevoir les notifications push pour les nouvelles commandes</span>
                              <button type="button" id="push-enable-btn" class="btn btn-order-primary btn-sm">Activer</button>
                        </div>
                        <a href="archive.php" class="btn btn-order-primary border-1 border-black rounded-3">
                              <i class='bx bx-archive me-2'></i> Archivées
                        </a>
                  </div>
            </div>

            <!-- Navigation par onglets -->
            <ul class="nav nav-tabs" id="ordersTabs" role="tablist">
                  <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-to-process" data-bs-toggle="tab" data-bs-target="#pane-to-process" type="button" role="tab">
                              <i class='bx bx-time-five me-2'></i>A traiter
                              <span class="badge bg-primary ms-2"><?= count($groupedOrders['to-process']) ?></span>
                        </button>
                  </li>
                  <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-unreachable" data-bs-toggle="tab" data-bs-target="#pane-unreachable" type="button" role="tab">
                              <i class='bx bx-phone-off me-2'></i>Injoignable
                              <span class="badge bg-danger ms-2"><?= count($groupedOrders['unreachable']) ?></span>
                        </button>
                  </li>
                  <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-processing" data-bs-toggle="tab" data-bs-target="#pane-processing" type="button" role="tab">
                              <i class='bx bx-calendar-check me-2'></i>Programmer
                              <span class="badge bg-warning ms-2"><?= count($groupedOrders['processing']) ?></span>
                        </button>
                  </li>
                  <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-delivered" data-bs-toggle="tab" data-bs-target="#pane-delivered" type="button" role="tab">
                              <i class='bx bx-check-circle me-2'></i>Livrer aujourd'hui
                              <span class="badge bg-success ms-2"><?= count($groupedOrders['delivered']) ?></span>
                        </button>
                  </li>
            </ul>

            <!-- Contenu des onglets -->
            <div class="tab-content" id="ordersTabsContent">
                  <!-- Onglet À traiter -->
                  <div class="tab-pane fade show active" id="pane-to-process" role="tabpanel">
                        <div class="row">
                              <div class="col-12">
                                  
                                  
                                  <!-- Champ de recherche/filtrage compact -->
                                    <div class="card mb-3 search-compact">
                                          <div class="card-body p-2">
                                                <div class="row g-2 align-items-end">
                                                      <div class="col-md-6">
                                                            <input type="text" class="form-control form-control-sm" id="searchInput" placeholder="🔍 Rechercher par nom, téléphone ou produit...">
                                                      </div>
                                                      <div class="col-md-4">
                                                            <select class="form-select form-select-sm" id="statusFilter">
                                                                  <option value="all">Tous</option>
                                                                  <option value="new">Nouvelles</option>
                                                                  <option value="remind">Rappeler</option>
                                                            </select>
                                                      </div>
                                                      <div class="col-md-2 text-end">
                                                            <span class="badge bg-secondary" id="order-count"><?= count($groupedOrders['to-process']) ?></span>
                                                            <small class="text-muted ms-1">résultats</small>
                                                      </div>
                                                </div>
                                          </div>
                                    </div>
                                    
                                    
                                    <?php if (empty($groupedOrders['to-process'])): ?>
                                          <p class="text-muted">Aucune commande à traiter.</p>
                                    <?php else: ?>
                                          <div class="table-responsive">
                                                <table class="table table-bordered" id="orders-table">
                                                      <thead>
                                                            <tr>
                                                                  <th scope="col">ID</th>
                                                                  <th scope="col">Client</th>
                                                                  <th scope="col">Contact</th>
                                                                  <th scope="col">Adresse</th>
                                                                  <th scope="col">Note client</th>
                                                                  <th scope="col">Produit</th>
                                                                  <th scope="col">Qt</th>
                                                                  <th scope="col">Prix Total</th>
                                                                  <th scope="col">Notes</th>
                                                                  <th scope="col">Actions</th>
                                                                  <th scope="col">Date</th>
                                                            </tr>
                                                      </thead>
                                                      <tbody>
                                                            <?php foreach ($groupedOrders['to-process'] as $order):
                                                                  $statusClass = 'order-row-default';
                                                                  switch ($order['newstat']) {
                                                                        case 'unreachable':
                                                                              $statusClass = 'order-row-unreachable';
                                                                              break;
                                                                        case 'remind':
                                                                              $statusClass = 'order-row-remind';
                                                                              break;
                                                                        case 'processing':
                                                                              $statusClass = 'order-row-processing';
                                                                              break;
                                                                  }
                                                            ?>
                                                                  <tr class="order-row <?= $statusClass ?>"
                                                                        data-order-id="<?= (int)$order['order_id'] ?>"
                                                                        data-status="<?= $order['newstat'] ?>"
                                                                        data-client="<?= htmlspecialchars(strtolower($order['client_name'])) ?>"
                                                                        data-phone="<?= htmlspecialchars($order['client_phone']) ?>"
                                                                        data-product="<?= htmlspecialchars(strtolower($order['product_name'])) ?>">
                                                                        <td>#<?= htmlspecialchars($order['order_id']) ?></td>
                                                                        <td class="client-name-cell" title="<?= htmlspecialchars($order['client_name']) ?>"><?= htmlspecialchars($order['client_name']) ?></td>
                                                                        <td><?= htmlspecialchars($order['client_phone']) ?></td>
                                                                                                                                                                                                                        <td class="note-cell" title="<?= htmlspecialchars($order['client_adress'] ?? '') ?>"><?= htmlspecialchars(trim((string)($order['client_adress'] ?? '')) !== '' ? $order['client_adress'] : '—') ?></td>
                                                                          <td class="note-cell" title="<?= htmlspecialchars($order['client_note'] ?? '') ?>"><?= htmlspecialchars(trim((string)($order['client_note'] ?? '')) !== '' ? $order['client_note'] : '—') ?></td>
                                                                        <td class="product-name-cell" title="<?= htmlspecialchars($order['product_name']) ?>"><?= htmlspecialchars($order['product_name']) ?></td>
                                                                        <td><?= (int)$order['quantity'] ?></td>
                                                                        <td><?= number_format($order['total_price'], 0, ',', ' ') ?> F</td>
                                                                        <td class="note-cell" title="<?= htmlspecialchars($order['manager_note'] ?? '') ?>"><?= htmlspecialchars($order['manager_note'] ?? '') ?></td>
                                                                        <td>
                                                                              <?php if ($order['newstat'] === 'processing'): ?>
                                                                                    <!-- Boutons directs pour les commandes programmées -->
                                                                                    <div class="order-action-group">
                                                                                          <form method="POST" action="save.php" id="quickDeliverForm<?= $order['order_id'] ?>">
                                                                                                <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                                                                                <input type="hidden" name="quantity" value="<?= $order['quantity'] ?>">
                                                                                                <input type="hidden" name="total_price" value="<?= $order['total_price'] ?>">
                                                                                                <input type="hidden" name="newstat" value="deliver">
                                                                                                <input type="hidden" name="manager_note" value="<?= htmlspecialchars($order['manager_note'] ?? '') ?>">
                                                                                                <input type="hidden" name="updated_at" value="<?= date('Y-m-d H:i:s') ?>">
                                                                                                <input type="hidden" name="valider" value="update">
                                                                                                <input type="hidden" name="delivery_fee" value="0">
                                                                                                <button type="button" class="btn btn-success btn-sm quick-deliver-btn" data-order-id="<?= $order['order_id'] ?>" title="Livrer">
                                                                                                      <i class='bx bx-check'></i>
                                                                                                      <span>Livrer</span>
                                                                                                </button>
                                                                                          </form>
                                                                                          <form method="POST" action="save.php" data-confirm="Annuler cette commande ?">
                                                                                                <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                                                                                <input type="hidden" name="quantity" value="<?= $order['quantity'] ?>">
                                                                                                <input type="hidden" name="total_price" value="<?= $order['total_price'] ?>">
                                                                                                <input type="hidden" name="newstat" value="canceled">
                                                                                                <input type="hidden" name="manager_note" value="<?= htmlspecialchars($order['manager_note'] ?? '') ?>">
                                                                                                <input type="hidden" name="updated_at" value="<?= date('Y-m-d H:i:s') ?>">
                                                                                                <input type="hidden" name="valider" value="update">
                                                                                                <button type="submit" class="btn btn-danger btn-sm" title="Annuler">
                                                                                                      <i class='bx bx-x'></i>
                                                                                                      <span>Annuler</span>
                                                                                                </button>
                                                                                          </form>
                                                                                    </div>
                                                                              <?php else: ?>
                                                                                    <!-- Bouton modal pour traiter la commande (autres statuts) -->
                                                                                    <button class="btn btn-order-primary btn-sm" type="button"
                                                                                          data-bs-toggle="modal"
                                                                                          data-bs-target="#orderModal<?= (int)$order['order_id'] ?>"
                                                                                          title="Traiter"
                                                                                          aria-label="Traiter">
                                                                                          <i class='bx bx-edit-alt'></i>
                                                                                    </button>
                                                                              <?php endif; ?>
                                                                        </td>
                                                                        <td><?= date('d/m/Y à H:i', strtotime($order['created_at'])) ?></td>
                                                                  </tr>
                                                            <?php endforeach; ?>
                                                      </tbody>
                                                </table>
                                          </div>
                                    <?php endif; ?>
                              </div>
                        </div>
                  </div>

                  <!-- Onglet Injoignable -->
                  <div class="tab-pane fade" id="pane-unreachable" role="tabpanel">
                        <div class="row">
                              <div class="col-12">
                                  
                                  <!-- Champ de recherche -->
                                    <div class="card mb-3 search-compact">
                                          <div class="card-body p-2">
                                                <div class="row g-2 align-items-end">
                                                      <div class="col-md-12">
                                                            <input type="text" class="form-control form-control-sm" id="searchInputUnreachable" placeholder="🔍 Rechercher par nom, téléphone ou produit...">
                                                      </div>
                                                </div>
                                          </div>
                                    </div>
                                    
                                    <?php if (empty($groupedOrders['unreachable'])): ?>
                                          <p class="text-muted">Aucune commande injoignable.</p>
                                    <?php else: ?>
                                          <div class="table-responsive">
                                                <table class="table table-bordered" id="orders-table">
                                                      <thead>
                                                            <tr>
                                                                  <th scope="col">ID</th>
                                                                  <th scope="col">Client</th>
                                                                  <th scope="col">Contact</th>
                                                                  <th scope="col">Adresse</th>
                                                                  <th scope="col">Note client</th>
                                                                  <th scope="col">Produit</th>
                                                                  <th scope="col">Qt</th>
                                                                  <th scope="col">Prix Total</th>
                                                                  <th scope="col">Notes</th>
                                                                  <th scope="col">Actions</th>
                                                                  <th scope="col">Date</th>
                                                            </tr>
                                                      </thead>
                                                      <tbody>
                                                            <?php foreach ($groupedOrders['unreachable'] as $order): ?>
                                                                  <tr class="order-row order-row-unreachable"
                                                                        data-order-id="<?= (int)$order['order_id'] ?>"
                                                                        data-status="<?= $order['newstat'] ?>"
                                                                        data-client="<?= htmlspecialchars(strtolower($order['client_name'])) ?>"
                                                                        data-phone="<?= htmlspecialchars($order['client_phone']) ?>"
                                                                        data-product="<?= htmlspecialchars(strtolower($order['product_name'])) ?>">
                                                                        <td>#<?= htmlspecialchars($order['order_id']) ?></td>
                                                                        <td class="client-name-cell" title="<?= htmlspecialchars($order['client_name']) ?>"><?= htmlspecialchars($order['client_name']) ?></td>
                                                                        <td><?= htmlspecialchars($order['client_phone']) ?></td>
                                                                                                                                                                                                                        <td class="note-cell" title="<?= htmlspecialchars($order['client_adress'] ?? '') ?>"><?= htmlspecialchars(trim((string)($order['client_adress'] ?? '')) !== '' ? $order['client_adress'] : '—') ?></td>
                                                                          <td class="note-cell" title="<?= htmlspecialchars($order['client_note'] ?? '') ?>"><?= htmlspecialchars(trim((string)($order['client_note'] ?? '')) !== '' ? $order['client_note'] : '—') ?></td>
                                                                        <td class="product-name-cell" title="<?= htmlspecialchars($order['product_name']) ?>"><?= htmlspecialchars($order['product_name']) ?></td>
                                                                        <td><?= (int)$order['quantity'] ?></td>
                                                                        <td><?= number_format($order['total_price'], 0, ',', ' ') ?> F</td>
                                                                        <td class="note-cell" title="<?= htmlspecialchars($order['manager_note'] ?? '') ?>"><?= htmlspecialchars($order['manager_note'] ?? '') ?></td>
                                                                        <td>
                                                                              <button class="btn btn-order-primary btn-sm" type="button"
                                                                                    data-bs-toggle="modal"
                                                                                    data-bs-target="#orderModal<?= (int)$order['order_id'] ?>"
                                                                                    title="Traiter"
                                                                                    aria-label="Traiter">
                                                                                    <i class='bx bx-edit-alt'></i>
                                                                              </button>
                                                                        </td>
                                                                        <td><?= date('d/m/Y à H:i', strtotime($order['created_at'])) ?></td>
                                                                  </tr>
                                                            <?php endforeach; ?>
                                                      </tbody>
                                                </table>
                                          </div>
                                    <?php endif; ?>
                              </div>
                        </div>
                  </div>

                  <!-- Onglet Programmer -->
                  <div class="tab-pane fade" id="pane-processing" role="tabpanel">
                        <div class="row">
                              <div class="col-12">
                                  
                                  <!-- Champ de recherche -->
                                    <div class="card mb-3 search-compact">
                                          <div class="card-body p-2">
                                                <div class="row g-2 align-items-end">
                                                      <div class="col-md-12">
                                                            <input type="text" class="form-control form-control-sm" id="searchInputProcessing" placeholder="🔍 Rechercher par nom, téléphone ou produit...">
                                                      </div>
                                                </div>
                                          </div>
                                    </div>
                                    
                                    <?php if (empty($groupedOrders['processing'])): ?>
                                          <p class="text-muted">Aucune commande programmée.</p>
                                    <?php else: ?>
                                          <div class="table-responsive">
                                                <table class="table table-bordered" id="orders-table">
                                                      <thead>
                                                            <tr>
                                                                  <th scope="col">ID</th>
                                                                  <th scope="col">Client</th>
                                                                  <th scope="col">Contact</th>
                                                                  <th scope="col">Adresse</th>
                                                                  <th scope="col">Note client</th>
                                                                  <th scope="col">Produit</th>
                                                                  <th scope="col">Qt</th>
                                                                  <!-- <th scope="col">Prix Unit.</th> -->
                                                                  <th scope="col">Prix Total</th>
                                                                  <th scope="col">Notes</th>
                                                                  <th scope="col">Actions</th>
                                                                  <th scope="col">Date</th>
                                                            </tr>
                                                      </thead>
                                                      <tbody>
                                                            <?php foreach ($groupedOrders['processing'] as $order): ?>
                                                                  <tr class="order-row order-row-processing"
                                                                        data-order-id="<?= (int)$order['order_id'] ?>"
                                                                        data-status="<?= $order['newstat'] ?>"
                                                                        data-client="<?= htmlspecialchars(strtolower($order['client_name'])) ?>"
                                                                        data-phone="<?= htmlspecialchars($order['client_phone']) ?>"
                                                                        data-product="<?= htmlspecialchars(strtolower($order['product_name'])) ?>">
                                                                        <td>#<?= htmlspecialchars($order['order_id']) ?></td>
                                                                        <td class="client-name-cell" title="<?= htmlspecialchars($order['client_name']) ?>"><?= htmlspecialchars($order['client_name']) ?></td>
                                                                        <td><?= htmlspecialchars($order['client_phone']) ?></td>
                                                                                                                                                                                                                        <td class="note-cell" title="<?= htmlspecialchars($order['client_adress'] ?? '') ?>"><?= htmlspecialchars(trim((string)($order['client_adress'] ?? '')) !== '' ? $order['client_adress'] : '—') ?></td>
                                                                          <td class="note-cell" title="<?= htmlspecialchars($order['client_note'] ?? '') ?>"><?= htmlspecialchars(trim((string)($order['client_note'] ?? '')) !== '' ? $order['client_note'] : '—') ?></td>
                                                                        <td class="product-name-cell" title="<?= htmlspecialchars($order['product_name']) ?>"><?= htmlspecialchars($order['product_name']) ?></td>
                                                                        <td><?= (int)$order['quantity'] ?></td>
                                                                        <!-- <td><?= number_format($order['unit_price'] ?? 0, 0, ',', ' ') ?> F</td> -->
                                                                        <td><?= number_format($order['total_price'], 0, ',', ' ') ?> F</td>
                                                                        <td class="note-cell" title="<?= htmlspecialchars($order['manager_note'] ?? '') ?>"><?= htmlspecialchars($order['manager_note'] ?? '') ?></td>
                                                                        <td>
                                                                              <div class="order-action-group">
                                                                                    <form method="POST" action="save.php" id="quickDeliverForm<?= $order['order_id'] ?>">
                                                                                          <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                                                                          <input type="hidden" name="quantity" value="<?= $order['quantity'] ?>">
                                                                                          <input type="hidden" name="total_price" value="<?= $order['total_price'] ?>">
                                                                                          <input type="hidden" name="newstat" value="deliver">
                                                                                          <input type="hidden" name="manager_note" value="<?= htmlspecialchars($order['manager_note'] ?? '') ?>">
                                                                                          <input type="hidden" name="updated_at" value="<?= date('Y-m-d H:i:s') ?>">
                                                                                          <input type="hidden" name="valider" value="update">
                                                                                          <input type="hidden" name="delivery_fee" value="0">
                                                                                          <button type="button" class="btn btn-success btn-sm quick-deliver-btn" data-order-id="<?= $order['order_id'] ?>" title="Livrer">
                                                                                                <i class='bx bx-check'></i>
                                                                                                <span>Livrer</span>
                                                                                          </button>
                                                                                    </form>
                                                                                    <form method="POST" action="save.php" data-confirm="Annuler cette commande ?">
                                                                                          <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                                                                          <input type="hidden" name="quantity" value="<?= $order['quantity'] ?>">
                                                                                          <input type="hidden" name="total_price" value="<?= $order['total_price'] ?>">
                                                                                          <input type="hidden" name="newstat" value="canceled">
                                                                                          <input type="hidden" name="manager_note" value="<?= htmlspecialchars($order['manager_note'] ?? '') ?>">
                                                                                          <input type="hidden" name="updated_at" value="<?= date('Y-m-d H:i:s') ?>">
                                                                                          <input type="hidden" name="valider" value="update">
                                                                                          <button type="submit" class="btn btn-danger btn-sm" title="Annuler">
                                                                                                <i class='bx bx-x'></i>
                                                                                                <span>Annuler</span>
                                                                                          </button>
                                                                                    </form>
                                                                              </div>
                                                                        </td>
                                                                        <td><?= date('d/m/Y à H:i', strtotime($order['created_at'])) ?></td>
                                                                  </tr>
                                                            <?php endforeach; ?>
                                                      </tbody>
                                                </table>
                                          </div>
                                    <?php endif; ?>
                              </div>
                        </div>
                  </div>

                  <!-- Onglet Livrées aujourd'hui -->
                  <div class="tab-pane fade" id="pane-delivered" role="tabpanel">
                        <div class="row">
                              <div class="col-12">
                                  
                                  <!-- Champ de recherche -->
                                    <div class="card mb-3 search-compact">
                                          <div class="card-body p-2">
                                                <div class="row g-2 align-items-end">
                                                      <div class="col-md-12">
                                                            <input type="text" class="form-control form-control-sm" id="searchInputDelivered" placeholder="🔍 Rechercher par nom, téléphone ou produit...">
                                                      </div>
                                                </div>
                                          </div>
                                    </div>
                                    
                                    <?php if (empty($groupedOrders['delivered'])): ?>
                                          <p class="text-muted">Aucune commande livrée aujourd'hui.</p>
                                    <?php else: ?>
                                          <div class="table-responsive">
                                                <table class="table table-striped table-bordered" id="orders-delivered-table">
                                                      <thead>
                                                            <tr>
                                                                  <th>ID</th>
                                                                  <th>Client</th>
                                                                  <th>Adresse</th>
                                                                  <th>Note client</th>
                                                                  <th>Produit</th>
                                                                  <th>Qt</th>
                                                                  <th>Total</th>
                                                                  <th>Date</th>
                                                            </tr>
                                                      </thead>
                                                      <tbody>
                                                            <?php foreach ($groupedOrders['delivered'] as $order): ?>
                                                                  <tr data-order-id="<?= (int)$order['order_id'] ?>">
                                                                        <td>#<?= $order['order_id'] ?></td>
                                                                        <td class="client-name-cell" title="<?= htmlspecialchars($order['client_name']) ?>"><?= htmlspecialchars($order['client_name']) ?></td>
                                                                        <td class="note-cell" title="<?= htmlspecialchars($order['client_adress'] ?? '') ?>"><?= htmlspecialchars(trim((string)($order['client_adress'] ?? '')) !== '' ? $order['client_adress'] : '—') ?></td>
                                                                        <td class="note-cell" title="<?= htmlspecialchars($order['client_note'] ?? '') ?>"><?= htmlspecialchars(trim((string)($order['client_note'] ?? '')) !== '' ? $order['client_note'] : '—') ?></td>
                                                                        <td class="product-name-cell" title="<?= htmlspecialchars($order['product_name']) ?>"><?= htmlspecialchars($order['product_name']) ?></td>
                                                                        <td><?= $order['quantity'] ?></td>
                                                                        <td><?= number_format($order['total_price']) ?> F</td>
                                                                        <td><?= date('d/m/Y H:i', strtotime($order['updated_at'])) ?></td>
                                                                  </tr>
                                                            <?php endforeach; ?>
                                                      </tbody>
                                                </table>
                                          </div>
                                    <?php endif; ?>
                              </div>
                        </div>
                  </div>
            </div>

      </main>

      <div id="modals-container">
      <?php foreach ($ordersForModals as $order): ?>
            <?php $modalId = 'orderModal' . (int)$order['order_id']; ?>
            <div class="modal fade" id="<?= $modalId ?>" tabindex="-1" aria-labelledby="<?= $modalId ?>Label" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered admin-order-modal">
                        <div class="modal-content">
                              <div class="modal-header py-2">
                                    <h6 class="modal-title mb-0" id="<?= $modalId ?>Label">
                                          <i class='bx bx-edit-alt me-1'></i>
                                          Commande #<?= $order['order_id'] ?>
                                    </h6>
                                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal" aria-label="Fermer"></button>
                              </div>
                              <form action="save.php" method="POST" id="orderForm<?= $order['order_id'] ?>">
                                    <div class="modal-body py-2">
                                          <div class="order-modal-summary">
                                                <div class="d-flex flex-column flex-sm-row justify-content-between gap-1">
                                                      <span><strong><?= htmlspecialchars($order['client_name']) ?></strong> (<?= htmlspecialchars($order['client_phone']) ?>)</span>
                                                </div>
                                                <div class="mt-1"><span class="text-muted">Produit : <strong><?= htmlspecialchars($order['product_name']) ?></strong></span></div>
                                          </div>

                                          <div class="row g-2">
                                                <div class="col-12 col-md-4">
                                                      <div class="mb-2">
                                                            <label for="modalQuantity<?= $order['order_id'] ?>" class="form-label mb-1 small fw-bold">Quantité</label>
                                                            <input type="number" class="form-control form-control-sm" id="modalQuantity<?= $order['order_id'] ?>" name="quantity" value="<?= (int)$order['quantity'] ?>" min="1" required>
                                                      </div>
                                                </div>
                                                <div class="col-12 col-md-4">
                                                      <div class="mb-2">
                                                            <label class="form-label mb-1 small fw-bold">Prix unitaire (FCFA)</label>
                                                            <input type="text" class="form-control form-control-sm" value="<?= number_format($order['unit_price'] ?? 0, 0, ',', ' ') ?>" readonly>
                                                      </div>
                                                </div>
                                                <div class="col-12 col-md-4">
                                                      <div class="mb-2">
                                                            <label for="modalTotal<?= $order['order_id'] ?>" class="form-label mb-1 small fw-bold">Prix total (FCFA)</label>
                                                            <input type="number" class="form-control form-control-sm" id="modalTotal<?= $order['order_id'] ?>" name="total_price" value="<?= (int)$order['total_price'] ?>" min="0" required>
                                                      </div>
                                                </div>

                                                <div class="col-12">
                                                      <div class="mb-2">
                                                            <label for="actionSelect<?= $order['order_id'] ?>" class="form-label mb-1 small fw-bold">Action</label>
                                                            <select class="form-select form-select-sm" id="actionSelect<?= $order['order_id'] ?>" name="newstat" required>
                                                                  <?php
                                                                  $actions = [];
                                                                  switch ($order['newstat']) {
                                                                        case 'new':
                                                                        case 'unreachable':
                                                                              $actions = [
                                                                                    ['value' => 'deliver', 'label' => 'Livrer'],
                                                                                    ['value' => 'processing', 'label' => 'Programmer'],
                                                                                    ['value' => 'remind', 'label' => 'Rappeler'],
                                                                                    ['value' => 'unreachable', 'label' => 'Injoignable'],
                                                                                    ['value' => 'canceled', 'label' => 'Annuler']
                                                                              ];
                                                                              break;
                                                                        case 'remind':
                                                                              $actions = [
                                                                                    ['value' => 'deliver', 'label' => 'Livrer'],
                                                                                    ['value' => 'processing', 'label' => 'Programmer'],
                                                                                    ['value' => 'remind', 'label' => 'Rappeler'],
                                                                                    ['value' => 'unreachable', 'label' => 'Injoignable'],
                                                                                    ['value' => 'canceled', 'label' => 'Annuler']
                                                                              ];
                                                                              break;
                                                                        case 'processing':
                                                                              $actions = [
                                                                                    ['value' => 'deliver', 'label' => 'Livré'],
                                                                                    ['value' => 'canceled', 'label' => 'Annuler']
                                                                              ];
                                                                              break;
                                                                  }
                                                                  ?>
                                                                  <option value="" selected>-- Choisir une action --</option>
                                                                  <?php foreach ($actions as $action): ?>
                                                                        <option name="newstat" value="<?= $action['value'] ?>">
                                                                              <?= $action['label'] ?>
                                                                        </option>
                                                                  <?php endforeach; ?>
                                                            </select>
                                                            <div class="form-text mt-1">
                                                                  <small class="text-muted">
                                                                        Statut: <strong><?= ucfirst($order['newstat']) ?></strong>
                                                                  </small>
                                                            </div>
                                                      </div>
                                                </div>

                                                <div class="col-12">
                                                      <div class="mb-2">
                                                            <label for="modalManagerNote<?= $order['order_id'] ?>" class="form-label mb-1 small fw-bold">Note manager</label>
                                                            <textarea class="form-control form-control-sm" id="modalManagerNote<?= $order['order_id'] ?>" name="manager_note" rows="2" placeholder="Notes..."><?= htmlspecialchars($order['manager_note'] ?? '') ?></textarea>
                                                      </div>
                                                </div>
                                          </div>
                                    </div>
                                    <div class="modal-footer py-2">
                                          <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                          <input type="hidden" name="valider" value="update">
                                          <input type="hidden" name="updated_at" value="<?= date('Y-m-d H:i:s') ?>">
                                          <input type="hidden" name="delivery_fee" id="deliveryFee<?= $order['order_id'] ?>" value="0">
                                          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                                                <i class='bx bx-x me-1'></i>Annuler
                                          </button>
                                          <button type="button" class="btn btn-primary btn-sm" id="submitBtn<?= $order['order_id'] ?>">
                                                <i class='bx bx-save me-1'></i>Enregistrer
                                          </button>
                                    </div>
                              </form>
                        </div>
                  </div>
            </div>
      <?php endforeach; ?>

      <!-- Modal pour les frais de livraison -->
      <?php foreach ($ordersForModals as $order): ?>
            <div class="modal fade" id="deliveryFeeModal<?= $order['order_id'] ?>" tabindex="-1" aria-labelledby="deliveryFeeModalLabel<?= $order['order_id'] ?>" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                              <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title" id="deliveryFeeModalLabel<?= $order['order_id'] ?>">
                                          <i class='bx bx-package me-2'></i>Frais de Livraison
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                              </div>
                              <div class="modal-body">
                                    <p class="text-muted mb-3">Commande #<?= $order['order_id'] ?> - <?= htmlspecialchars($order['client_name']) ?></p>
                                    <div class="mb-3">
                                          <label for="deliveryFeeInput<?= $order['order_id'] ?>" class="form-label fw-bold">
                                                Frais de livraison (FCFA)
                                          </label>
                                          <input type="number"
                                                class="form-control form-control-lg"
                                                id="deliveryFeeInput<?= $order['order_id'] ?>"
                                                placeholder="Entrez les frais de livraison"
                                                min="0"
                                                value="0">
                                          <div class="form-text">
                                                <i class='bx bx-info-circle me-1'></i>
                                                Laissez 0 si aucun frais de livraison
                                          </div>
                                    </div>
                              </div>
                              <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                          <i class='bx bx-x me-2'></i>Annuler
                                    </button>
                                    <button type="button" class="btn btn-success" onclick="confirmDelivery(<?= $order['order_id'] ?>)">
                                          <i class='bx bx-check me-2'></i>Confirmer la livraison
                                    </button>
                              </div>
                        </div>
                  </div>
            </div>
      <?php endforeach; ?>
      </div>

      <?php include '../../includes/footer.php'; ?>

      <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
      <script src="../../assets/js/bootstrap.bundle.min.js"></script>
      <script src="../../assets/js/ordering-alert.js"></script>
      <script src="../../assets/js/filter-orders.js"></script>
      <script src="../../assets/js/reload.js"></script>
        
      <script>
            let currentDeliveryContext = null;
            let deliveryModalConfirming = false;
            let lastOrderId = 0;
            let isNotificationRequestInFlight = false;
            let isReloadScheduled = false;
            let lastUserInteractionAt = window.Date.now();
            let isPushRegistering = false;

            const POLLING_INTERVAL_MS = 15000;
            const RELOAD_GRACE_PERIOD_MS = 1500;
            const RELOAD_RETRY_WHEN_BUSY_MS = 5000;
            const PUSH_SETUP_DELAY_MS = 5000;
            const PUSH_FETCH_TIMEOUT_MS = 8000;

            // Empêche les warnings aria-hidden en retirant le focus avant fermeture d'une modal
            function blurFocusInsideModal(modalElement) {
                  if (!modalElement) {
                        return;
                  }

                  const activeElement = document.activeElement;
                  if (activeElement && modalElement.contains(activeElement) && typeof activeElement.blur === 'function') {
                        activeElement.blur();
                  }
            }

            function attachModalFocusSafety() {
                  document.querySelectorAll('.modal').forEach(function(modalElement) {
                        if (modalElement.dataset.focusSafetyAttached === '1') {
                              return;
                        }

                        modalElement.addEventListener('hide.bs.modal', function() {
                              blurFocusInsideModal(modalElement);
                        });

                        modalElement.dataset.focusSafetyAttached = '1';
                  });
            }

            function escapeHtml(value) {
                  return String(value || '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
            }

            function formatPriceFcfa(value) {
                  const amount = Number(value || 0);
                  return amount.toLocaleString('fr-FR') + ' FCFA';
            }

            function formatDateTime(value) {
                  const date = value ? new Date(String(value).replace(' ', 'T')) : new Date();
                  if (isNaN(date.getTime())) {
                        return new Date().toLocaleString('fr-FR', {
                              day: '2-digit',
                              month: '2-digit',
                              year: 'numeric',
                              hour: '2-digit',
                              minute: '2-digit'
                        }).replace(',', '');
                  }

                  return date.toLocaleString('fr-FR', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                  }).replace(',', '');
            }

            function getPaneIdByStatus(status) {
                  switch (status) {
                        case 'new':
                        case 'remind':
                              return 'pane-to-process';
                        case 'unreachable':
                              return 'pane-unreachable';
                        case 'processing':
                              return 'pane-processing';
                        case 'deliver':
                              return 'pane-delivered';
                        default:
                              return null;
                  }
            }

            function getRowClassByStatus(status) {
                  switch (status) {
                        case 'unreachable':
                              return 'order-row-unreachable';
                        case 'remind':
                              return 'order-row-remind';
                        case 'processing':
                              return 'order-row-processing';
                        default:
                              return 'order-row-default';
                  }
            }

            function updateTabBadgeByPane(paneId, delta) {
                  if (!paneId || !delta) {
                        return;
                  }

                  const tabId = paneId.replace('pane-', 'tab-');
                  const badge = document.querySelector('button#' + tabId + ' .badge');
                  if (!badge) {
                        return;
                  }

                  const current = parseInt(badge.textContent || '0', 10);
                  const next = Math.max(0, current + delta);
                  badge.textContent = String(next);
            }

            function updateToProcessCount() {
                  const countBadge = document.getElementById('order-count');
                  if (!countBadge) {
                        return;
                  }

                  const rows = document.querySelectorAll('#pane-to-process tbody tr[data-order-id]');
                  countBadge.textContent = String(rows.length);
            }

            function buildPaneTableHtml(paneId) {
                  if (paneId === 'pane-delivered') {
                        return '' +
                        '<div class="table-responsive">' +
                              '<table class="table table-striped table-bordered" id="orders-delivered-table">' +
                                    '<thead>' +
                                          '<tr>' +
                                                '<th>ID</th>' +
                                                '<th>Client</th>' +
                                                '<th>Adresse</th>' +
                                                '<th>Note client</th>' +
                                                '<th>Produit</th>' +
                                                '<th>Qt</th>' +
                                                '<th>Total</th>' +
                                                '<th>Date</th>' +
                                          '</tr>' +
                                    '</thead>' +
                                    '<tbody></tbody>' +
                              '</table>' +
                        '</div>';
                  }

                  return '' +
                  '<div class="table-responsive">' +
                        '<table class="table table-bordered" id="orders-table">' +
                              '<thead>' +
                                    '<tr>' +
                                          '<th scope="col">ID</th>' +
                                          '<th scope="col">Client</th>' +
                                          '<th scope="col">Contact</th>' +
                                          '<th scope="col">Adresse</th>' +
                                          '<th scope="col">Note client</th>' +
                                          '<th scope="col">Produit</th>' +
                                          '<th scope="col">Qt</th>' +
                                          '<th scope="col">Prix Total</th>' +
                                          '<th scope="col">Notes</th>' +
                                          '<th scope="col">Actions</th>' +
                                          '<th scope="col">Date</th>' +
                                    '</tr>' +
                              '</thead>' +
                              '<tbody></tbody>' +
                        '</table>' +
                  '</div>';
            }

            function ensurePaneTableBody(paneId) {
                  if (!paneId) {
                        return null;
                  }

                  const existingTbody = document.querySelector('#' + paneId + ' table tbody');
                  if (existingTbody) {
                        return existingTbody;
                  }

                  const pane = document.getElementById(paneId);
                  if (!pane) {
                        return null;
                  }

                  const emptyText = pane.querySelector('p.text-muted');
                  if (emptyText) {
                        emptyText.remove();
                  }

                  const host = pane.querySelector('.col-12') || pane;
                  host.insertAdjacentHTML('beforeend', buildPaneTableHtml(paneId));
                  return pane.querySelector('table tbody');
            }

            function getActionOptionsByStatus(status) {
                  if (status === 'processing') {
                        return [
                              { value: 'deliver', label: 'Livre' },
                              { value: 'canceled', label: 'Annuler' }
                        ];
                  }

                  if (status === 'new' || status === 'unreachable' || status === 'remind') {
                        return [
                              { value: 'deliver', label: 'Livrer' },
                              { value: 'processing', label: 'Programmer' },
                              { value: 'remind', label: 'Rappeler' },
                              { value: 'unreachable', label: 'Injoignable' },
                              { value: 'canceled', label: 'Annuler' }
                        ];
                  }

                  return [];
            }

            function buildActionCellHtml(orderId, status, values) {
                  const quantity = Number(values.quantity || 0);
                  const totalPrice = Number(values.total_price || 0);
                  const managerNote = escapeHtml(values.manager_note || '');
                  const updatedAt = escapeHtml(values.updated_at || '');

                  if (status === 'processing') {
                        return '' +
                        '<div class="order-action-group">' +
                              '<form method="POST" action="save.php" id="quickDeliverForm' + orderId + '">' +
                                    '<input type="hidden" name="order_id" value="' + orderId + '">' +
                                    '<input type="hidden" name="quantity" value="' + quantity + '">' +
                                    '<input type="hidden" name="total_price" value="' + totalPrice + '">' +
                                    '<input type="hidden" name="newstat" value="deliver">' +
                                    '<input type="hidden" name="manager_note" value="' + managerNote + '">' +
                                    '<input type="hidden" name="updated_at" value="' + updatedAt + '">' +
                                    '<input type="hidden" name="valider" value="update">' +
                                    '<input type="hidden" name="delivery_fee" value="0">' +
                                    '<button type="button" class="btn btn-success btn-sm quick-deliver-btn" data-order-id="' + orderId + '" title="Livrer">' +
                                          '<i class="bx bx-check"></i><span>Livrer</span>' +
                                    '</button>' +
                              '</form>' +
                              '<form method="POST" action="save.php" data-confirm="Annuler cette commande ?">' +
                                    '<input type="hidden" name="order_id" value="' + orderId + '">' +
                                    '<input type="hidden" name="quantity" value="' + quantity + '">' +
                                    '<input type="hidden" name="total_price" value="' + totalPrice + '">' +
                                    '<input type="hidden" name="newstat" value="canceled">' +
                                    '<input type="hidden" name="manager_note" value="' + managerNote + '">' +
                                    '<input type="hidden" name="updated_at" value="' + updatedAt + '">' +
                                    '<input type="hidden" name="valider" value="update">' +
                                    '<button type="submit" class="btn btn-danger btn-sm" title="Annuler">' +
                                          '<i class="bx bx-x"></i><span>Annuler</span>' +
                                    '</button>' +
                              '</form>' +
                        '</div>';
                  }

                  return '' +
                  '<button class="btn btn-order-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#orderModal' + orderId + '" title="Traiter" aria-label="Traiter">' +
                        '<i class="bx bx-edit-alt"></i>' +
                  '</button>';
            }

            function updateOrderModalState(orderId, status, values) {
                  const quantityInput = document.getElementById('modalQuantity' + orderId);
                  if (quantityInput) {
                        quantityInput.value = values.quantity || quantityInput.value;
                  }

                  const totalInput = document.getElementById('modalTotal' + orderId);
                  if (totalInput) {
                        totalInput.value = values.total_price || totalInput.value;
                  }

                  const noteInput = document.getElementById('modalManagerNote' + orderId);
                  if (noteInput) {
                        noteInput.value = values.manager_note || '';
                  }

                  const deliveryFeeInput = document.getElementById('deliveryFee' + orderId);
                  if (deliveryFeeInput && typeof values.delivery_fee !== 'undefined') {
                        deliveryFeeInput.value = values.delivery_fee;
                  }

                  const actionSelect = document.getElementById('actionSelect' + orderId);
                  if (actionSelect) {
                        const options = getActionOptionsByStatus(status);
                        actionSelect.innerHTML = '';

                        const placeholder = document.createElement('option');
                        placeholder.value = '';
                        placeholder.selected = true;
                        placeholder.textContent = '-- Choisir une action --';
                        actionSelect.appendChild(placeholder);

                        options.forEach(function(option) {
                              const optionElement = document.createElement('option');
                              optionElement.value = option.value;
                              optionElement.textContent = option.label;
                              actionSelect.appendChild(optionElement);
                        });
                  }

                  const statusStrong = document.querySelector('#orderModal' + orderId + ' .form-text strong');
                  if (statusStrong) {
                        statusStrong.textContent = status ? (status.charAt(0).toUpperCase() + status.slice(1)) : statusStrong.textContent;
                  }
            }

            function collectRowSnapshot(row) {
                  const cells = row ? row.cells : null;
                  return {
                        clientName: cells && cells[1] ? cells[1].textContent.trim() : '',
                        address: cells && cells[3] ? cells[3].textContent.trim() : '—',
                        clientNote: cells && cells[4] ? cells[4].textContent.trim() : '—',
                        productName: cells && cells[5] ? cells[5].textContent.trim() : ''
                  };
            }

            function applyOrderUpdateInDom(orderId, newStatus, values) {
                  const row = document.querySelector('tr[data-order-id="' + orderId + '"]');
                  if (!row) {
                        return false;
                  }

                  const sourcePane = row.closest('.tab-pane');
                  const sourcePaneId = sourcePane ? sourcePane.id : null;
                  const targetPaneId = getPaneIdByStatus(newStatus);
                  const snapshot = collectRowSnapshot(row);

                  if (sourcePaneId) {
                        updateTabBadgeByPane(sourcePaneId, -1);
                  }

                  // Statut hors ecran (annule, archive...) => retirer la ligne locale.
                  if (!targetPaneId) {
                        row.remove();
                        updateToProcessCount();
                        return true;
                  }

                  if (targetPaneId === 'pane-delivered') {
                        const deliveredTbody = ensurePaneTableBody('pane-delivered');
                        if (!deliveredTbody) {
                              return false;
                        }

                        const deliveredRow = document.createElement('tr');
                        deliveredRow.setAttribute('data-order-id', String(orderId));
                        deliveredRow.innerHTML = '' +
                              '<td>#' + orderId + '</td>' +
                              '<td class="client-name-cell" title="' + escapeHtml(snapshot.clientName) + '">' + escapeHtml(snapshot.clientName) + '</td>' +
                              '<td class="note-cell" title="' + escapeHtml(snapshot.address) + '">' + escapeHtml(snapshot.address || '—') + '</td>' +
                              '<td class="note-cell" title="' + escapeHtml(snapshot.clientNote) + '">' + escapeHtml(snapshot.clientNote || '—') + '</td>' +
                              '<td class="product-name-cell" title="' + escapeHtml(snapshot.productName) + '">' + escapeHtml(snapshot.productName) + '</td>' +
                              '<td>' + Number(values.quantity || 0) + '</td>' +
                              '<td>' + formatPriceFcfa(values.total_price || 0) + '</td>' +
                              '<td>' + formatDateTime(values.updated_at) + '</td>';

                        deliveredTbody.prepend(deliveredRow);
                        row.remove();
                        updateTabBadgeByPane(targetPaneId, 1);
                        updateToProcessCount();
                        return true;
                  }

                  const targetTbody = ensurePaneTableBody(targetPaneId);
                  if (!targetTbody) {
                        return false;
                  }

                  row.dataset.status = newStatus;
                  row.classList.remove('order-row-default', 'order-row-unreachable', 'order-row-remind', 'order-row-processing');
                  row.classList.add('order-row', getRowClassByStatus(newStatus));

                  if (row.cells[6]) {
                        row.cells[6].textContent = String(Number(values.quantity || 0));
                  }

                  if (row.cells[7]) {
                        row.cells[7].textContent = formatPriceFcfa(values.total_price || 0);
                  }

                  if (row.cells[8]) {
                        const noteValue = values.manager_note || '';
                        row.cells[8].textContent = noteValue;
                        row.cells[8].setAttribute('title', noteValue);
                  }

                  if (row.cells[9]) {
                        row.cells[9].innerHTML = buildActionCellHtml(orderId, newStatus, values);
                  }

                  if (sourcePaneId !== targetPaneId) {
                        targetTbody.prepend(row);
                  }

                  updateTabBadgeByPane(targetPaneId, 1);
                  updateToProcessCount();
                  updateOrderModalState(orderId, newStatus, values);
                  initOrderInteractions();
                  return true;
            }

            function extractFormValues(formElement) {
                  const getFieldValue = function(name, fallback) {
                        const field = formElement.querySelector('[name="' + name + '"]');
                        return field ? field.value : fallback;
                  };

                  return {
                        quantity: getFieldValue('quantity', '0'),
                        total_price: getFieldValue('total_price', '0'),
                        manager_note: getFieldValue('manager_note', ''),
                        updated_at: getFieldValue('updated_at', ''),
                        delivery_fee: getFieldValue('delivery_fee', '0'),
                        newstat: getFieldValue('newstat', '')
                  };
            }

            function buildActionOptionsHtml(status) {
                  const options = getActionOptionsByStatus(status);
                  let html = '<option value="" selected>-- Choisir une action --</option>';
                  options.forEach(function(option) {
                        html += '<option value="' + escapeHtml(option.value) + '">' + escapeHtml(option.label) + '</option>';
                  });
                  return html;
            }

            function buildOrderModalHtml(order) {
                  const orderId = Number(order.order_id || 0);
                  const status = String(order.newstat || 'new');
                  const quantity = Number(order.quantity || 1);
                  const totalPrice = Number(order.total_price || 0);
                  const unitPrice = Number(order.unit_price || 0);
                  const managerNote = String(order.manager_note || '');
                  const clientName = String(order.client_name || 'Client');
                  const clientPhone = String(order.client_phone || '');
                  const productName = String(order.product_name || 'Produit');
                  const updatedAt = String(order.updated_at || new window.Date().toISOString().slice(0, 19).replace('T', ' '));

                  return '' +
                  '<div class="modal fade" id="orderModal' + orderId + '" tabindex="-1" aria-labelledby="orderModal' + orderId + 'Label" aria-hidden="true">' +
                        '<div class="modal-dialog modal-dialog-centered admin-order-modal">' +
                              '<div class="modal-content">' +
                                    '<div class="modal-header py-2">' +
                                          '<h6 class="modal-title mb-0" id="orderModal' + orderId + 'Label"><i class="bx bx-edit-alt me-1"></i>Commande #' + orderId + '</h6>' +
                                          '<button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal" aria-label="Fermer"></button>' +
                                    '</div>' +
                                    '<form action="save.php" method="POST" id="orderForm' + orderId + '">' +
                                          '<div class="modal-body py-2">' +
                                                '<div class="order-modal-summary">' +
                                                      '<div class="d-flex flex-column flex-sm-row justify-content-between gap-1">' +
                                                            '<span><strong>' + escapeHtml(clientName) + '</strong> (' + escapeHtml(clientPhone) + ')</span>' +
                                                      '</div>' +
                                                      '<div class="mt-1"><span class="text-muted">Produit : <strong>' + escapeHtml(productName) + '</strong></span></div>' +
                                                '</div>' +
                                                '<div class="row g-2">' +
                                                      '<div class="col-12 col-md-4"><div class="mb-2"><label for="modalQuantity' + orderId + '" class="form-label mb-1 small fw-bold">Quantite</label><input type="number" class="form-control form-control-sm" id="modalQuantity' + orderId + '" name="quantity" value="' + quantity + '" min="1" required></div></div>' +
                                                      '<div class="col-12 col-md-4"><div class="mb-2"><label class="form-label mb-1 small fw-bold">Prix unitaire (FCFA)</label><input type="text" class="form-control form-control-sm" value="' + escapeHtml(Math.round(unitPrice).toLocaleString('fr-FR')) + '" readonly></div></div>' +
                                                      '<div class="col-12 col-md-4"><div class="mb-2"><label for="modalTotal' + orderId + '" class="form-label mb-1 small fw-bold">Prix total (FCFA)</label><input type="number" class="form-control form-control-sm" id="modalTotal' + orderId + '" name="total_price" value="' + totalPrice + '" min="0" required></div></div>' +
                                                      '<div class="col-12"><div class="mb-2"><label for="actionSelect' + orderId + '" class="form-label mb-1 small fw-bold">Action</label><select class="form-select form-select-sm" id="actionSelect' + orderId + '" name="newstat" required>' + buildActionOptionsHtml(status) + '</select><div class="form-text mt-1"><small class="text-muted">Statut: <strong>' + escapeHtml(status.charAt(0).toUpperCase() + status.slice(1)) + '</strong></small></div></div></div>' +
                                                      '<div class="col-12"><div class="mb-2"><label for="modalManagerNote' + orderId + '" class="form-label mb-1 small fw-bold">Note manager</label><textarea class="form-control form-control-sm" id="modalManagerNote' + orderId + '" name="manager_note" rows="2" placeholder="Notes...">' + escapeHtml(managerNote) + '</textarea></div></div>' +
                                                '</div>' +
                                          '</div>' +
                                          '<div class="modal-footer py-2">' +
                                                '<input type="hidden" name="order_id" value="' + orderId + '">' +
                                                '<input type="hidden" name="valider" value="update">' +
                                                '<input type="hidden" name="updated_at" value="' + escapeHtml(updatedAt) + '">' +
                                                '<input type="hidden" name="delivery_fee" id="deliveryFee' + orderId + '" value="0">' +
                                                '<button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="bx bx-x me-1"></i>Annuler</button>' +
                                                '<button type="button" class="btn btn-primary btn-sm" id="submitBtn' + orderId + '"><i class="bx bx-save me-1"></i>Enregistrer</button>' +
                                          '</div>' +
                                    '</form>' +
                              '</div>' +
                        '</div>' +
                  '</div>';
            }

            function buildDeliveryModalHtml(order) {
                  const orderId = Number(order.order_id || 0);
                  const clientName = String(order.client_name || 'Client');

                  return '' +
                  '<div class="modal fade" id="deliveryFeeModal' + orderId + '" tabindex="-1" aria-labelledby="deliveryFeeModalLabel' + orderId + '" aria-hidden="true">' +
                        '<div class="modal-dialog modal-dialog-centered">' +
                              '<div class="modal-content">' +
                                    '<div class="modal-header bg-success text-white">' +
                                          '<h5 class="modal-title" id="deliveryFeeModalLabel' + orderId + '"><i class="bx bx-package me-2"></i>Frais de Livraison</h5>' +
                                          '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>' +
                                    '</div>' +
                                    '<div class="modal-body">' +
                                          '<p class="text-muted mb-3">Commande #' + orderId + ' - ' + escapeHtml(clientName) + '</p>' +
                                          '<div class="mb-3">' +
                                                '<label for="deliveryFeeInput' + orderId + '" class="form-label fw-bold">Frais de livraison (FCFA)</label>' +
                                                '<input type="number" class="form-control form-control-lg" id="deliveryFeeInput' + orderId + '" placeholder="Entrez les frais de livraison" min="0" value="0">' +
                                                '<div class="form-text"><i class="bx bx-info-circle me-1"></i>Laissez 0 si aucun frais de livraison</div>' +
                                          '</div>' +
                                    '</div>' +
                                    '<div class="modal-footer">' +
                                          '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bx bx-x me-2"></i>Annuler</button>' +
                                          '<button type="button" class="btn btn-success" onclick="confirmDelivery(' + orderId + ')"><i class="bx bx-check me-2"></i>Confirmer la livraison</button>' +
                                    '</div>' +
                              '</div>' +
                        '</div>' +
                  '</div>';
            }

            function ensureOrderModals(order) {
                  const orderId = Number(order.order_id || 0);
                  if (!orderId) {
                        return;
                  }

                  const container = document.getElementById('modals-container');
                  if (!container) {
                        return;
                  }

                  if (!document.getElementById('orderModal' + orderId)) {
                        container.insertAdjacentHTML('beforeend', buildOrderModalHtml(order));
                  }

                  if (!document.getElementById('deliveryFeeModal' + orderId)) {
                        container.insertAdjacentHTML('beforeend', buildDeliveryModalHtml(order));
                  }
            }

            function addIncomingOrderToDom(order) {
                  const orderId = Number(order.order_id || 0);
                  if (!orderId) {
                        return false;
                  }

                  if (document.querySelector('tr[data-order-id="' + orderId + '"]')) {
                        return true;
                  }

                  const status = String(order.newstat || 'new');
                  const targetPaneId = getPaneIdByStatus(status);
                  if (!targetPaneId) {
                        return true;
                  }

                  if (targetPaneId === 'pane-delivered') {
                        const deliveredTbody = ensurePaneTableBody('pane-delivered');
                        if (!deliveredTbody) {
                              return false;
                        }

                        const deliveredRow = document.createElement('tr');
                        deliveredRow.setAttribute('data-order-id', String(orderId));
                        deliveredRow.innerHTML = '' +
                              '<td>#' + orderId + '</td>' +
                              '<td class="client-name-cell" title="' + escapeHtml(order.client_name || 'Client') + '">' + escapeHtml(order.client_name || 'Client') + '</td>' +
                              '<td class="note-cell" title="' + escapeHtml(order.client_adress || '—') + '">' + escapeHtml(order.client_adress || '—') + '</td>' +
                              '<td class="note-cell" title="' + escapeHtml(order.client_note || '—') + '">' + escapeHtml(order.client_note || '—') + '</td>' +
                              '<td class="product-name-cell" title="' + escapeHtml(order.product_name || 'Produit') + '">' + escapeHtml(order.product_name || 'Produit') + '</td>' +
                              '<td>' + Number(order.quantity || 1) + '</td>' +
                              '<td>' + formatPriceFcfa(order.total_price || 0) + '</td>' +
                              '<td>' + formatDateTime(order.updated_at || order.created_at) + '</td>';

                        deliveredTbody.prepend(deliveredRow);
                        updateTabBadgeByPane(targetPaneId, 1);
                        return true;
                  }

                  const targetTbody = ensurePaneTableBody(targetPaneId);
                  if (!targetTbody) {
                        return false;
                  }

                  const values = {
                        quantity: String(order.quantity || 1),
                        total_price: String(order.total_price || 0),
                        manager_note: String(order.manager_note || ''),
                        updated_at: String(order.updated_at || order.created_at || ''),
                        delivery_fee: '0'
                  };

                  const row = document.createElement('tr');
                  row.className = 'order-row ' + getRowClassByStatus(status);
                  row.setAttribute('data-order-id', String(orderId));
                  row.setAttribute('data-status', status);
                  row.setAttribute('data-client', String(order.client_name || '').toLowerCase());
                  row.setAttribute('data-phone', String(order.client_phone || ''));
                  row.setAttribute('data-product', String(order.product_name || '').toLowerCase());

                  row.innerHTML = '' +
                        '<td>#' + orderId + '</td>' +
                        '<td class="client-name-cell" title="' + escapeHtml(order.client_name || 'Client') + '">' + escapeHtml(order.client_name || 'Client') + '</td>' +
                        '<td>' + escapeHtml(order.client_phone || '') + '</td>' +
                        '<td class="note-cell" title="' + escapeHtml(order.client_adress || '') + '">' + escapeHtml((order.client_adress && String(order.client_adress).trim() !== '') ? order.client_adress : '—') + '</td>' +
                        '<td class="note-cell" title="' + escapeHtml(order.client_note || '') + '">' + escapeHtml((order.client_note && String(order.client_note).trim() !== '') ? order.client_note : '—') + '</td>' +
                        '<td class="product-name-cell" title="' + escapeHtml(order.product_name || 'Produit') + '">' + escapeHtml(order.product_name || 'Produit') + '</td>' +
                        '<td>' + Number(order.quantity || 1) + '</td>' +
                        '<td>' + formatPriceFcfa(order.total_price || 0) + '</td>' +
                        '<td class="note-cell" title="' + escapeHtml(order.manager_note || '') + '">' + escapeHtml(order.manager_note || '') + '</td>' +
                        '<td>' + buildActionCellHtml(orderId, status, values) + '</td>' +
                        '<td>' + formatDateTime(order.created_at) + '</td>';

                  targetTbody.prepend(row);
                  updateTabBadgeByPane(targetPaneId, 1);
                  updateToProcessCount();
                  ensureOrderModals(order);
                  initOrderInteractions();
                  return true;
            }

            // Fonction AJAX centralisée
            function submitFormAsync(formElement, orderId) {
                  if (!formElement) return;

                  const $form = $(formElement);

                  // Vérifier si une action a bien été choisie (modal)
                  const $actionSelect = $form.find('select[name="newstat"]');
                  if ($actionSelect.length && $actionSelect.val() === '') {
                        alert('Veuillez choisir une action avant de continuer.');
                        return;
                  }

                  const formData = $form.serialize() + '&is_ajax=1';

                  console.log('[AJAX] Envoi vers:', $form.attr('action'), '| orderId:', orderId, '| données:', formData);

                  const $submitBtn = $form.find('#submitBtn' + orderId);
                  const originalHtml = $submitBtn.length ? $submitBtn.html() : null;
                  if ($submitBtn.length) {
                        $submitBtn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin"></i> ...');
                  }

                  // Fermer la modal principale après avoir gelé le bouton
                  const mainModalEl = document.getElementById('orderModal' + orderId);
                  if (mainModalEl) {
                        const _modal = bootstrap.Modal.getInstance(mainModalEl);
                        if (_modal) _modal.hide();
                  }

                  $.ajax({
                        url: $form.attr('action') || 'save.php',
                        method: 'POST',
                        data: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        dataType: 'json',
                        success: function(response) {
                              console.log('[AJAX] Réponse:', response);
                              if (response && response.success) {
                                    const $row = $('tr[data-order-id="' + orderId + '"]');
                                    console.log('[AJAX] Ligne trouvée:', $row.length, 'éléments pour orderId=' + orderId);

                                    const values = extractFormValues(formElement);
                                    const newStatus = response.newstat || values.newstat || '';
                                    const domUpdated = applyOrderUpdateInDom(orderId, newStatus, values);

                                    if (typeof window.showNotification === 'function') {
                                          window.showNotification('Commande mise à jour avec succès.', 'success', 4000);
                                    }

                                    if (!domUpdated) {
                                          scheduleSmartReload();
                                    }
                              } else {
                                    alert('Erreur : ' + (response ? (response.error || JSON.stringify(response)) : 'Réponse vide'));
                              }
                        },
                        error: function(xhr, status, err) {
                              console.error('[AJAX] Erreur:', status, err, xhr.responseText);
                              alert('Erreur réseau lors de la mise à jour. Réponse: ' + xhr.responseText.substring(0, 200));
                        },
                        complete: function() {
                              if ($submitBtn.length && originalHtml) {
                                    $submitBtn.prop('disabled', false).html(originalHtml);
                              }
                        }
                  });
            }

            $(document).ready(function() {
                  // Intercepter globalement toutes les soumissions de formulaire vers save.php
                  $(document).on('submit', 'form[action="save.php"]', function(e) {
                        e.preventDefault();
                        const $form = $(this);
                        const orderId = $form.find('input[name="order_id"]').val();
                        if (!orderId) return;

                        // Gérer la confirmation si demandée (remplace onsubmit="return confirm(...)")
                        const confirmMsg = $form.data('confirm');
                        if (confirmMsg) {
                              if (!window.confirm(confirmMsg)) return;
                        }

                        submitFormAsync(this, orderId);
                  });
            });

            // Initialise les interactions sur les commandes (modals et boutons rapides)
            function initOrderInteractions() {
            attachModalFocusSafety();

            document.querySelectorAll('[id^="submitBtn"]').forEach(button => {
                  if (button.dataset.listenerAttached === '1') {
                        return;
                  }

                  button.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();

                        const orderId = this.id.replace('submitBtn', '');
                        const form = document.getElementById('orderForm' + orderId);
                        const selectedAction = document.getElementById('actionSelect' + orderId).value;

                        if (selectedAction === 'deliver') {
                              currentDeliveryContext = {
                                    type: 'modal',
                                    orderId: orderId
                              };

                              const mainModalElement = document.getElementById('orderModal' + orderId);
                              const mainModal = bootstrap.Modal.getInstance(mainModalElement);
                              if (mainModal) {
                                    blurFocusInsideModal(mainModalElement);
                                    mainModal.hide();
                              }

                              setTimeout(() => {
                                    const deliveryModalElement = document.getElementById('deliveryFeeModal' + orderId);
                                    if (deliveryModalElement) {
                                          const feeInput = document.getElementById('deliveryFeeInput' + orderId);
                                          if (feeInput) {
                                                feeInput.value = '0';
                                                feeInput.focus();
                                          }
                                          const existingModal = bootstrap.Modal.getInstance(deliveryModalElement);
                                          const deliveryModal = existingModal || new bootstrap.Modal(deliveryModalElement);
                                          deliveryModal.show();
                                          attachDeliveryModalHandler(orderId, deliveryModalElement);
                                    }
                              }, 250);
                        } else {
                              submitFormAsync(form, orderId);
                        }
                  });

                  button.dataset.listenerAttached = '1';
            });

            document.querySelectorAll('.quick-deliver-btn').forEach(button => {
                  if (button.dataset.listenerAttached === '1') {
                        return;
                  }

                  button.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();

                        const orderId = this.dataset.orderId;
                        currentDeliveryContext = {
                              type: 'quick',
                              orderId: orderId
                        };

                        const deliveryModalElement = document.getElementById('deliveryFeeModal' + orderId);
                        if (deliveryModalElement) {
                              const feeInput = document.getElementById('deliveryFeeInput' + orderId);
                              if (feeInput) {
                                    feeInput.value = '0';
                                    feeInput.focus();
                              }
                              const existingModal = bootstrap.Modal.getInstance(deliveryModalElement);
                              const deliveryModal = existingModal || new bootstrap.Modal(deliveryModalElement);
                              deliveryModal.show();
                              attachDeliveryModalHandler(orderId, deliveryModalElement);
                        }
                  });

                  button.dataset.listenerAttached = '1';
            });
            }

            // Confirme la livraison avec les frais saisis
            function confirmDelivery(orderId) {
                  const feeInput = document.getElementById('deliveryFeeInput' + orderId);
                  const deliveryFee = feeInput ? parseInt(feeInput.value || '0', 10) : 0;

                  const deliveryModalElement = document.getElementById('deliveryFeeModal' + orderId);
                  const deliveryModal = bootstrap.Modal.getInstance(deliveryModalElement);
                  if (deliveryModal) {
                        deliveryModalConfirming = true;
                        blurFocusInsideModal(deliveryModalElement);
                        deliveryModal.hide();
                  }

                  if (currentDeliveryContext && currentDeliveryContext.type === 'quick') {
                        const quickForm = document.getElementById('quickDeliverForm' + orderId);
                        if (quickForm) {
                              const feeField = quickForm.querySelector('input[name="delivery_fee"]');
                              if (feeField) {
                                    feeField.value = deliveryFee;
                              }
                              setTimeout(() => submitFormAsync(quickForm, orderId), 200);
                        }
                  } else {
                        const feeField = document.getElementById('deliveryFee' + orderId);
                        if (feeField) {
                              feeField.value = deliveryFee;
                        }
                        setTimeout(() => {
                              const form = document.getElementById('orderForm' + orderId);
                              if (form) {
                                    submitFormAsync(form, orderId);
                              }
                        }, 200);
                  }

                  currentDeliveryContext = null;
                  deliveryModalConfirming = false;
            }

            window.confirmDelivery = confirmDelivery;

            // Attache le comportement de retour au modal principal après le modal de frais
            function attachDeliveryModalHandler(orderId, modalElement) {
                  if (!modalElement || modalElement.dataset.handlerAttached === '1') {
                        return;
                  }

                  modalElement.addEventListener('hidden.bs.modal', function() {
                        if (deliveryModalConfirming) {
                              deliveryModalConfirming = false;
                              currentDeliveryContext = null;
                              return;
                        }

                        if (currentDeliveryContext && currentDeliveryContext.type === 'modal' && currentDeliveryContext.orderId === orderId) {
                              const mainModalElement = document.getElementById('orderModal' + orderId);
                              if (mainModalElement) {
                                    const existingMainModal = bootstrap.Modal.getInstance(mainModalElement);
                                    const mainModal = existingMainModal || new bootstrap.Modal(mainModalElement);
                                    mainModal.show();
                              }
                        }

                        currentDeliveryContext = null;
                  });

                  modalElement.dataset.handlerAttached = '1';
            }

            // Recharge la page proprement
            function refreshOrdersPage() {
                  window.location.reload();
            }

            // Récupère l'ID le plus élevé des commandes  présentes dans le DOM
            function getInitialLastOrderId() {
                  let maxId = 0;
                  document.querySelectorAll('[data-order-id]').forEach(row => {
                        const id = parseInt(row.getAttribute('data-order-id'), 10);
                        if (!isNaN(id) && id > maxId) {
                              maxId = id;
                        }
                  });
                  return maxId;
            }

            // Demande la permission pour afficher les notifications système
            function ensureNotificationPermission() {
                  if (!('Notification' in window)) {
                        return;
                  }
                  if (window.Notification.permission === 'default') {
                        window.Notification.requestPermission();
                  }
            }

            function markUserInteraction() {
                  lastUserInteractionAt = window.Date.now();
            }

            function isUserBusyForReload() {
                  if (document.querySelector('.modal.show')) {
                        return true;
                  }

                  const activeEl = document.activeElement;
                  const isTypingInField = activeEl && (
                        activeEl.tagName === 'INPUT' ||
                        activeEl.tagName === 'TEXTAREA' ||
                        activeEl.tagName === 'SELECT'
                  );

                  if (isTypingInField && !activeEl.readOnly && !activeEl.disabled) {
                        return true;
                  }

                  // Eviter de recharger juste après une interaction utilisateur
                  return (window.Date.now() - lastUserInteractionAt) < 2500;
            }

            function scheduleSmartReload() {
                  if (isReloadScheduled) {
                        return;
                  }

                  isReloadScheduled = true;

                  const tryReload = function() {
                        if (isUserBusyForReload()) {
                              setTimeout(tryReload, RELOAD_RETRY_WHEN_BUSY_MS);
                              return;
                        }

                        window.location.reload();
                  };

                  setTimeout(tryReload, RELOAD_GRACE_PERIOD_MS);
            }

            // Cree une notification system personnalisee avec details de la commande
            function createDetailedNotification(orderData) {
                  if (!('Notification' in window) || window.Notification.permission !== 'granted') {
                        return false;
                  }

                  var title = '📦 Nouvelle commande #' + orderData.order_id;
                  var body = orderData.client_name + '\n' + orderData.product_name + '\n' + 
                        orderData.total_price.toLocaleString('fr-FR') + ' FCFA';

                  try {
                        new window.Notification(title, {
                              body: body,
                              icon: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%230066cc"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>',
                              badge: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"><rect width="24" height="24" fill="%230066cc"/><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2z" fill="white"/></svg>',
                              tag: 'order_' + orderData.order_id,
                              requireInteraction: false
                        });
                        return true;
                  } catch (e) {
                        console.warn('Notification failed:', e);
                        return false;
                  }
            }

            // Toast personnalise pour chaque nouvelle commande
            function showDetailedToast(orderData) {
                  if (typeof window.showNotification === 'function') {
                        var priceFormatted = orderData.total_price.toLocaleString('fr-FR');
                        var msg = '<strong>' + orderData.client_name + '</strong><br>' +
                              '📦 ' + orderData.product_name + '<br>' +
                              '💰 ' + priceFormatted + ' FCFA';
                        window.showNotification(msg, 'success', 8000);
                  }
            }

            // Interroge le serveur pour détecter les nouvelles commandes avec détails personnalisés
            function checkNewOrders() {
                  if (!window.jQuery || isNotificationRequestInFlight) return;

                  isNotificationRequestInFlight = true;

                  $.getJSON('notifications.php', { last_id: lastOrderId })
                        .done(function(data) {
                              if (!data || !data.success) return;
                              if (typeof data.last_id === 'number') lastOrderId = data.last_id;

                              if (!data.new_count || data.new_count <= 0) return;

                              lastOrderId = data.last_id;

                              // Traiter chaque nouvelle commande
                              if (data.orders && Array.isArray(data.orders)) {
                                    var hasDomSyncFailure = false;
                                    data.orders.forEach(function(order) {
                                          var notified = createDetailedNotification(order);
                                          if (!notified) {
                                                showDetailedToast(order);
                                          }
                                          if (!addIncomingOrderToDom(order)) {
                                                hasDomSyncFailure = true;
                                          }
                                    });

                                    // Fallback sécurité si la structure locale ne permet pas l'injection.
                                    if (hasDomSyncFailure) {
                                          scheduleSmartReload();
                                    }
                              } else {
                                    // Fallback si pas de details
                                    var msg = data.new_count === 1
                                          ? "Une nouvelle commande vient d'être passée."
                                          : data.new_count + " nouvelles commandes viennent d'être passées.";
                                    if (typeof window.showNotification === 'function') {
                                          window.showNotification(msg, 'success', 6000);
                                    }
                              }
                        })
                        .always(function() {
                              isNotificationRequestInFlight = false;
                        });
            }

            // --- Web Push : abonnement pour recevoir les notifs même hors de la page ---
            function urlBase64ToUint8Array(base64String) {
                  var padding = '='.repeat((4 - base64String.length % 4) % 4);
                  var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
                  var rawData = window.atob(base64);
                  var output = new window.Uint8Array(rawData.length);
                  for (var i = 0; i < rawData.length; i++) output[i] = rawData.charCodeAt(i);
                  return output;
            }

            function createFetchWithTimeout(url, options, timeoutMs) {
                  return window.Promise.race([
                        fetch(url, options),
                        new window.Promise((resolve, reject) =>
                              setTimeout(() => reject(new Error('Fetch timeout')), timeoutMs)
                        )
                  ]);
            }

            function registerPushAndSubscribe(publicKey) {
                  if (!('serviceWorker' in navigator) || isPushRegistering) {
                        return window.Promise.reject(new Error('Push registration in progress'));
                  }

                  isPushRegistering = true;
                  var storedKey = null;
                  try { storedKey = localStorage.getItem('push_vapid_public_key'); } catch (e) {}

                  return navigator.serviceWorker.register('/sw.js', { scope: '/' })
                        .then(function(reg) {
                              return reg.pushManager.getSubscription().then(function(existingSub) {
                                    if (existingSub && storedKey === publicKey) {
                                          return existingSub;
                                    }

                                    if (existingSub) {
                                          // La clé VAPID a changé depuis cet abonnement (ex: régénération serveur) :
                                          // l'ancien abonnement est devenu invalide, il faut le renouveler.
                                          return existingSub.unsubscribe().then(function() {
                                                return reg.pushManager.subscribe({
                                                      userVisibleOnly: true,
                                                      applicationServerKey: urlBase64ToUint8Array(publicKey)
                                                });
                                          });
                                    }

                                    return reg.pushManager.subscribe({
                                          userVisibleOnly: true,
                                          applicationServerKey: urlBase64ToUint8Array(publicKey)
                                    });
                              });
                        })
                        .then(function(sub) {
                              var payload = sub.toJSON ? sub.toJSON() : { endpoint: sub.endpoint, keys: { p256dh: btoa(String.fromCharCode.apply(null, new window.Uint8Array(sub.getKey('p256dh')))), auth: btoa(String.fromCharCode.apply(null, new window.Uint8Array(sub.getKey('auth')))) } };
                              return createFetchWithTimeout('push-subscribe.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify(payload)
                              }, PUSH_FETCH_TIMEOUT_MS).then(function() {
                                    try { localStorage.setItem('push_vapid_public_key', publicKey); } catch (e) {}
                              });
                        })
                        .then(function() {
                              isPushRegistering = false;
                        })
                        .catch(function(err) {
                              isPushRegistering = false;
                              console.warn('Push subscription failed:', err);
                              return window.Promise.reject(err);
                        });
            }

            function setupPushNotifications() {
                  createFetchWithTimeout('push-public-key.php', { method: 'GET' }, PUSH_FETCH_TIMEOUT_MS)
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                              if (!data.enabled || !data.publicKey) return;
                              var banner = document.getElementById('push-notif-banner');
                              var btn = document.getElementById('push-enable-btn');
                              if (!banner || !btn) return;
                              if (typeof window.Notification !== 'undefined' && window.Notification.permission === 'default') {
                                    banner.classList.remove('d-none');
                                    banner.classList.add('d-flex');
                              } else if (window.Notification.permission === 'granted') {
                                    registerPushAndSubscribe(data.publicKey).catch(function() {});
                              }
                              btn.addEventListener('click', function() {
                                    if (typeof window.Notification === 'undefined') return;
                                    window.Notification.requestPermission().then(function(perm) {
                                          if (perm !== 'granted') return;
                                          banner.classList.add('d-none');
                                          registerPushAndSubscribe(data.publicKey).then(function() {
                                                if (typeof window.showNotification === 'function') window.showNotification('Notifications push activées.', 'success');
                                          }).catch(function() {
                                                if (typeof window.showNotification === 'function') window.showNotification('Impossible d\'activer les notifications.', 'error');
                                          });
                                    });
                              });
                        })
                        .catch(function(err) {
                              console.warn('Push setup failed:', err);
                        });
            }

            document.addEventListener('DOMContentLoaded', function() {
                  initOrderInteractions();
                  lastOrderId = getInitialLastOrderId();
                  ensureNotificationPermission();

                  // Suivre l'activité utilisateur pour éviter les rechargements agressifs
                  ['click', 'keydown', 'input', 'touchstart'].forEach(function(eventName) {
                        document.addEventListener(eventName, markUserInteraction, { passive: true });
                  });

                  setTimeout(checkNewOrders, 2500);
                  setInterval(checkNewOrders, POLLING_INTERVAL_MS);

                  // Web Push : setup en arrière-plan pour ne pas bloquer le chargement
                  setTimeout(setupPushNotifications, PUSH_SETUP_DELAY_MS);
            });
      </script>

</body>

</html>