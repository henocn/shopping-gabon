<?php
// ---------------------------------------------------------------------------//
//     logique de rédirection en fonction du role apres la connexion          //
// ---------------------------------------------------------------------------//

require("../../vendor/autoload.php");
require("../../utils/middleware.php");
startAdminSession();

use src\Connectbd;
use src\User;
use src\Order;

$cnx = Connectbd::getConnection();


// Fonction de redirection
function redirect($url, $message = '')
{
    if (!empty($message)) {
        $url .= '?message=' . urlencode($message);
    }
    header("Location: $url");
    exit();
}

function safeInternalRedirect($value, $fallback = '/management/dashboard.php')
{
    if (!is_string($value) || $value === '' || $value[0] !== '/' || str_starts_with($value, '//')) {
        return $fallback;
    }

    $parts = parse_url($value);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
        return $fallback;
    }

    return $value;
}

if (isset($_POST['validate'])) {
    $connect = is_string($_POST['validate']) ? strtolower(trim($_POST['validate'])) : '';
    $manager = new User($cnx);

    if ($connect !== 'login') {
        if (empty($_SESSION['user_id'])) {
            header('Location: login.php');
            exit;
        }
        checkAdminAccess($_SESSION['user_id']);
        checkIsActive($_SESSION['user_id']);
    }
    verifyCsrfToken();

    switch ($connect) {

        case 'login':
            if (
                isset($_POST['email']) && is_string($_POST['email']) && trim($_POST['email']) !== '' &&
                isset($_POST['password']) && is_string($_POST['password']) && $_POST['password'] !== '' &&
                isset($_POST['redirect']) && is_string($_POST['redirect'])
            ) {
                $email = trim((string) $_POST['email']);
                $password = (string) $_POST['password'];
                $redirect = safeInternalRedirect($_POST['redirect'], '/management/dashboard.php');

                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || strlen($password) > 1024) {
                    header('Location: login.php?error=invalid');
                    exit;
                }

                $data = [
                    'email'  => $email,
                    'password'  => $password
                ];

                $result = $manager->verify($data);

                if ($result["success"]) {
                    session_regenerate_id(true);
                    $_SESSION['user_name'] = $result['name'];
                    $_SESSION['user_id'] = $result['id'];
                    $_SESSION['email'] = $data['email'];
                    $_SESSION['role'] = $result['role'];
                    $_SESSION['country'] = $result['country'];
                    $_SESSION['is_active'] = $result['is_active'];

                    header('Location: ' . $redirect);
                } else {
                    header('Location: login.php?error=failed&redirect=' . rawurlencode($redirect));
                }
            } else {
                echo "On ne peut pas se connecter";
            }
            break;

        case 'ajouter':
            if (!is_string($_POST['email'] ?? null) || !is_string($_POST['name'] ?? null) ||
                !is_scalar($_POST['country'] ?? null) || !is_scalar($_POST['role'] ?? null)) {
                redirect('index.php', "Veuillez remplir tous les champs.");
            }

            $email = trim($_POST['email']);
            $name = trim($_POST['name']);
            $role = filter_var($_POST['role'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1]]);
            $country = filter_var($_POST['country'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || $name === '' || strlen($name) > 150 || $role === false || $country === false) {
                redirect('index.php', "Données utilisateur invalides.");
            }

            if ($manager->email_exists($email)) {
                redirect('index.php', "L'email existe déjà. Veuillez en choisir un autre.");
            }


            $data = [
                'email' => $email,
                'name' => $name,
                'password' => "user1234",
                'role' => $role,
                'country' => $country
            ];

            if ($manager->create($data)) {
                redirect('index.php', "Inscription réussie !");
            } else {
                redirect('index.php', "Une erreur est survenue lors de l'inscription.");
            }
            break;

        case 'suspend':
            if (
                isset($_POST['user_id']) && is_scalar($_POST['user_id']) && filter_var($_POST['user_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            ) {
                $user_id = (int)$_POST['user_id'];

                if ($manager->switchaccountStatus($user_id)) {
                    redirect('index.php', "Opération réussie !");
                } else {
                    redirect('index.php', "Erreur lors de la mise à jour du statut.");
                }
            } else {
                redirect('index.php', "Données invalides pour la mise à jour du statut.");
            }
            break;

        case 'delete':
            checkAdminAccess($_SESSION['user_id'] ?? 0);

            if (
                isset($_POST['user_id']) && is_scalar($_POST['user_id']) && filter_var($_POST['user_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            ) {
                $user_id = (int)$_POST['user_id'];

                // Libère les commandes en cours de cet assistant (pool "non assigné",
                // visible par l'admin) avant de supprimer son compte — sinon elles
                // restent orphelines et invisibles pour tout le monde.
                $orderManager = new Order($cnx);
                $orderManager->unassignManager($user_id);

                if ($manager->deleteUser($user_id)) {
                    redirect('index.php', "Utilisateur supprimé avec succès !");
                } else {
                    redirect('index.php', "Erreur lors de la suppression de l'utilisateur.");
                }
            } else {
                redirect('index.php', "Données invalides pour la suppression de l'utilisateur.");
            }
            break;

        case 'admin_reset_password':
            checkAdminAccess($_SESSION['user_id'] ?? 0);

            if (
                isset($_POST['user_id']) && is_scalar($_POST['user_id']) && filter_var($_POST['user_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) &&
                isset($_POST['new_password']) && is_string($_POST['new_password']) && strlen($_POST['new_password']) >= 6 && strlen($_POST['new_password']) <= 1024
            ) {
                $user_id = (int)$_POST['user_id'];
                $new_password = $_POST['new_password'];

                if ($manager->adminResetPassword($user_id, $new_password)) {
                    redirect('index.php', "Mot de passe réinitialisé avec succès !");
                } else {
                    redirect('index.php', "Erreur lors de la réinitialisation du mot de passe.");
                }
            } else {
                redirect('index.php', "Mot de passe invalide (minimum 6 caractères).");
            }
            break;

        case 'change_password':
            if (
                isset($_POST['current_password']) && !empty($_POST['current_password']) &&
                isset($_POST['new_password']) && !empty($_POST['new_password']) &&
                isset($_POST['confirm_password']) && !empty($_POST['confirm_password']) &&
                isset($_SESSION['user_id'])
            ) {
                $current_password = is_string($_POST['current_password']) ? $_POST['current_password'] : '';
                $new_password = is_string($_POST['new_password']) ? $_POST['new_password'] : '';
                $confirm_password = is_string($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
                $redirect = safeInternalRedirect($_POST['redirect'] ?? '', '/management/dashboard.php');

                if (strlen($new_password) < 6 || strlen($new_password) > 1024) {
                    header('Location: change-pass.php?error=invalid_password');
                    exit();
                }

                if ($new_password !== $confirm_password) {
                    header('Location: change-pass.php?error=passwords_not_match&redirect=' . rawurlencode($redirect));
                    exit();
                }

                $result = $manager->changePassword($_SESSION['user_id'], $current_password, $new_password);

                if ($result["success"]) {
                    header('Location: logout.php?redirect=' . rawurlencode($redirect));
                } else {
                    header('Location: change-pass.php?error=' . rawurlencode((string) $result['message']) . '&redirect=' . rawurlencode($redirect));
                }
            } else {
                header('Location: change-pass.php?error=missing_fields');
            }
            break;

        default:
        header("Location: /error.php?code=400");
    }
} else {
    header("Location: /error.php?code=400");
}
