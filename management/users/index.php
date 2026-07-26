<?php
require '../../vendor/autoload.php';
require '../../utils/middleware.php';

verifyConnection("/management/users/");
checkAdminAccess($_SESSION['user_id']);
checkIsActive($_SESSION['user_id']);

use src\Connectbd;
use src\User;
use src\Country;
use src\Product;
use src\Order;

$cnx = Connectbd::getConnection();

$user = new User($cnx);
$country = new Country($cnx);
$countries = $country->getAll();
$productManager = new Product($cnx);
$orderManager = new Order($cnx);

$users = $user->getAllUsers();


?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/index.css" rel="stylesheet">
    <link href="../../assets/css/admin.css" rel="stylesheet">
    <link href="../../assets/css/navbar.css" rel="stylesheet" />
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

    <main class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Liste des utilisateurs</h2>
            <button class="btn btn-order-primary border-1 border-black rounded-3" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class='bx bx-plus'></i> Ajouter
            </button>
        </div>

        <!-- Modal Ajout Utilisateur -->
        <div class="modal fade" id="addUserModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="border: 1.5px solid #0c1a2c; border-radius: 0px;">
                    <div class="modal-header" style="background: var(--primary); border-radius: 0px;">
                        <h5 class="modal-title" style="color: var(--paper);">
                            <i class='bx bx-user-plus'></i> Nouvel Utilisateur
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form action="save.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="mb-3 position-relative">
                                <label class="form-label" style="color: var(--purple);">
                                    <i class='bx bx-user'></i> Nom et prénom
                                </label>
                                <input type="text" class="form-control" name="name" required
                                    style="border-color: var(--purple); padding-left: 35px;">
                            </div>
                            <div class="mb-3 position-relative">
                                <label class="form-label" style="color: var(--purple);">
                                    <i class='bx bx-envelope'></i> Email
                                </label>
                                <input type="email" class="form-control" name="email" required
                                    style="border-color: var(--purple); padding-left: 35px;">
                            </div>

                            <div class="mb-3">
                                <label class="form-label" style="color: var(--purple);">
                                    <i class='bx bx-flag'></i> Pays
                                </label>
                                <select class="form-select" name="country" required style="border-color: var(--purple);">
                                    <?php foreach ($countries as $country): ?>
                                        <option value="<?php echo $country['id']; ?>"><?php echo $country['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" style="color: var(--purple);">
                                    <i class='bx bx-user-check'></i> Rôle
                                </label>
                                <select class="form-select" name="role" required style="border-color: var(--purple);">
                                    <option value="0">Manager</option>
                                    <option value="1">Admin</option>
                                </select>
                            </div>

                            <div class="modal-footer border-0">
                                <button type="button" class="btn btn-order-primary border-1 border-black rounded-3" data-bs-dismiss="modal"
                                    style="background: var(--paper); color: var(--purple);">Annuler</button>
                                <input type="submit" class="btn btn-order-primary border-1 border-black rounded-3" name="validate" value="Ajouter"
                                    style="background: var(--primary); color: var(--paper);" />
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered" id="orders-table">
                <thead>
                    <tr>
                        <th scope="col">Id</th>
                        <th scope="col">Email</th>
                        <th scope="col">Nom & prénom</th>
                        <th scope="col">Pays</th>
                        <th scope="col">Status</th>
                        <th scope="col">Role</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($users as $user):
                        if (strpos($user['email'], 'superdmin@maintenance') !== false) {
                            continue;
                        }
                    ?>
                        <?php
                            $assignedProductsCount = $productManager->countProductsByManager((int) $user['id']);
                            $pendingOrdersCount = $orderManager->countPendingOrdersByManager((int) $user['id']);
                            $deleteWarning = "Supprimer cet utilisateur est irréversible.";
                            if ($assignedProductsCount > 0 || $pendingOrdersCount > 0) {
                                $deleteWarning .= " Il est assigné à {$assignedProductsCount} produit(s) — il en sera retiré. ";
                                if ($pendingOrdersCount > 0) {
                                    $deleteWarning .= "{$pendingOrdersCount} commande(s) en cours lui sont assignées — elles seront transférées à l'administrateur.";
                                }
                            }
                        ?>
                        <tr class="<?php echo $user['is_active'] == 1 ? 'status-active' : 'status-inactive'; ?>">
                            <td class="text-center"><?php echo (int) $user['id']; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class='bx bxs-user-circle me-2' style="font-size: 2rem; color: var(--purple);"></i>
                                    <a href="mailto:<?php echo rawurlencode((string) $user['email']); ?>" class="text-decoration-none" style="color: var(--purple);"><?php echo htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8'); ?></a>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $user['country_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-center"><?php echo $user['is_active'] == 1 ? '<i class="bx bxs-check-circle" style="color: green;"></i>' : '<i class="bx bxs-x-circle" style="color: red;"></i>'; ?></td>
                            <td>
                                <span style="color: var(--purple); font-weight: bold;"><?php echo $user['role'] == 0 ? 'Assistant' : 'Admin'; ?></span>
                            </td>
                            <td class="text-center">

                                <form action="save.php" method="post" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="validate" value="suspend">
                                    <button type="submit" class="btn btn-link p-0" style="color: var(--purple); padding: 1rem; border: 1px solid var(--purple);">
                                        <i class='bx bxs-user-x' style="font-size: 1.5rem;" title="Suspend"></i>
                                    </button>
                                </form>
                                <button type="button" class="btn btn-link p-0" style="color: var(--purple); padding: 1rem; border: 1px solid var(--purple);" data-bs-toggle="modal" data-bs-target="#resetPasswordModal<?php echo $user['id']; ?>" title="Réinitialiser le mot de passe">
                                    <i class='bx bxs-key' style="font-size: 1.5rem;"></i>
                                </button>
                                <form action="save.php" method="post" class="d-inline form-delete-user" data-warning="<?php echo htmlspecialchars($deleteWarning, ENT_QUOTES); ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="validate" value="delete">
                                    <button type="submit" class="btn btn-link p-0" style="color: var(--primary); padding: 1rem; border: 1px solid var(--primary);">
                                        <i class='bx bxs-trash' style="font-size: 1.5rem;" title="Supprimer"></i>
                                    </button>
                                </form>

                                <!-- Modal Réinitialisation mot de passe -->
                                <div class="modal fade" id="resetPasswordModal<?php echo $user['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">
                                                    <i class='bx bxs-key'></i> Réinitialiser le mot de passe
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form action="save.php" method="post">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                                <div class="modal-body">
                                                    <p class="text-muted">Nouveau mot de passe pour <strong><?php echo htmlspecialchars($user['name']); ?></strong> :</p>
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="validate" value="admin_reset_password">
                                                    <input type="password" class="form-control" name="new_password" minlength="6" required placeholder="Nouveau mot de passe">
                                                </div>
                                                <div class="modal-footer border-0">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                    <button type="submit" class="btn btn-order-primary">Réinitialiser</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </main>

    <?php include '../../includes/footer.php'; ?>

    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.form-delete-user').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                const warning = form.getAttribute('data-warning') || 'Êtes-vous sûr de vouloir supprimer cet utilisateur ? Cette action est irréversible.';
                if (!confirm(warning)) {
                    e.preventDefault();
                }
            });
        });
    </script>

    <?php include '../../includes/push-notifications-init.php'; ?>
    <?php include '../../includes/pwa-script.php'; ?>
</body>

</html>
