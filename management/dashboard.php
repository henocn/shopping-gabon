<?php
require '../vendor/autoload.php';
require '../utils/middleware.php';

verifyConnection("/management/");
checkAdminAccess($_SESSION['user_id']);
checkIsActive($_SESSION['user_id']);

use src\Connectbd;
use src\Product;
use src\User;
use src\Order;

$cnx = Connectbd::getConnection();

$productObj = new Product($cnx);
$userObj = new User($cnx);
$orderObj = new Order($cnx);

// Récupérer les statistiques
$totalProducts = $productObj->getTotalProducts();
$availableProducts = $productObj->getAvailableProducts();
$unavailableProducts = $totalProducts - $availableProducts;

$totalUsers = $userObj->getTotalUsers();
$activeUsers = $userObj->getActiveUsers();
$inactiveUsers = $totalUsers - $activeUsers;

$totalOrders = $orderObj->getTotalOrders();
/*$processingOrders = $orderObj->getOrdersByStatus('processing');
$validatedOrders = $orderObj->getOrdersByStatus('validated');
$canceledOrders = $orderObj->getOrdersByStatus('canceled');*/
?>
<!DOCTYPE html>
<html lang="fr" class="h-100">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/index.css" rel="stylesheet" />
    <link href="../assets/css/admin.css" rel="stylesheet" />
    <link href="../assets/css/navbar.css" rel="stylesheet" />
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <?php include '../includes/pwa-head.php'; ?>
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

<body class="d-flex flex-column h-100">
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
    <?php include '../includes/navbar.php'; ?>

    <main class="admin-main flex-shrink-0">
        <div class="admin-header container">
            <div>
                <h1 class="admin-title">Tableau de bord</h1>
                <p class="admin-subtitle">
                    Vue d’ensemble de votre boutique : produits, utilisateurs et commandes en temps réel.
                </p>
            </div>
            <div class="admin-header-badge">
                <i class='bx bx-time-five'></i>
                <span><?php echo date('d/m/Y'); ?></span>
            </div>
        </div>

        <section class="admin-section container">
            <div class="admin-stats-grid">
                <article class="admin-stat-card">
                    <div class="admin-stat-icon admin-stat-icon-primary">
                        <i class='bx bx-box'></i>
                    </div>
                    <div class="admin-stat-content">
                        <p class="admin-stat-label">Total produits</p>
                        <p class="admin-stat-value"><?php echo $totalProducts; ?></p>
                    </div>
                </article>

                <article class="admin-stat-card">
                    <div class="admin-stat-icon admin-stat-icon-success">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    <div class="admin-stat-content">
                        <p class="admin-stat-label">Produits disponibles</p>
                        <p class="admin-stat-value"><?php echo $availableProducts; ?></p>
                    </div>
                </article>

                <article class="admin-stat-card">
                    <div class="admin-stat-icon admin-stat-icon-warning">
                        <i class='bx bx-block'></i>
                    </div>
                    <div class="admin-stat-content">
                        <p class="admin-stat-label">Produits indisponibles</p>
                        <p class="admin-stat-value"><?php echo $unavailableProducts; ?></p>
                    </div>
                </article>

                <article class="admin-stat-card">
                    <div class="admin-stat-icon admin-stat-icon-info">
                        <i class='bx bx-cart'></i>
                    </div>
                    <div class="admin-stat-content">
                        <p class="admin-stat-label">Total commandes</p>
                        <p class="admin-stat-value"><?php echo $totalOrders; ?></p>
                    </div>
                </article>
            </div>

            <div class="admin-grid-2">
                <section class="admin-panel">
                    <header class="admin-panel-header">
                        <h2>Produits</h2>
                        <a href="products/index.php" class="admin-link">
                            Voir tous les produits <i class='bx bx-chevron-right'></i>
                        </a>
                    </header>
                    <p class="admin-panel-text">
                        Gérez votre catalogue, les prix par pays et la disponibilité des produits.
                    </p>
                    <ul class="admin-keypoints">
                        <li><i class='bx bx-check'></i> <?php echo $availableProducts; ?> produits actuellement disponibles</li>
                        <li><i class='bx bx-error-circle'></i> <?php echo $unavailableProducts; ?> produits à réapprovisionner</li>
                        <li><i class='bx bx-cog'></i> Gestion centralisée des prix par pays</li>
                    </ul>
                </section>

                <section class="admin-panel">
                    <header class="admin-panel-header">
                        <h2>Utilisateurs &amp; commandes</h2>
                        <a href="orders/index.php" class="admin-link">
                            Gérer les commandes <i class='bx bx-chevron-right'></i>
                        </a>
                    </header>

                    <div class="admin-users-orders">
                        <div class="admin-users-block">
                            <p class="admin-small-label">Utilisateurs</p>
                            <p class="admin-users-line">
                                <strong><?php echo $activeUsers; ?></strong> actifs /
                                <strong><?php echo $inactiveUsers; ?></strong> inactifs sur
                                <strong><?php echo $totalUsers; ?></strong> au total
                            </p>
                            <a href="users/index.php" class="admin-chip-link">
                                Gérer les utilisateurs
                            </a>
                        </div>

                        <div class="admin-orders-block">
                            <p class="admin-small-label">Commandes</p>
                            <p class="admin-orders-line">
                                Suivez les nouvelles commandes, les relances, les livraisons du jour et l’historique.
                            </p>
                            <a href="orders/index.php#pane-to-process" class="admin-chip-link">
                                Voir les commandes à traiter
                            </a>
                        </div>

                        <div class="admin-cleanup-block">
                            <p class="admin-small-label">Maintenance</p>
                            <p class="admin-orders-line">
                                Supprimez les anciennes commandes non livrées pour libérer l'espace.
                            </p>
                            <button type="button" class="btn-liberation" data-bs-toggle="modal" data-bs-target="#cleanupModal">
                                <i class='bx bx-trash me-1'></i>Libérer
                            </button>
                        </div>
                    </div>
                </section>
            </div>
        </section>
    </main>

    <!-- Modal Nettoyage des commandes -->
    <div class="modal fade" id="cleanupModal" tabindex="-1" aria-labelledby="cleanupModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="cleanupModalLabel">
                        <i class='bx bx-trash me-2'></i>Libérer l'espace
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <form id="cleanupForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="modal-body">
                        <p class="text-muted mb-3">
                            Supprimez les commandes anciennement créées, selon les statuts que vous choisissez ci-dessous.
                        </p>
                        <div class="mb-3">
                            <label for="daysInput" class="form-label fw-bold">Supprimer les commandes créées avant :</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="daysInput" name="days_ago" value="30" min="1" max="365" required>
                                <span class="input-group-text">jours</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Statuts à inclure :</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="statuses[]" value="new,remind" id="statusToProcess" checked>
                                <label class="form-check-label" for="statusToProcess">À traiter</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="statuses[]" value="unreachable" id="statusUnreachable" checked>
                                <label class="form-check-label" for="statusUnreachable">Injoignable</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="statuses[]" value="processing" id="statusProcessing">
                                <label class="form-check-label" for="statusProcessing">Programmer</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="statuses[]" value="canceled" id="statusCanceled" checked>
                                <label class="form-check-label" for="statusCanceled">Annulé</label>
                            </div>
                            <small class="text-muted d-block mt-2">
                                Les commandes livrées ne sont jamais supprimables — cette option n'apparaît pas ici.
                            </small>
                        </div>
                        <div class="alert alert-info" role="alert">
                            <i class='bx bx-info-circle me-2'></i>
                            <strong>Exemple :</strong> Une valeur de 20 supprimera les commandes créées avant 20 jours, pour les statuts cochés.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Annuler
                        </button>
                        <button type="submit" class="btn btn-warning">
                            <i class='bx bx-trash me-1'></i>Confirmer la suppression
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Gestion du formulaire de nettoyage
        document.getElementById('cleanupForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const daysAgo = document.getElementById('daysInput').value;
            const checkedStatuses = Array.from(this.querySelectorAll('input[name="statuses[]"]:checked'))
                .map(function(el) { return el.value; })
                .join(',');

            if (!checkedStatuses) {
                alert('Choisissez au moins un statut à supprimer.');
                return;
            }

            const btn = this.querySelector('button[type="submit"]');
            const originalContent = Array.from(btn.childNodes).map(function(node) {
                return node.cloneNode(true);
            });

            btn.disabled = true;
            const spinner = document.createElement('span');
            spinner.className = 'spinner-border spinner-border-sm me-2';
            btn.replaceChildren(spinner, document.createTextNode('Traitement...'));

            try {
                const response = await fetch('cleanup-orders.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new window.URLSearchParams({
                        days_ago: daysAgo,
                        statuses: checkedStatuses,
                        csrf_token: this.querySelector('[name="csrf_token"]')?.value || ''
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('✓ Nettoyage réussi\n\n' + data.message);
                    bootstrap.Modal.getInstance(document.getElementById('cleanupModal')).hide();
                    this.reset();
                    // Optionnel : rafraîchir les stats du dashboard
                    location.reload();
                } else {
                    alert('✗ Erreur : ' + data.message);
                }
            } catch (error) {
                alert('✗ Erreur réseau : ' + error.message);
            } finally {
                btn.disabled = false;
                btn.replaceChildren.apply(btn, originalContent.map(function(node) {
                    return node.cloneNode(true);
                }));
            }
        });
    </script>

    <?php include '../includes/push-notifications-init.php'; ?>
    <?php include '../includes/pwa-script.php'; ?>
</body>

</html>
