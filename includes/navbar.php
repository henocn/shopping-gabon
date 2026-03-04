
<?php
    $currentPath = $_SERVER['PHP_SELF'] ?? '';
    $userName = $_SESSION['user_name'] ?? 'Utilisateur';
    $userInitial = strtoupper(substr($userName, 0, 1));
?>

<nav class="navbar navbar-expand-lg admin-navbar">
    <div class="container-fluid">

        <!-- Brand -->
        <a class="navbar-brand admin-brand" href="/management/dashboard.php">
            <span class="brand-icon">
                <i class='bx bx-store-alt'></i>
                <span class="brand-title">ZAfrica</span>
            </span>
        </a>

        <!-- Toggler -->
        <button class="navbar-toggler admin-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
            <span class="toggler-line"></span>
            <span class="toggler-line"></span>
        </button>

        <!-- Menu -->
        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav admin-nav mx-auto">
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
                <li class="nav-item">
                    <a class="nav-link <?php echo (strpos($currentPath, '/orders/') !== false) ? 'active' : ''; ?>" href="/management/orders/index.php">
                        <i class='bx bx-cart'></i>
                        <span>Commandes</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (strpos($currentPath, '/users/') !== false) ? 'active' : ''; ?>" href="/management/users/index.php">
                        <i class='bx bx-user'></i>
                        <span>Utilisateurs</span>
                    </a>
                </li>
            </ul>

            <!-- Actions utilisateur -->
            <div class="admin-actions d-flex align-items-center gap-2">
                <span class="user-pill">
                    <span class="user-avatar"><?php echo $userInitial; ?></span>
                    <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
                </span>
                <a href="/management/users/change-pass.php" class="btn btn-sm admin-btn-ghost" title="Changer le mot de passe">
                    <i class='bx bx-lock-alt'></i>
                </a>
                <a href="/management/users/logout.php" class="btn btn-sm admin-btn-primary">
                    <i class='bx bx-log-out-circle'></i>
                    <span>Déconnexion</span>
                </a>
            </div>
        </div>
    </div>
</nav>