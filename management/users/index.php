<?php
require '../../vendor/autoload.php';
require '../../utils/middleware.php';

verifyConnection("/management/users/");
checkAdminAccess($_SESSION['user_id']);
checkIsActive($_SESSION['user_id']);

use src\Connectbd;
use src\User;
use src\Country;

$cnx = Connectbd::getConnection();

$user = new User($cnx);
$country = new Country($cnx);
$countries = $country->getAll();

$users = $user->getAllUsers();


?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/index.css" rel="stylesheet">
    <link href="../../assets/css/admin.css" rel="stylesheet">
    <link href="../../assets/css/navbar.css" rel="stylesheet" />
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>

<body>
    <?php include '../../includes/navbar.php'; ?>

    <main class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Liste des utilisateurs</h2>
            <button class="btn btn-order-primary border-1 border-black rounded-3" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class='bx bx-plus'></i> Ajouter
            </button>
        </div>

        <!-- Modal Ajout Utilisateur -->
        <div class="modal fade" id="addUserModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="border: 1.5px solid #0c1a2c; border-radius: 0px;">
                    <div class="modal-header" style="background: var(--primary); border-radius: 0px;">
                        <h5 class="modal-title" style="color: var(--paper);">
                            <i class='bx bx-user-plus'></i> Nouvel Utilisateur
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form action="save.php" method="POST">
                            <div class="mb-3 position-relative">
                                <label class="form-label" style="color: var(--purple);">
                                    <i class='bx bx-user'></i> Nom et prénom
                                </label>
                                <input type="text" class="form-control" name="name" required
                                    style="border-color: var(--purple); padding-left: 35px;">
                            </div>
                            <div class="mb-3 position-relative">
                                <label class="form-label" style="color: var(--purple);">
                                    <i class='bx bx-envelope'></i> Email
                                </label>
                                <input type="email" class="form-control" name="email" required
                                    style="border-color: var(--purple); padding-left: 35px;">
                            </div>

                            <div class="mb-3">
                                <label class="form-label" style="color: var(--purple);">
                                    <i class='bx bx-flag'></i> Pays
                                </label>
                                <select class="form-select" name="country" required style="border-color: var(--purple);">
                                    <?php foreach ($countries as $country): ?>
                                        <option value="<?php echo $country['id']; ?>"><?php echo $country['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" style="color: var(--purple);">
                                    <i class='bx bx-user-check'></i> Rôle
                                </label>
                                <select class="form-select" name="role" required style="border-color: var(--purple);">
                                    <option value="0">Manager</option>
                                    <option value="1">Admin</option>
                                </select>
                            </div>

                            <div class="modal-footer border-0">
                                <button type="button" class="btn btn-order-primary border-1 border-black rounded-3" data-bs-dismiss="modal"
                                    style="background: var(--paper); color: var(--purple);">Annuler</button>
                                <input type="submit" class="btn btn-order-primary border-1 border-black rounded-3" name="validate" value="Ajouter"
                                    style="background: var(--primary); color: var(--paper);" />
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered" id="orders-table">
                <thead>
                    <tr>
                        <th scope="col">Id</th>
                        <th scope="col">Email</th>
                        <th scope="col">Nom & prénom</th>
                        <th scope="col">Pays</th>
                        <th scope="col">Status</th>
                        <th scope="col">Role</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($users as $user):
                        if (strpos($user['email'], 'superdmin@maintenance') !== false) {
                            continue;
                        }
                    ?>
                        <tr class="<?php echo $user['is_active'] == 1 ? 'status-active' : 'status-inactive'; ?>">
                            <td class="text-center"><?php echo $user['id']; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class='bx bxs-user-circle me-2' style="font-size: 2rem; color: var(--purple);"></i>
                                    <a href="mailto:<?php echo $user['email']; ?>" class="text-decoration-none" style="color: var(--purple);"><?php echo $user['email']; ?></a>
                                </div>
                            </td>
                            <td><?php echo $user['name']; ?></td>
                            <td><?php echo $user['country_name']; ?></td>
                            <td class="text-center"><?php echo $user['is_active'] == 1 ? '<i class="bx bxs-check-circle" style="color: green;"></i>' : '<i class="bx bxs-x-circle" style="color: red;"></i>'; ?></td>
                            <td>
                                <span style="color: var(--purple); font-weight: bold;"><?php echo $user['role'] == 0 ? 'Assistant' : 'Admin'; ?></span>
                            </td>
                            <td class="text-center">

                                <form action="save.php" method="post" class="d-inline">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="validate" value="suspend">
                                    <button type="submit" class="btn btn-link p-0" style="color: var(--purple); padding: 1rem; border: 1px solid var(--purple);">
                                        <i class='bx bxs-user-x' style="font-size: 1.5rem;" title="Suspend"></i>
                                    </button>
                                </form>
                                <form action="save.php" method="post" class="d-inline form-delete-user" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ? Cette action est irréversible.');">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="validate" value="delete">
                                    <button type="submit" class="btn btn-link p-0" style="color: var(--primary); padding: 1rem; border: 1px solid var(--primary);">
                                        <i class='bx bxs-trash' style="font-size: 1.5rem;" title="Supprimer"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </main>

    <?php include '../../includes/footer.php'; ?>

    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
</body>

</html>