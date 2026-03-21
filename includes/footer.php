<?php
  $isAdmin = isset($_SESSION['role']) && (int)$_SESSION['role'] === 1;
?>

<footer class="footer mt-auto pt-4 pb-0 primary-bg paper-color">
  <div class="container">
    <div class="row">
      <div class="col-md-4 mb-4">
        <h5 class="fw-bold primary-color">
          <i class='bx bx-store'></i> MyShop
          <!-- <i class='bx bx-store-alt'></i> MAXORA Shop -->
        </h5>
        <p class="small">
          Votre boutique en ligne pour trouver les meilleurs produits au meilleur prix.
        </p>
      </div>

      <!-- Liens de navigation -->
      <div class="col-md-4 mb-4">
        <h6 class="fw-bold mb-3 primary-color">Navigation</h6>
        <ul class="list-unstyled">
          <?php if ($isAdmin): ?>
          <li><a href="/management/dashboard.php" class="text-decoration-none" style="color: var(--neutral-light);"><i class='bx bx-home'></i> Accueil</a></li>
          <li><a href="/management/products/index.php" class="text-decoration-none" style="color: var(--neutral-light);"><i class='bx bx-box'></i> Produits</a></li>
          <li><a href="/management/orders/index.php" class="text-decoration-none" style="color: var(--neutral-light);"><i class='bx bx-cart'></i> Commandes</a></li>
          <li><a href="/management/users/index.php" class="text-decoration-none" style="color: var(--neutral-light);"><i class='bx bx-user'></i> Utilisateurs</a></li>
          <?php else: ?>
          <li><a href="/management/orders/index.php" class="text-decoration-none" style="color: var(--neutral-light);"><i class='bx bx-cart'></i> Commandes</a></li>
          <?php endif; ?>
        </ul>
      </div>

      <!-- Contact & Social -->
      <div class="col-md-4 mb-4">
        <h6 class="fw-bold mb-3 primary-color">Contact</h6>
        <p class="small"><i class='bx bx-envelope'></i> contact@myshop.com</p>
        <p class="small"><i class='bx bx-phone'></i> +228 90 00 00 00</p>
        <div class="d-flex gap-3 mt-3">
          <a href="#" class="text-decoration-none" style="color: var(--neutral-light); font-size: 1.4rem;"><i class='bx bxl-facebook-circle'></i></a>
          <a href="#" class="text-decoration-none" style="color: var(--neutral-light); font-size: 1.4rem;"><i class='bx bxl-instagram'></i></a>
          <a href="#" class="text-decoration-none" style="color: var(--neutral-light); font-size: 1.4rem;"><i class='bx bxl-twitter'></i></a>
        </div>
      </div>
    </div>
  </div>
</footer>