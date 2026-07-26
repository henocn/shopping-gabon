<?php

// Le suffixe de version invalide les anciens cookies éventuellement créés avec
// un mauvais chemin. Ils ne peuvent ainsi plus faire varier la session entre
// /management/ et /management/users/.
const ADMIN_SESSION_NAME = 'LUXEMARKET_ADMIN_V2';
const ADMIN_SESSION_LIFETIME = 172800; // 48 heures, renouvelées lors des visites

function startAdminSession(): void
{
    // Si une session avec un AUTRE nom est déjà active (ex: PHPSESSID de la
    // vitrine), on la ferme proprement avant de démarrer la session admin.
    // C'est la cause principale du ERR_TOO_MANY_REDIRECTS : startAdminSession()
    // voyait session_status() === ACTIVE et retournait immédiatement, mais les
    // données de session appartenaient à PHPSESSID, pas à LUXEMARKET_ADMIN.
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (session_name() === ADMIN_SESSION_NAME) {
            return; // La bonne session admin est déjà active
        }
        // Fermer la session publique (PHPSESSID) sans la détruire
        session_write_close();
        // session_write_close() conserve les anciennes données dans la variable
        // $_SESSION. Les vider empêche qu'un user_id de la vitrine soit pris pour
        // une authentification de l'espace d'administration.
        $_SESSION = [];
    }

    session_name(ADMIN_SESSION_NAME);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', (string) ADMIN_SESSION_LIFETIME);

    // Détection HTTPS y compris derrière un reverse proxy (Cloudflare, etc.)
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => ADMIN_SESSION_LIFETIME,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (!session_start()) {
        throw new RuntimeException("Impossible de démarrer la session d'administration.");
    }

    // Les pages d'administration contiennent des jetons CSRF : elles ne doivent
    // pas être restaurées avec une ancienne réponse par le navigateur ou la PWA.
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    // Ne pas créer le jeton CSRF ici. Une nouvelle session admin doit rester
    // réellement vide jusqu'à l'authentification. Les formulaires et scripts qui
    // en ont besoin appellent explicitement csrfToken().
}

function destroyAdminSession(): void
{
    startAdminSession();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    // Supprimer aussi le cookie CSRF
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    setcookie('LUXEMARKET_CSRF', '', [
        'expires' => time() - 42000,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_destroy();
}
