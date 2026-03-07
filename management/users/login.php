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
</head>

<body class="auth-page">
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
</body>

</html>