<?php
namespace src;

use PDO;
use Exception;

class Connectbd {

    private static function connect() {
        try {
            $configPaths = [
                __DIR__ . DIRECTORY_SEPARATOR . '.env',
                dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env',
                dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env',
            ];
            $file = null;
            foreach ($configPaths as $candidate) {
                if (is_file($candidate) && is_readable($candidate)) {
                    $file = $candidate;
                    break;
                }
            }
            $config = $file !== null ? parse_ini_file($file, true) : false;
            if ($config === false || empty($config['database'])) {
                throw new Exception('Configuration de base de données indisponible dans les chemins configurés.');
            }

            $host = $config['database']['host'];
            $dbname = $config['database']['dbname'];
            $username = $config['database']['username'];
            $password = $config['database']['password'];
            $charset = $config['database']['charset'];

            $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

            $cnx = new PDO($dsn, $username, $password);
            $cnx->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $cnx;
        } catch (Exception $error) {
            error_log('[DB] ' . $error->getMessage());
            die('Service temporairement indisponible.');
        }
    }

    public static function getConnection() {
        return self::connect();
    }
}

