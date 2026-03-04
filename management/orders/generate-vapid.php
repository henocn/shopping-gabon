<?php
/**
 * Génère les clés VAPID et écrit config/vapid.php.
 * À exécuter une seule fois : php generate-vapid.php (depuis ce dossier ou la racine).
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Minishlink\WebPush\VAPID;

$keys = VAPID::createVapidKeys();
$configDir = dirname(__DIR__, 2) . '/config';
if (!is_dir($configDir)) {
    mkdir($configDir, 0755, true);
}

$subject = 'mailto:admin@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$content = "<?php\n/** Généré par generate-vapid.php - ne pas commiter en prod si sensible */\nreturn [\n"
    . "    'enabled'   => true,\n"
    . "    'subject'   => " . var_export($subject, true) . ",\n"
    . "    'publicKey' => " . var_export($keys['publicKey'], true) . ",\n"
    . "    'privateKey'=> " . var_export($keys['privateKey'], true) . ",\n];\n";

$file = $configDir . '/vapid.php';
file_put_contents($file, $content);
echo "Clés VAPID générées et enregistrées dans " . realpath($file) . "\n";
echo "Clé publique (pour le frontend) : " . $keys['publicKey'] . "\n";
