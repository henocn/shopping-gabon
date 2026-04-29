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
</head>

<body class="d-flex flex-column h-100">
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
                    <div class="modal-body">
                        <p class="text-muted mb-3">
                            Supprimez les commandes anciennement créées qui ne sont pas en cours de traitement ou livrées.
                        </p>
                        <div class="mb-3">
                            <label for="daysInput" class="form-label fw-bold">Supprimer les commandes créées avant :</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="daysInput" name="days_ago" value="30" min="1" max="365" required>
                                <span class="input-group-text">jours</span>
                            </div>
                            <small class="text-muted d-block mt-2">
                                Les commandes en cours (processing) et livrées (deliver) ne seront jamais supprimées.
                            </small>
                        </div>
                        <div class="alert alert-info" role="alert">
                            <i class='bx bx-info-circle me-2'></i>
                            <strong>Exemple :</strong> Une valeur de 20 supprimera les commandes créées avant 20 jours à partir d'aujourd'hui.
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
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Traitement...';
            
            try {
                const response = await fetch('cleanup-orders.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new window.URLSearchParams({
                        days_ago: daysAgo
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
                btn.innerHTML = originalText;
            }
        });
    </script>
</body>

</html>