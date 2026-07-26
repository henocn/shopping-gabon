<?php

/**
 * Script à usage unique : crée les index manquants sur orders.newstat.
 * À exécuter une seule fois en visitant cette URL dans le navigateur, puis à supprimer.
 * Sans danger à relancer plusieurs fois (vérifie si l'index existe déjà avant de le créer).
 */

require __DIR__ . '/../../vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Script disponible uniquement en ligne de commande.');
}

use src\Connectbd;

header('Content-Type: text/plain; charset=utf-8');

$cnx = Connectbd::getConnection();

function indexExists(\PDO $cnx, string $table, string $indexName): bool
{
    $stmt = $cnx->prepare("SHOW INDEX FROM `$table` WHERE Key_name = :name");
    $stmt->execute(['name' => $indexName]);
    return (bool) $stmt->fetch();
}

$toCreate = [
    'idx_newstat' => "CREATE INDEX idx_newstat ON orders (newstat)",
    'idx_newstat_manager' => "CREATE INDEX idx_newstat_manager ON orders (newstat, manager_id)",
];

foreach ($toCreate as $name => $sql) {
    if (indexExists($cnx, 'orders', $name)) {
        echo "OK - $name existe déjà, rien à faire.\n";
        continue;
    }
    $cnx->exec($sql);
    echo "Créé - $name\n";
}

echo "\nTerminé. Tu peux supprimer ce fichier (setup-indexes.php) du serveur.\n";
