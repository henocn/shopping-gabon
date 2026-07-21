<?php
session_start();
if (isset($_SESSION) && !empty($_SESSION)) {
    header('location:../dashboard.php');
    exit;
}
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '';
$message = isset($message) ? $message : '';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/index.css" rel="stylesheet">
    <link href="../../assets/css/login.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1a1a2e">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="/assets/icons/icon-192x192.png">
    <style>
        .pwa-install-banner {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 9999;
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: #fff;
            padding: 12px 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.3);
            animation: slideDown 0.3s ease-out;
        }
        @keyframes slideDown {
            from { transform: translateY(-100%); }
            to { transform: translateY(0); }
        }
        .pwa-install-content {
            display: flex;
            align-items: center;
            gap: 12px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .pwa-install-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            flex-shrink: 0;
        }
        .pwa-install-text {
            flex: 1;
            min-width: 0;
            line-height: 1.3;
        }
        .pwa-install-text strong {
            display: block;
            font-size: 14px;
        }
        .pwa-install-text span {
            display: block;
            font-size: 12px;
            opacity: 0.8;
        }
        .pwa-install-content .btn-success {
            flex-shrink: 0;
            font-weight: 600;
            padding: 6px 16px;
            border-radius: 6px;
        }
        .pwa-install-content .btn-close {
            flex-shrink: 0;
            opacity: 0.7;
            filter: brightness(0) invert(1);
        }
        body.pwa-banner-shown {
            padding-top: 64px;
        }
    </style>
</head>

<body class="auth-page">
    <div id="pwa-install-banner" class="pwa-install-banner d-none">
        <div class="pwa-install-content">
            <img src="/assets/icons/icon-192x192.png" alt="LUXEMARKET" class="pwa-install-icon">
            <div class="pwa-install-text">
                <strong>Installer l'application</strong>
                <span>Gérez vos commandes plus rapidement</span>
            </div>
            <button type="button" id="pwa-install-btn" class="btn btn-sm btn-success">Installer</button>
            <button type="button" id="pwa-install-dismiss" class="btn-close btn-close-white" aria-label="Fermer"></button>
        </div>
    </div>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Connexion</h1>
                <p>Entrez vos identifiants pour accéder à l’espace admin</p>
            </div>

            <div class="auth-alert error" id="errorMessage"><?= htmlspecialchars($message); ?></div>

            <form action="save.php" method="POST" id="loginForm" class="auth-form">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect); ?>">
                <input type="hidden" name="validate" value="login">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="vous@exemple.com" required>
                </div>
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <div class="auth-password-wrap">
                        <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required>
                        <button type="button" class="auth-password-toggle" id="togglePassword" aria-label="Afficher ou masquer le mot de passe">
                            <i class="bx bx-hide" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-auth">
                    <i class='bx bx-log-in'></i> Se connecter
                </button>
            </form>
        </div>
    </div>

    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        (function() {
            var urlParams = new URLSearchParams(window.location.search);
            var error = urlParams.get('error');
            var errorMessage = document.getElementById('errorMessage');
            if (error && errorMessage) {
                errorMessage.classList.add('show');
                errorMessage.textContent = error === 'failed' ? "Email ou mot de passe incorrect." : "Une erreur s'est produite. Réessayez.";
            }
            var toggle = document.getElementById('togglePassword');
            var input = document.getElementById('password');
            if (toggle && input) {
                toggle.addEventListener('click', function() {
                    var icon = toggle.querySelector('i');
                    if (input.type === 'password') {
                        input.type = 'text';
                        if (icon) { icon.classList.remove('bx-hide'); icon.classList.add('bx-show'); }
                    } else {
                        input.type = 'password';
                        if (icon) { icon.classList.remove('bx-show'); icon.classList.add('bx-hide'); }
                    }
                });
            }
        })();
    </script>

    <script>
        (function() {
            var isStandalone = window.matchMedia('(display-mode: standalone)').matches ||
                              window.navigator.standalone ||
                              document.referrer.includes('android-app://');

            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sw.js', { scope: '/management/' }).catch(function () {});
            }

            var deferredPrompt = null;
            var banner = document.getElementById('pwa-install-banner');
            var installBtn = document.getElementById('pwa-install-btn');
            var dismissBtn = document.getElementById('pwa-install-dismiss');
            var installText = banner ? banner.querySelector('.pwa-install-text') : null;
            var bannerTimeout = null;

            if (!banner || !installBtn || !dismissBtn) return;

            function showBanner() {
                banner.classList.remove('d-none');
                document.body.classList.add('pwa-banner-shown');
            }

            function hideBanner() {
                banner.classList.add('d-none');
                document.body.classList.remove('pwa-banner-shown');
            }

            function showManualPrompt() {
                if (deferredPrompt || isStandalone) return;
                
                if (installText) {
                    installText.innerHTML = '<strong>Installer l\'application</strong><span>Ouvrez le menu navigateur → "Ajouter à l\'écran d\'accueil"</span>';
                }
                installBtn.textContent = 'Comment faire ?';
                installBtn.onclick = function () {
                    if (installText) {
                        installText.innerHTML = '<strong>Installer l\'application</strong><span>Android : menu ⋮ → Ajouter à l\'écran d\'accueil<br>iOS : partager → Ajouter à l\'écran d\'accueil</span>';
                    }
                    installBtn.textContent = 'J\'ai compris';
                    installBtn.onclick = function () { hideBanner(); };
                };
                showBanner();
            }

            window.addEventListener('beforeinstallprompt', function (e) {
                e.preventDefault();
                
                if (isStandalone) return;
                
                deferredPrompt = e;
                if (bannerTimeout) clearTimeout(bannerTimeout);
                if (installText) {
                    installText.innerHTML = '<strong>Installer l\'application</strong><span>Gérez vos commandes plus rapidement</span>';
                }
                installBtn.textContent = 'Installer';
                installBtn.onclick = function () {
                    if (!deferredPrompt) return;
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then(function (choiceResult) {
                        if (choiceResult.outcome === 'accepted') {
                            hideBanner();
                        }
                        deferredPrompt = null;
                    });
                };
                showBanner();
            });

            dismissBtn.addEventListener('click', function () {
                hideBanner();
                deferredPrompt = null;
                if (bannerTimeout) clearTimeout(bannerTimeout);
            });

            window.addEventListener('appinstalled', function () {
                hideBanner();
                deferredPrompt = null;
                if (bannerTimeout) clearTimeout(bannerTimeout);
            });

            bannerTimeout = setTimeout(showManualPrompt, 3000);
        })();
    </script>

    <?php include '../../includes/push-notifications-init.php'; ?>
</body>

</html>