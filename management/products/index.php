<?php

require '../../vendor/autoload.php';
require '../../utils/middleware.php';

verifyConnection("/management/products/");
checkAdminAccess($_SESSION['user_id']);
checkIsActive($_SESSION['user_id']);

use src\Connectbd;
use src\Product;

$cnx = Connectbd::getConnection();

$product = new Product($cnx);

$products = $product->getAllProducts();

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management</title>
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

    <main class="admin-main container my-4">
        <div class="admin-page-header d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="admin-title mb-1">Produits</h1>
                <p class="admin-subtitle mb-0">
                    Gestion du catalogue, des prix par pays et des managers associés.
                </p>
            </div>
            <a href="add.php" class="btn btn-order-primary border-1 border-black rounded-3">
                <i class='bx bx-plus'></i>
                <span>Ajouter</span>
            </a>
        </div>

        <div class="admin-filters-bar d-flex justify-content-between align-items-center mb-3">
            <div class="admin-search-wrapper">
                <i class='bx bx-search'></i>
                <input type="text" id="productSearch" class="form-control admin-search-input"
                    placeholder="Rechercher un produit (nom, pays, manager)...">
            </div>
            <div class="d-flex gap-2">
                <span class="badge rounded-pill bg-light text-muted">
                    Total: <?php echo count($products); ?> produits
                </span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered" id="orders-table">
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Produit</th>
                        <th scope="col">Prix par pays / managers</th>
                        <th scope="col" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($products as $prod):
                        $managers = $product->getProductManagers($prod['product_id']);
                        $countries = $product->getProductCountries($prod['product_id']);
                    ?>
                        <?php
                        $searchParts = [strtolower($prod['name'])];
                        foreach ($countries as $c) {
                            $searchParts[] = strtolower($c['code']);
                        }
                        foreach ($managers as $m) {
                            $searchParts[] = strtolower($m['name'] ?? '');
                        }
                        $dataSearch = implode(' ', array_filter($searchParts));
                        ?>
                        <tr class="product-row"
                            data-name="<?= htmlspecialchars(strtolower($prod['name'])); ?>"
                            data-search="<?= htmlspecialchars($dataSearch); ?>">
                            <td>#<?php echo $prod['product_id']; ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2 product-main-info">
                                    <img src="../../uploads/main/<?php echo rawurlencode((string) $prod['image']); ?>"
                                        alt="<?php echo htmlspecialchars((string) $prod['name'], ENT_QUOTES, 'UTF-8'); ?>" class="product-image">
                                    <div class="product-text">
                                        <div class="product-name-cell-admin">
                                            <?php echo htmlspecialchars($prod['name']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($countries)): ?>
                                    <?php foreach ($countries as $ctry): ?>
                                        <div class="mb-1 product-country-line">
                                            <span class="badge country-badge">
                                                <?php echo htmlspecialchars($ctry['code']); ?> -
                                                <?php echo number_format($ctry['selling_price'], 0, ',', ' '); ?> FCFA
                                            </span>
                                            <?php
                                            $countryManagers = array_filter($managers, function ($m) use ($ctry) {
                                                return $m['country_code'] === $ctry['code'];
                                            });
                                            ?>
                                            <?php if (!empty($countryManagers)): ?>
                                                <span class="small text-muted ms-1">
                                                    <?php foreach ($countryManagers as $mgr): ?>
                                                        <span class="badge manager-badge">
                                                            <?php echo htmlspecialchars($mgr['name']); ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="small text-muted ms-1">Aucun manager</span>
                                            <?php endif; ?>
                                            <button type="button"
                                                class="btn btn-sm btn-link p-0 ms-1 product-copy-link"
                                                onclick="copyProductLink(<?php echo $prod['product_id']; ?>, <?php echo (int)$ctry['id']; ?>)"
                                                title="Copier le lien pour ce pays">
                                                <i class='bx bx-link-alt'></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted">Aucun prix défini</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <a href="update.php?id=<?php echo $prod['product_id']; ?>"
                                    class="btn btn-link p-0 me-1" style="color: var(--purple); padding: 1rem; border: 1px solid var(--purple);"
                                    title="Modifier">
                                    <i class='bx bx-edit' style="font-size: 1.5rem;"></i>
                                </a>
                                <form action="save.php" method="post" class="d-inline form-delete-product"
                                    onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ? Cette action est irréversible.');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="valider" value="delete">
                                    <input type="hidden" name="product_id" value="<?php echo $prod['product_id']; ?>">
                                    <button type="submit" class="btn btn-link p-0" style="color: var(--primary); padding: 1rem; border: 1px solid var(--primary);" title="Supprimer">
                                        <i class='bx bx-trash' style="font-size: 1.5rem;"></i>
                                    </button>
                                </form>
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
        function fallbackCopy(text) {
            const tempInput = document.createElement('input');
            tempInput.value = text;
            document.body.appendChild(tempInput);
            tempInput.select();
            try {
                document.execCommand('copy');
            } catch (e) {}
            document.body.removeChild(tempInput);
            if (typeof showNotification === 'function') {
                showNotification("Lien copié dans le presse-papiers !", "success");
            } else {
                alert("Lien copié : " + text);
            }
        }

        function copyProductLink(productId, countryId) {
            let shareUrl = window.location.protocol + '//' + window.location.host + '/index.php?id=' + productId;
            if (countryId) {
                shareUrl += '&country=' + countryId;
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(shareUrl)
                    .then(function() {
                        if (typeof showNotification === 'function') {
                            showNotification("Lien copié dans le presse-papiers !", "success");
                        } else {
                            alert("Lien copié : " + shareUrl);
                        }
                    })
                    .catch(function() {
                        fallbackCopy(shareUrl);
                    });
            } else {
                fallbackCopy(shareUrl);
            }
        }


        (function() {
            var searchInput = document.getElementById('productSearch');
            var rows = document.querySelectorAll('.product-row');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    var term = (this.value || '').toLowerCase().trim();
                    rows.forEach(function(row) {
                        var text = (row.getAttribute('data-search') || row.getAttribute('data-name') || '') + ' ' + (row.textContent || '').toLowerCase();
                        row.style.display = term === '' || text.indexOf(term) !== -1 ? '' : 'none';
                    });
                });
            }
        })();

    </script>

    <?php include '../../includes/push-notifications-init.php'; ?>
    <?php include '../../includes/pwa-script.php'; ?>
</body>

</html>
