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
</head>

<body>
    <?php include '../../includes/navbar.php'; ?>

    <main class="admin-main container my-4">
        <div class="admin-page-header d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="admin-title mb-1">Produits</h1>
                <p class="admin-subtitle mb-0">
                    Gestion du catalogue, des prix par pays et des managers associés.
                </p>
            </div>
            <a href="add.php" class="btn admin-btn-primary">
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

        <div class="table-container admin-table-panel">
            <table class="table align-middle mb-0" id="productsTable">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Produit</th>
                        <th>Prix par pays / managers</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($products as $prod):
                        $managers = $product->getProductManagers($prod['product_id']);
                        $countries = $product->getProductCountries($prod['product_id']);
                        ?>
                        <tr class="product-row"
                            data-name="<?= htmlspecialchars(strtolower($prod['name'])); ?>">
                            <td>#<?php echo $prod['product_id']; ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2 product-main-info">
                                    <img src="../../uploads/main/<?php echo $prod['image']; ?>"
                                         alt="<?php echo $prod['name']; ?>" class="product-image">
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
                            <td class="text-end" style="position:relative;">
                                <button type="button" class="action-btn context-menu-btn"
                                        data-id="<?php echo $prod['product_id']; ?>">
                                    <i class='bx bx-dots-vertical-rounded'></i>
                                </button>
                                <div class="context-menu admin-context-menu"
                                     id="contextMenu<?php echo $prod['product_id']; ?>">
                                    
                                    <a href="update.php?id=<?php echo $prod['product_id']; ?>"
                                       class="menu-item d-flex align-items-center gap-2">
                                        <i class='bx bx-edit'></i>
                                        <span>Modifier</span>
                                    </a>
                                    <button class="menu-item d-flex align-items-center gap-2 text-danger"
                                            onclick="deleteProduct(<?php echo $prod['product_id']; ?>)">
                                        <i class='bx bx-trash'></i>
                                        <span>Supprimer</span>
                                    </button>
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
                    .then(function () {
                        if (typeof showNotification === 'function') {
                            showNotification("Lien copié dans le presse-papiers !", "success");
                        } else {
                            alert("Lien copié : " + shareUrl);
                        }
                    })
                    .catch(function () {
                        fallbackCopy(shareUrl);
                    });
            } else {
                fallbackCopy(shareUrl);
            }
        }


        function deleteProduct(productId) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce produit ?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'save.php';

                const validerInput = document.createElement('input');
                validerInput.type = 'hidden';
                validerInput.name = 'valider';
                validerInput.value = 'delete';

                const productIdInput = document.createElement('input');
                productIdInput.type = 'hidden';
                productIdInput.name = 'product_id';
                productIdInput.value = productId;

                form.appendChild(validerInput);
                form.appendChild(productIdInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        document.querySelectorAll('.context-menu-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                document.querySelectorAll('.admin-context-menu').forEach(function (menu) {
                    menu.style.display = 'none';
                });
                var menu = document.getElementById('contextMenu' + btn.getAttribute('data-id'));
                menu.style.display = 'block';
            });
        });

        document.addEventListener('click', function () {
            document.querySelectorAll('.admin-context-menu').forEach(function (menu) {
                menu.style.display = 'none';
            });
        });

        // Filtre simple côté client
        const searchInput = document.getElementById('productSearch');
        const rows = document.querySelectorAll('#productsTable tbody .product-row');
        searchInput.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            rows.forEach(function (row) {
                const name = row.getAttribute('data-name') || '';
                row.style.display = name.indexOf(q) !== -1 ? '' : 'none';
            });
        });

    </script>
</body>

</html>