<?php

function csrfToken(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new RuntimeException('Session requise pour générer le jeton CSRF.');
    }

    // Une page restaurée depuis le cache (notamment dans une PWA) peut contenir
    // un jeton lié à une ancienne session. Ces réponses ne doivent jamais être
    // mises en cache par le navigateur.
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    if (!headers_sent()) {
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        setcookie('LUXEMARKET_CSRF', $_SESSION['csrf_token'], [
            'expires' => 0,
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(): void
{
    // Si la requête POST est vide mais qu'elle a une taille de contenu, cela signifie que post_max_size a été dépassé
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $maxSize = ini_get('post_max_size');
        http_response_code(413);
        exit("Requête refusée. Les fichiers envoyés sont trop volumineux. La taille maximale autorisée par le serveur est de $maxSize.");
    }

    $submitted = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $expected = $_SESSION['csrf_token'] ?? '';
    $cookieToken = $_COOKIE['LUXEMARKET_CSRF'] ?? '';

    $validSessionToken = is_string($submitted) && is_string($expected) && $expected !== ''
        && hash_equals($expected, $submitted);
    $validCookieToken = is_string($submitted) && is_string($cookieToken) && $cookieToken !== ''
        && hash_equals($cookieToken, $submitted);

    if (!$validSessionToken && !$validCookieToken) {
        http_response_code(403);
        exit('Requête refusée. Jeton de sécurité invalide.');
    }
}
