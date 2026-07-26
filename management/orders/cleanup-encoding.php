<?php

/**
 * Script à usage unique : corrige les apostrophes/guillemets/esperluettes mal enregistrés
 * (ex: &#039; au lieu de ', &amp; au lieu de &) dans les commandes et les produits historiques.
 * À exécuter une seule fois en visitant cette URL dans le navigateur, puis à supprimer.
 * Sans danger à relancer plusieurs fois : ne touche que les lignes qui contiennent encore
 * une entité HTML, donc une seconde exécution ne trouve plus rien à corriger.
 */

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../utils/middleware.php';

if (PHP_SAPI !== 'cli') {
    verifyConnection('/management/orders/');
    checkAdminAccess($_SESSION['user_id']);
    checkIsActive($_SESSION['user_id']);
    http_response_code(404);
    exit('Script disponible uniquement en ligne de commande.');
}

use src\Connectbd;

header('Content-Type: text/plain; charset=utf-8');

const ENTITY_PATTERN = '&#[0-9]+;|&(amp|lt|gt|quot|apos|nbsp);';

function decodeUntilStable(string $value): string
{
    $previous = null;
    $current = $value;
    $iterations = 0;
    while ($current !== $previous && $iterations < 5) {
        $previous = $current;
        $current = html_entity_decode($previous, ENT_QUOTES, 'UTF-8');
        $iterations++;
    }
    return $current;
}

$cnx = Connectbd::getConnection();
$totalFixed = 0;

// --- orders ---
$orderColumns = ['client_name', 'client_adress', 'client_note', 'manager_note'];
$where = implode(' OR ', array_map(fn($c) => "$c REGEXP " . $cnx->quote(ENTITY_PATTERN), $orderColumns));
$stmt = $cnx->query("SELECT id, " . implode(', ', $orderColumns) . " FROM orders WHERE $where");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== orders : " . count($rows) . " ligne(s) à corriger ===\n";
foreach ($rows as $row) {
    $values = [];
    foreach ($orderColumns as $col) {
        $values[$col] = decodeUntilStable($row[$col] ?? '');
    }
    $update = $cnx->prepare("UPDATE orders SET client_name = :client_name, client_adress = :client_adress, client_note = :client_note, manager_note = :manager_note WHERE id = :id");
    $update->execute($values + ['id' => $row['id']]);
    echo "orders#{$row['id']} corrigée\n";
    $totalFixed++;
}

// --- products ---
$productColumns = ['name', 'ar_name'];
$whereP = implode(' OR ', array_map(fn($c) => "$c REGEXP " . $cnx->quote(ENTITY_PATTERN), $productColumns));
$stmtP = $cnx->query("SELECT id, " . implode(', ', $productColumns) . " FROM products WHERE $whereP");
$rowsP = $stmtP->fetchAll(PDO::FETCH_ASSOC);

echo "\n=== products : " . count($rowsP) . " ligne(s) à corriger ===\n";
foreach ($rowsP as $row) {
    $values = [];
    foreach ($productColumns as $col) {
        $values[$col] = decodeUntilStable($row[$col] ?? '');
    }
    $update = $cnx->prepare("UPDATE products SET name = :name, ar_name = :ar_name WHERE id = :id");
    $update->execute($values + ['id' => $row['id']]);
    echo "products#{$row['id']} corrigée : {$values['name']}\n";
    $totalFixed++;
}

echo "\nTerminé. $totalFixed ligne(s) corrigée(s) au total.\n";
echo "Tu peux supprimer ce fichier (cleanup-encoding.php) du serveur.\n";
