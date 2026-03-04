<?php
require '../../vendor/autoload.php';
require '../../utils/middleware.php';

verifyConnection("/management/products/add.php");
checkAdminAccess($_SESSION['user_id']);
checkIsActive($_SESSION['user_id']);

use src\Connectbd;
use src\User;

$cnx = Connectbd::getConnection();
$userManager = new User($cnx);
$helpers = $userManager->getUsersByRole(0);

// Récupérer la liste des pays
$countryStmt = $cnx->prepare("SELECT id, code, name FROM countries ORDER BY name ASC");
$countryStmt->execute();
$countries = $countryStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un produit</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/index.css" rel="stylesheet">       
    <link href="../../assets/css/admin.css" rel="stylesheet">
    <link href="../../assets/css/navbar.css" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>

<body>
    <?php include '../../includes/navbar.php'; ?>

    <main class="admin-main container my-4">
        <div class="admin-page-header d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="admin-title mb-1">Nouveau produit</h1>
                <p class="admin-subtitle mb-0">
                    Ajoutez un produit, définissez ses prix par pays et assignez les managers.
                </p>
            </div>
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                <i class='bx bx-arrow-back'></i>
                <span>Retour à la liste</span>
            </a>
        </div>

        <form id="productForm" enctype="multipart/form-data" class="form-container" method="POST" action="save.php">
            <!-- Champs cachés pour les images -->
            <input type="file" id="mainImageInput" name="mainImage" style="display: none;" accept="image/*">
            <input type="file" id="carouselImagesInput" name="carouselImages[]" style="display: none;" accept="image/*" multiple>

            <div class="floating-actions">
                <button type="button" class="floating-btn" onclick="toggleSection('carousel')" title="Ajouter des images">
                    <i class='bx bx-images'></i>
                </button>
                <button type="button" class="floating-btn" onclick="toggleSection('characteristics')" title="Ajouter des caractéristiques">
                    <i class='bx bx-list-plus'></i>
                </button>
                <button type="button" class="floating-btn" onclick="toggleSection('videos')" title="Ajouter des vidéos">
                    <i class='bx bx-video-plus'></i>
                </button>
                <button type="button" class="floating-btn" onclick="toggleSection('packs')" title="Ajouter des packs">
                    <i class='bx bx-package'></i>
                </button>
            </div>

            <!-- Informations de base -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">
                            <i class='bx bx-purchase-tag'></i> Nom du produit
                        </label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            <i class='bx bx-dollar'></i> Prix d'achat
                        </label>
                        <input type="number" class="form-control" name="purchase_price" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            <i class='bx bx-dollar'></i> Livraison
                        </label>
                        <input type="number" class="form-control" name="shipping_price" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            <i class='bx bx-package'></i> Quantité
                        </label>
                        <input type="number" class="form-control" name="quantity" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            <i class='bx bx-user'></i> Assistants de vente (sélection multiple)
                        </label>
                        <select class="form-select" name="manager_ids[]" multiple required>
                            <?php
                            foreach ($helpers as $helper) { ?>
                                <option value=<?= $helper['id'] ?>><?= $helper['name'] ?> (<?= $helper['country'] ?>)</option>
                            <?php } ?>
                        </select>
                        <small class="form-text text-muted">Maintenez Ctrl (Cmd sur Mac) pour sélectionner plusieurs assistants</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            <i class='bx bx-flag'></i> Pays de vente (sélection multiple)
                        </label>
                        <div class="admin-country-box">
                            <?php foreach ($countries as $country) { ?>
                                <div class="form-check d-flex align-items-center mb-1">
                                    <input class="form-check-input" type="checkbox" name="country_ids[]" value="<?= $country['id'] ?>" id="country_<?= $country['id'] ?>">
                                    <label class="form-check-label ms-1" for="country_<?= $country['id'] ?>">
                                        <?= htmlspecialchars($country['code'] . ' - ' . $country['name']) ?>
                                    </label>
                                    <input type="number"
                                           class="form-control form-control-sm ms-2 admin-country-price-input"
                                           placeholder="Prix de vente"
                                           name="country_prices[<?= $country['id'] ?>]"
                                           style="display:none;"
                                           id="price_<?= $country['id'] ?>">
                                </div>
                            <?php } ?>
                        </div>
                        <small class="form-text text-muted">Sélectionnez les pays et renseignez les prix de vente</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            <i class='bx bx-image'></i> Image principale
                        </label>
                        <div class="custom-file-input" id="mainImageUpload">
                            <i class='bx bx-upload'></i>
                            <p>Cliquez ou déposez l'image ici</p>
                        </div>
                        <div id="mainImagePreview"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            <i class='bx bx-text'></i> Description
                        </label>
                        <textarea id="summernote" class="form-control" name="description" rows="4"></textarea>
                    </div>
                </div>
            </div>

            <!-- Carousel (Images additionnelles) -->
            <div class="card mb-4" id="carouselSection" style="display: none;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Images additionnelles</h5>
                    <button type="button" class="btn-close" onclick="toggleSection('carousel')"></button>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">
                            <i class='bx bx-images'></i> Images du carousel (5 images maximum)
                        </label>
                        <div class="custom-file-input" id="carouselImageUpload">
                            <i class='bx bx-upload'></i>
                            <p>Cliquez ou déposez les images ici</p>
                        </div>
                        <div class="carousel-preview" id="carouselPreview"></div>
                    </div>
                </div>
            </div>

            <!-- Caractéristiques -->
            <div class="card mb-4" id="characteristicsSection" style="display: none;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Caractéristiques</h5>
                    <button type="button" class="btn-close" onclick="toggleSection('characteristics')"></button>
                </div>
                <div class="card-body">
                    <div id="characteristics">
                        <button type="button" class="btn admin-section-btn mb-3" onclick="addCharacteristic()">
                            <i class='bx bx-plus'></i> Ajouter une caractéristique
                        </button>
                        <div id="characteristicsList"></div>
                    </div>
                </div>
            </div>

            <!-- Vidéos -->
            <div class="card mb-4" id="videosSection" style="display: none;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Vidéos</h5>
                    <button type="button" class="btn-close" onclick="toggleSection('videos')"></button>
                </div>
                <div class="card-body">
                    <div id="videos">
                        <button type="button" class="btn admin-section-btn mb-3" onclick="addVideo()">
                            <i class='bx bx-plus'></i> Ajouter une vidéo
                        </button>
                        <div id="videosList"></div>
                    </div>
                </div>
            </div>

            <!-- Packs -->
            <div class="card mb-4" id="packsSection" style="display: none;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Packs</h5>
                    <button type="button" class="btn-close" onclick="toggleSection('packs')"></button>
                </div>
                <div class="card-body">
                    <div id="packs">
                        <button type="button" class="btn admin-section-btn mb-3" onclick="addPack()">
                            <i class='bx bx-package'></i> Ajouter un pack
                        </button>
                        <div id="packsList"></div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <button type="submit" name="valider" value="Enregistrer le produit" class="btn admin-btn-primary">
                    <i class='bx bx-save'></i>
                    <span>Enregistrer le produit</span>
                </button>
            </div>
        </form>
    </main>

    <?php include '../../includes/footer.php'; ?>

    

    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/add-product.js"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#summernote').summernote();
            
            // Toggle price input visibility based on checkbox
            document.querySelectorAll('input[name="country_ids[]"]').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const priceInput = document.getElementById('price_' + this.value);
                    if (this.checked) {
                        priceInput.style.display = 'block';
                        priceInput.required = true;
                    } else {
                        priceInput.style.display = 'none';
                        priceInput.required = false;
                        priceInput.value = '';
                    }
                });
            });
        });
    </script>
</body>

</html>