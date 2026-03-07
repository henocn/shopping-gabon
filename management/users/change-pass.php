<?php
require_once __DIR__ . "/../../utils/middleware.php";

verifyConnection("/management/users/login.php");

$message = isset($message) ? $message : '';
$success = isset($success) ? $success : '';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changer le mot de passe</title>
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
                <h1>Changer le mot de passe</h1>
                <p>Indiquez votre mot de passe actuel et le nouveau</p>
            </div>

            <div class="auth-alert error" id="errorMessage"><?= htmlspecialchars($message); ?></div>
            <div class="auth-alert success" id="successMessage"><?= htmlspecialchars($success); ?></div>

            <form action="save.php" method="POST" id="changePassForm" class="auth-form">
                <input type="hidden" name="validate" value="change_password">

                <div class="form-group">
                    <label for="current_password">Mot de passe actuel</label>
                    <div class="auth-password-wrap">
                        <input type="password" class="form-control" id="current_password" name="current_password" placeholder="••••••••" required>
                        <button type="button" class="auth-password-toggle" aria-label="Afficher ou masquer"><i class="bx bx-hide"></i></button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="new_password">Nouveau mot de passe</label>
                    <div class="auth-password-wrap">
                        <input type="password" class="form-control" id="new_password" name="new_password" placeholder="••••••••" required>
                        <button type="button" class="auth-password-toggle" aria-label="Afficher ou masquer"><i class="bx bx-hide"></i></button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmer le mot de passe</label>
                    <div class="auth-password-wrap">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="••••••••" required>
                        <button type="button" class="auth-password-toggle" aria-label="Afficher ou masquer"><i class="bx bx-hide"></i></button>
                    </div>
                </div>
                <button type="submit" class="btn btn-auth">
                    <i class='bx bx-key'></i> Changer le mot de passe
                </button>
            </form>

            <div class="auth-footer-link">
                <a href="../dashboard.php">← Retour au tableau de bord</a>
            </div>
        </div>
    </div>

    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        (function() {
            var urlParams = new URLSearchParams(window.location.search);
            var error = urlParams.get('error');
            var success = urlParams.get('success');
            var errorMessage = document.getElementById('errorMessage');
            var successMessage = document.getElementById('successMessage');

            if (error && errorMessage) {
                errorMessage.classList.add('show');
                switch (error) {
                    case 'current_password_wrong':
                        errorMessage.textContent = "Le mot de passe actuel est incorrect.";
                        break;
                    case 'passwords_not_match':
                        errorMessage.textContent = "Les deux mots de passe ne correspondent pas.";
                        break;
                    case 'same_password':
                        errorMessage.textContent = "Le nouveau mot de passe doit être différent de l'actuel.";
                        break;
                    case 'update_failed':
                        errorMessage.textContent = "Erreur lors de la mise à jour.";
                        break;
                    default:
                        errorMessage.textContent = "Une erreur s'est produite. Réessayez.";
                }
            }

            if (success && successMessage) {
                successMessage.classList.add('show');
                successMessage.textContent = "Mot de passe modifié avec succès.";
            }

            var form = document.getElementById('changePassForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    var newPassword = document.getElementById('new_password').value;
                    var confirmPassword = document.getElementById('confirm_password').value;

                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        if (errorMessage) {
                            errorMessage.textContent = "Les deux mots de passe ne correspondent pas.";
                            errorMessage.classList.add('show');
                        }
                        return false;
                    }
                    if (newPassword.length < 6) {
                        e.preventDefault();
                        if (errorMessage) {
                            errorMessage.textContent = "Le mot de passe doit contenir au moins 6 caractères.";
                            errorMessage.classList.add('show');
                        }
                        return false;
                    }
                });
            }
            document.querySelectorAll('.auth-password-toggle').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var wrap = this.closest('.auth-password-wrap');
                    var input = wrap ? wrap.querySelector('input') : null;
                    var icon = this.querySelector('i');
                    if (!input || !icon) return;
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.replace('bx-hide', 'bx-show');
                    } else {
                        input.type = 'password';
                        icon.classList.replace('bx-show', 'bx-hide');
                    }
                });
            });
        })();
    </script>
</body>

</html>
