<?php

require '../../vendor/autoload.php';
require '../../utils/middleware.php';

verifyConnection("/management/gestion/");
checkAdminAccess($_SESSION['user_id']);
checkIsActive($_SESSION['user_id']);

use src\Connectbd;
use src\FinanceManager;

$cnx = Connectbd::getConnection();
$finance = new FinanceManager($cnx);

$redirect = 'index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';

    if ($action === 'delete' && isset($_POST['expense_id']) && is_numeric($_POST['expense_id'])) {
        $finance->deleteExpense((int) $_POST['expense_id']);
        header('Location: ' . $redirect . '?deleted=1');
        exit;
    }

    if ($action === 'add') {
        $type = isset($_POST['type']) ? trim($_POST['type']) : 'autres';
        $amount = isset($_POST['cout']) ? (float) str_replace(',', '.', $_POST['cout']) : 0;
        $description = isset($_POST['description']) ? trim($_POST['description']) : null;
        $productId = isset($_POST['product_id']) && $_POST['product_id'] !== '' ? (int) $_POST['product_id'] : null;
        $date = isset($_POST['date']) && $_POST['date'] !== '' ? $_POST['date'] . ' ' . date('H:i:s') : null;

        if ($amount > 0) {
            try {
                $finance->createExpense($type, $amount, $description, $productId, null, $date);
                header('Location: ' . $redirect . '?added=1');
                exit;
            } catch (Exception $e) {
                header('Location: ' . $redirect . '?error=' . urlencode($e->getMessage()));
                exit;
            }
        }
        header('Location: ' . $redirect . '?error=Montant invalide');
        exit;
    }
}

header('Location: ' . $redirect);
exit;
