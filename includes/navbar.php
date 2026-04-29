
<?php
    $currentPath = $_SERVER['PHP_SELF'] ?? '';
    $userName = $_SESSION['user_name'] ?? 'Utilisateur';
    $isAdmin = isset($_SESSION['role']) && (int)$_SESSION['role'] === 1;
    $roleLabel = $isAdmin ? 'Admin' : 'Manager';
?>

<nav class="navbar navbar-expand-lg admin-navbar primary-bg paper-color">
    <div class="container-fluid">

        <!-- Brand -->
        <a class="navbar-brand admin-brand" href="<?php echo $isAdmin ? '/management/dashboard.php' : '/management/orders/index.php'; ?>">
            <span class="brand-icon">
                <i class='bx bx-store-alt'></i>
            </span>
            <span class="brand-title">ZAfrica</span>
        </a>

        <!-- Toggler -->
        <button class="navbar-toggler admin-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
            <span class="toggler-line"></span>
            <span class="toggler-line"></span>
        </button>

        <!-- Menu -->
        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav admin-nav mx-auto">
                <?php if ($isAdmin): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo (strpos($currentPath, 'dashboard.php') !== false) ? 'active' : ''; ?>" href="/management/dashboard.php">
                        <i class='bx bx-grid-alt'></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (strpos($currentPath, '/products/') !== false) ? 'active' : ''; ?>" href="/management/products/index.php">
                        <i class='bx bx-box'></i>
                        <span>Produits</span>
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo (strpos($currentPath, '/orders/') !== false) ? 'active' : ''; ?>" href="/management/orders/index.php">
                        <i class='bx bx-cart'></i>
                        <span>Commandes</span>
                    </a>
                </li>
                <?php if ($isAdmin): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo (strpos($currentPath, '/users/') !== false) ? 'active' : ''; ?>" href="/management/users/index.php">
                        <i class='bx bx-user'></i>
                        <span>Utilisateurs</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (strpos($currentPath, '/gestion/') !== false) ? 'active' : ''; ?>" href="/management/gestion/index.php">
                        <i class='bx bx-wallet'></i>
                        <span>Gestion</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <!-- Actions utilisateur -->
            <div class="admin-actions d-flex align-items-center gap-2">
                <div class="dropdown">
                    <button class="btn admin-avatar-btn dropdown-toggle" type="button" id="userMenuButton"
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="user-avatar-icon">
                            <i class='bx bx-user-circle'></i>
                        </span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end admin-user-menu" aria-labelledby="userMenuButton">
                        <div class="admin-user-header admin-user-info">
                            <div class="admin-user-name"><?php echo htmlspecialchars($userName); ?></div>
                            <div class="admin-user-role"><?php echo $roleLabel; ?></div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item admin-user-item" href="/management/users/change-pass.php">
                            <i class='bx bx-lock-alt'></i>
                            <span>Changer le mot de passe</span>
                        </a>
                        <a class="dropdown-item admin-user-item" href="/management/users/logout.php">
                            <i class='bx bx-log-out-circle'></i>
                            <span>Déconnexion</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>