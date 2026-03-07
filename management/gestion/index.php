<?php

require '../../vendor/autoload.php';
require '../../utils/middleware.php';

verifyConnection("/management/gestion/");
checkAdminAccess($_SESSION['user_id']);
checkIsActive($_SESSION['user_id']);

use src\Connectbd;
use src\FinanceManager;
use src\Product;

$cnx = Connectbd::getConnection();
$finance = new FinanceManager($cnx);
$productObj = new Product($cnx);

$products = $productObj->getAllProducts();

$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$filterType = isset($_GET['type']) ? $_GET['type'] : '';

$expenses = [];
$summary = null;
$expensesByType = [];
$revenueByCountry = [];
$topProducts = [];
$salesSummary = null;

try {
    $expenses = $finance->getExpenses($dateFrom, $dateTo . ' 23:59:59', $filterType !== '' ? $filterType : null, 100, 0);
    $expensesByType = $finance->getExpensesSummary($dateFrom, $dateTo . ' 23:59:59');
    $salesSummary = $finance->getSalesCostsAndExpensesSummary($dateFrom, $dateTo . ' 23:59:59');
    $revenueByCountry = $finance->getRevenueByCountry($dateFrom, $dateTo . ' 23:59:59');
    $topProducts = $finance->getTopProfitableProducts(10, $dateFrom, $dateTo . ' 23:59:59');
} catch (Exception $e) {
    $errorDb = $e->getMessage();
}

$expenseTypes = [
    'livraison' => 'Livraison',
    'frais' => 'Frais généraux',
    'products' => 'Produits',
    'users' => 'Utilisateurs',
    'campagn' => 'Campagne',
    'others' => 'Autres',
];

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion - Dépenses & Analyse</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/index.css" rel="stylesheet">
    <link href="../../assets/css/admin.css" rel="stylesheet">
    <link href="../../assets/css/navbar.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body>
    <?php include '../../includes/navbar.php'; ?>

    <main class="admin-main container my-4">
        <div class="admin-page-header d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="admin-title mb-1">Gestion</h1>
                <p class="admin-subtitle mb-0">
                    Dépenses par type, calculs prix d'achat / vente et analyse du marché.
                </p>
            </div>
            <button type="button" class="btn btn-order-primary border-1 border-black rounded-3" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                <i class='bx bx-plus'></i>
                <span>Ajouter une dépense</span>
            </button>
        </div>

        <?php if (isset($_GET['added'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Dépense enregistrée.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                Dépense supprimée.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($errorDb)): ?>
            <div class="alert alert-warning">
                La table des dépenses est peut-être absente. Exécutez le script <code>migrations/create_depense_table.sql</code> dans votre base de données.
            </div>
        <?php endif; ?>

        <!-- Période et filtres -->
        <form method="get" class="admin-filters-bar d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <label class="mb-0">Du</label>
                <input type="date" name="date_from" class="form-control form-control-sm" style="width: auto;" value="<?php echo htmlspecialchars($dateFrom); ?>">
                <label class="mb-0">au</label>
                <input type="date" name="date_to" class="form-control form-control-sm" style="width: auto;" value="<?php echo htmlspecialchars($dateTo); ?>">
                <select name="type" class="form-select form-select-sm" style="width: auto;">
                    <option value="">Tous les types</option>
                    <?php foreach ($expenseTypes as $k => $v): ?>
                        <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $filterType === $k ? 'selected' : ''; ?>><?php echo htmlspecialchars($v); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-sm btn-outline-primary">Actualiser</button>
            </div>
        </form>

        <!-- Calculs : Prix d'achat / Vente / Dépenses -->
        <?php if ($salesSummary !== null): ?>
            <section class="mb-4">
                <h2 class="h5 mb-3" style="color: var(--purple);">
                    <i class='bx bx-calculator'></i> Calculs sur la période
                </h2>
                <div class="row g-3">
                    <div class="col-md-3 col-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted small">Chiffre d'affaires (livré)</div>
                                <div class="fw-bold fs-5"><?php echo number_format($salesSummary['total_revenue'], 0, ',', ' '); ?> FCFA</div>
                                <div class="small"><?php echo $salesSummary['orders_delivered']; ?> commande(s)</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted small">Coût d'achat</div>
                                <div class="fw-bold fs-5"><?php echo number_format($salesSummary['total_purchase_cost'], 0, ',', ' '); ?> FCFA</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted small">Dépenses (frais)</div>
                                <div class="fw-bold fs-5"><?php echo number_format($salesSummary['total_expenses'], 0, ',', ' '); ?> FCFA</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted small">Marge nette</div>
                                <div class="fw-bold fs-5"><?php echo number_format($salesSummary['net_profit'], 0, ',', ' '); ?> FCFA</div>
                                <div class="small"><?php echo number_format($salesSummary['profit_margin_pct'], 1, ',', ''); ?> %</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- Analyse marché : par pays -->
        <?php if (!empty($revenueByCountry)): ?>
            <section class="mb-4">
                <h2 class="h5 mb-3" style="color: var(--purple);">
                    <i class='bx bx-globe'></i> Analyse par pays
                </h2>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Pays</th>
                                <th class="text-end">Commandes</th>
                                <th class="text-end">CA</th>
                                <th class="text-end">Coût achat</th>
                                <th class="text-end">Marge</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($revenueByCountry as $row): ?>
                                <?php $marge = (float)$row['total_revenue'] - (float)$row['total_purchase_cost']; ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['name']); ?> (<?php echo htmlspecialchars($row['code']); ?>)</td>
                                    <td class="text-end"><?php echo (int)$row['orders_count']; ?></td>
                                    <td class="text-end"><?php echo number_format($row['total_revenue'], 0, ',', ' '); ?></td>
                                    <td class="text-end"><?php echo number_format($row['total_purchase_cost'], 0, ',', ' '); ?></td>
                                    <td class="text-end"><?php echo number_format($marge, 0, ',', ' '); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <!-- Produits les plus rentables -->
        <?php if (!empty($topProducts)): ?>
            <section class="mb-4">
                <h2 class="h5 mb-3" style="color: var(--purple);">
                    <i class='bx bx-trending-up'></i> Produits les plus rentables
                </h2>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th class="text-end">Vendu</th>
                                <th class="text-end">CA</th>
                                <th class="text-end">Coût achat</th>
                                <th class="text-end">Marge</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topProducts as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td class="text-end"><?php echo (int)($row['units_sold'] ?? 0); ?></td>
                                    <td class="text-end"><?php echo number_format($row['total_revenue'] ?? 0, 0, ',', ' '); ?></td>
                                    <td class="text-end"><?php echo number_format($row['total_purchase_cost'] ?? 0, 0, ',', ' '); ?></td>
                                    <td class="text-end"><?php echo number_format($row['estimated_profit'] ?? 0, 0, ',', ' '); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <!-- Dépenses par type (résumé) -->
        <?php if (!empty($expensesByType)): ?>
            <section class="mb-4">
                <h2 class="h5 mb-3" style="color: var(--purple);">
                    <i class='bx bx-pie-chart-alt'></i> Dépenses par type
                </h2>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th class="text-end">Nombre</th>
                                <th class="text-end">Total (FCFA)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expensesByType as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($expenseTypes[$row['type']] ?? $row['type']); ?></td>
                                    <td class="text-end"><?php echo (int)$row['transaction_count']; ?></td>
                                    <td class="text-end"><?php echo number_format($row['total_amount'], 0, ',', ' '); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <!-- Liste des dépenses -->
        <section>
            <h2 class="h5 mb-3" style="color: var(--purple);">
                <i class='bx bx-list-ul'></i> Liste des dépenses
            </h2>
            <div class="table-responsive">
                <table class="table table-bordered" id="expenses-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Produit</th>
                            <th class="text-end">Montant</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expenses)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">Aucune dépense sur la période.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($expenses as $e): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($e['date'])); ?></td>
                                    <td><?php echo htmlspecialchars($expenseTypes[$e['type']] ?? $e['type']); ?></td>
                                    <td><?php echo htmlspecialchars($e['description'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($e['product_name'] ?? '-'); ?></td>
                                    <td class="text-end"><?php echo number_format($e['cout'], 0, ',', ' '); ?> FCFA</td>
                                    <td class="text-center">
                                        <form action="save.php" method="post" class="d-inline" onsubmit="return confirm('Supprimer cette dépense ?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="expense_id" value="<?php echo (int)$e['id']; ?>">
                                            <button type="submit" class="btn btn-link p-0 product-action-btn product-action-delete" title="Supprimer">
                                                <i class='bx bx-trash' style="font-size: 1.5rem;"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- Modal Ajouter une dépense -->
    <div class="modal fade" id="addExpenseModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border: 1.5px solid #0c1a2c; border-radius: 0;">
                <div class="modal-header" style="background: var(--primary); border-radius: 0;">
                    <h5 class="modal-title" style="color: var(--paper);">
                        <i class='bx bx-plus-circle'></i> Nouvelle dépense
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="save.php" method="post">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label" style="color: var(--purple);">Type</label>
                            <select name="type" class="form-select" required style="border-color: var(--purple);">
                                <?php foreach ($expenseTypes as $k => $v): ?>
                                    <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($v); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="color: var(--purple);">Montant (FCFA)</label>
                            <input type="number" name="cout" class="form-control" min="1" step="1" required style="border-color: var(--purple);">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="color: var(--purple);">Description</label>
                            <input type="text" name="description" class="form-control" placeholder="Ex. Livraison, Frais transport..." style="border-color: var(--purple);">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="color: var(--purple);">Produit (optionnel)</label>
                            <select name="product_id" class="form-select" style="border-color: var(--purple);">
                                <option value="">— Aucun —</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?php echo (int)$p['product_id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="color: var(--purple);">Date</label>
                            <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" style="border-color: var(--purple);">
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-order-primary border-1 border-black rounded-3">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
</body>

</html>
