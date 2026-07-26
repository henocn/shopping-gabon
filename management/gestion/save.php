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
    verifyCsrfToken();
    $action = isset($_POST['action']) && is_string($_POST['action'])
        ? trim($_POST['action'])
        : '';

    if ($action === 'delete' && isset($_POST['expense_id']) && is_scalar($_POST['expense_id']) && filter_var($_POST['expense_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
        $finance->deleteExpense((int) $_POST['expense_id']);
        header('Location: ' . $redirect . '?deleted=1');
        exit;
    }

    if ($action === 'add') {
        $allowedTypes = ['livraison', 'frais', 'products', 'users', 'campagn', 'others'];
        $type = isset($_POST['type']) && is_string($_POST['type']) ? trim($_POST['type']) : '';
        $amountInput = isset($_POST['cout']) && is_scalar($_POST['cout'])
            ? str_replace(',', '.', (string) $_POST['cout'])
            : '';
        $amount = is_numeric($amountInput) ? (float) $amountInput : 0;
        $description = isset($_POST['description']) && is_string($_POST['description'])
            ? trim($_POST['description'])
            : null;
        $productId = null;
        if (isset($_POST['product_id']) && $_POST['product_id'] !== '') {
            $productId = is_scalar($_POST['product_id'])
                ? filter_var($_POST['product_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                : false;
            if ($productId === false) {
                $productId = null;
            }
        }
        $date = null;
        if (isset($_POST['date']) && $_POST['date'] !== '') {
            $dateInput = is_string($_POST['date']) ? $_POST['date'] : '';
            $dateObject = DateTime::createFromFormat('!Y-m-d', $dateInput);
            if (!$dateObject || $dateObject->format('Y-m-d') !== $dateInput) {
                header('Location: ' . $redirect . '?error=' . urlencode('Date invalide'));
                exit;
            }
            $date = $dateInput . ' ' . date('H:i:s');
        }

        if (in_array($type, $allowedTypes, true) && is_finite($amount) && $amount > 0 && strlen($description ?? '') <= 1000) {
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
