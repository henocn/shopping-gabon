<?php
require("../../vendor/autoload.php");
require("../../utils/middleware.php");

use src\Connectbd;
use src\Product;
use src\Pack;
use src\Order;

$cnx = Connectbd::getConnection();
$manager = new Product($cnx);
$packManager = new Pack($cnx);

verifyConnection('/management/products/');
checkAdminAccess($_SESSION['user_id']);
checkIsActive($_SESSION['user_id']);

function safeUploadName($originalName, array $allowedExtensions): string
{
    if (!is_string($originalName)) {
        return '';
    }

    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
        return '';
    }

    return bin2hex(random_bytes(16)) . '.' . $extension;
}

function safeStoredUploadName($value): string
{
    if (!is_string($value) || $value === '') {
        return '';
    }

    $name = basename($value);
    return $name === $value && strpos($name, '..') === false ? $name : '';
}


if (!isset($_POST['valider'])) {
    header('Location: index.php?error=' . urlencode("Action non spécifiée"));
    exit;
}

verifyCsrfToken();


$action = is_string($_POST['valider']) ? trim($_POST['valider']) : '';

switch ($action) {
    case 'upstatus':
        if (
            isset($_POST['product_id'], $_POST['new_status']) &&
            is_scalar($_POST['product_id']) &&
            is_scalar($_POST['new_status']) &&
            filter_var($_POST['product_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false &&
            filter_var($_POST['new_status'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1]]) !== false
        ) {

            try {
                $manager->updateProductStatus((int) $_POST['product_id'], (int) $_POST['new_status']);
                $message = "Statut du produit mis à jour avec succès !";
                header('Location: index.php?message=' . urlencode($message));
                exit;
            } catch (Exception $e) {
                $message = "Erreur lors de la mise à jour du statut : " . $e->getMessage();
                header('Location: index.php?error=' . urlencode($message));
                exit;
            }
        }
        break;

    case 'delete':
        if (isset($_POST['product_id']) && is_scalar($_POST['product_id']) && filter_var($_POST['product_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false) {
            try {
                $manager->deleteProduct((int) $_POST['product_id']);
                $message = "Produit supprimé avec succès !";
                header('Location: index.php?message=' . urlencode($message));
                exit;
            } catch (Exception $e) {
                $message = "Erreur lors de la suppression : " . $e->getMessage();
                header('Location: index.php?error=' . urlencode($message));
                exit;
            }
        }
        break;

    case 'Enregistrer le produit':
        $nameInput = isset($_POST['name']) && is_string($_POST['name']) ? trim($_POST['name']) : '';
        $purchasePrice = isset($_POST['purchase_price']) && is_numeric($_POST['purchase_price']) ? (float) $_POST['purchase_price'] : -1;
        $shippingPrice = isset($_POST['shipping_price']) && is_numeric($_POST['shipping_price']) ? (float) $_POST['shipping_price'] : -1;
        $quantityInput = is_scalar($_POST['quantity'] ?? null)
            ? filter_var($_POST['quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])
            : false;
        if ($nameInput === '' || strlen($nameInput) > 255 || !is_finite($purchasePrice) || $purchasePrice < 0 || !is_finite($shippingPrice) || $shippingPrice < 0 || $quantityInput === false) {
            header('Location: add.php?error=' . urlencode('Données produit invalides'));
            exit;
        }

        // Création des dossiers d'upload si nécessaire
        $uploadDirs = [
            'main' => __DIR__ . '/../../uploads/main/',
            'carousel' => __DIR__ . '/../../uploads/carousel/',
            'characteristics' => __DIR__ . '/../../uploads/characteristics/',
            'packs' => __DIR__ . '/../../uploads/packs/',
            'videos' => __DIR__ . '/../../uploads/videos/'
        ];

        foreach ($uploadDirs as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        try {
            // Traitement de l'image principale
            $mainImageName = '';
            if (isset($_FILES['mainImage']) && $_FILES['mainImage']['error'] === UPLOAD_ERR_OK) {
                $mainImageName = safeUploadName($_FILES['mainImage']['name'], ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                move_uploaded_file(
                    $_FILES['mainImage']['tmp_name'],
                    $uploadDirs['main'] . $mainImageName
                );
            }

            // Traitement des images du carousel
            $carouselImages = ['', '', '', '', ''];
            if (isset($_FILES['carouselImages'])) {
                foreach ($_FILES['carouselImages']['tmp_name'] as $key => $tmp_name) {
                    if (
                        isset($_FILES['carouselImages']['error'][$key]) &&
                        $_FILES['carouselImages']['error'][$key] === UPLOAD_ERR_OK &&
                        $key < 5
                    ) {
                        $fileName = safeUploadName($_FILES['carouselImages']['name'][$key], ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                        if (move_uploaded_file($tmp_name, $uploadDirs['carousel'] . $fileName)) {
                            $carouselImages[$key] = $fileName;
                        }
                    }
                }
            }

            // Création du produit principal
            $productData = [
                'name' => $nameInput,
                'purchase_price' => $purchasePrice,
                'shipping_price' => $shippingPrice,
                'quantity' => $quantityInput,
                'image' => $mainImageName,
                'description' => (string)($_POST['description'] ?? ''),
                'carousel1' => $carouselImages[0],
                'carousel2' => $carouselImages[1],
                'carousel3' => $carouselImages[2],
                'carousel4' => $carouselImages[3],
                'carousel5' => $carouselImages[4],
            ];
            
            // Ajouter les managers
            if (isset($_POST['manager_ids']) && is_array($_POST['manager_ids']) && !empty($_POST['manager_ids'])) {
                $productData['manager_ids'] = $_POST['manager_ids'];
            }
            
            // Ajouter les pays avec prix
            if (isset($_POST['country_ids']) && is_array($_POST['country_ids']) && !empty($_POST['country_ids'])) {
                $productData['product_countries'] = [];
                foreach ($_POST['country_ids'] as $country_id) {
                    $selling_price = floatval($_POST['country_prices'][$country_id] ?? 0);
                    if ($selling_price > 0) {
                        $productData['product_countries'][] = [
                            'country_id' => intval($country_id),
                            'selling_price' => $selling_price
                        ];
                    }
                }
            }

            $manager->createProduct($productData);
            $productId = $manager->GetLastProductId();

            if ($productId) {
                // Traitement des caractéristiques
                if (isset($_POST['characteristic_title'])) {
                    foreach ($_POST['characteristic_title'] as $key => $title) {
                        if (!empty($title)) {
                            $characteristicImage = '';
                            if (
                                isset($_FILES['characteristic_image']['tmp_name'][$key]) &&
                                $_FILES['characteristic_image']['error'][$key] === UPLOAD_ERR_OK
                            ) {

                                $characteristicImage = safeUploadName($_FILES['characteristic_image']['name'][$key], ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                move_uploaded_file(
                                    $_FILES['characteristic_image']['tmp_name'][$key],
                                    $uploadDirs['characteristics'] . $characteristicImage
                                );
                            }

                            $characteristicData = [
                                'product_id' => $productId,
                                'title' => trim((string)$title),
                                'image' => $characteristicImage,
                                'description' => trim((string)($_POST['characteristic_description'][$key] ?? ''))
                            ];

                            $manager->createCaracteristics($characteristicData);
                        }
                    }
                }

                // Traitement des vidéos
                if (isset($_FILES['video'])) {
                    foreach ($_FILES['video']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['video']['error'][$key] === UPLOAD_ERR_OK) {
                            $videoName = safeUploadName($_FILES['video']['name'][$key], ['mp4', 'webm', 'ogg', 'mov']);
                            if (move_uploaded_file($tmp_name, $uploadDirs['videos'] . $videoName)) {
                                $videoData = [
                                    'product_id' => $productId,
                                    'video_url' => $videoName,
                                    'texte' => trim((string)($_POST['video_text'][$key] ?? ''))
                                ];

                                $manager->createVideos($videoData);
                            }
                        }
                    }
                }



                // Traitement des Packs
                // Vérifier si au moins un pack est envoyé
                if (isset($_POST['pack_name'])) {
                    foreach ($_POST['pack_name'] as $key => $name) {
                        if (!empty($name)) {
                            $image = '';
                            if (
                                isset($_FILES['pack_image']['tmp_name'][$key]) &&
                                $_FILES['pack_image']['error'][$key] === UPLOAD_ERR_OK
                            ) {

                                $image = safeUploadName($_FILES['pack_image']['name'][$key], ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                move_uploaded_file(
                                    $_FILES['pack_image']['tmp_name'][$key],
                                    $uploadDirs['packs'] . $image
                                );
                            }

                            $packData = [
                                'product_id'       => $productId,
                                'pack_name'             => trim((string)($name ?? '')),
                                'pack_image'            => $image,
                                'pack_quantity'         => (int)($_POST['pack_quantity'][$key] ?? 0),
                                'pack_price'            => (int)($_POST['pack_price'][$key] ?? 0),
                            ];

                            $manager->createPacks($packData);
                        }
                    }
                }

                $message = "Produit ajouté avec succès !";
                header('Location: index.php?message=' . urlencode($message));
                exit;
            } else {
                throw new Exception("Erreur lors de la création du produit");
            }
        } catch (Exception $e) {
            $message = "Erreur lors de l'ajout du produit : " . $e->getMessage();
            header('Location: add.php?error=' . urlencode($message));
            exit;
        }
        break;






    case 'Mettre a jour le produit':
        // Code pour mettre à jour un produit
        if (isset($_POST['productId'])) {
            $productId = is_scalar($_POST['productId'])
                ? filter_var($_POST['productId'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                : false;
            if ($productId === false) {
                header('Location: index.php?error=' . urlencode('Produit invalide'));
                exit;
            }

            $nameInput = isset($_POST['name']) && is_string($_POST['name']) ? trim($_POST['name']) : '';
            $purchasePrice = isset($_POST['purchase_price']) && is_numeric($_POST['purchase_price']) ? (float) $_POST['purchase_price'] : -1;
            $shippingPrice = isset($_POST['shipping_price']) && is_numeric($_POST['shipping_price']) ? (float) $_POST['shipping_price'] : -1;
            $quantityInput = is_scalar($_POST['quantity'] ?? null)
                ? filter_var($_POST['quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])
                : false;
            if ($nameInput === '' || strlen($nameInput) > 255 || !is_finite($purchasePrice) || $purchasePrice < 0 || !is_finite($shippingPrice) || $shippingPrice < 0 || $quantityInput === false) {
                header('Location: update.php?id=' . (int) $productId . '&error=' . urlencode('Données produit invalides'));
                exit;
            }

            // Création des dossiers d'upload si nécessaire
            $uploadDirs = [
                'main' => __DIR__ . '/../../uploads/main/',
                'carousel' => __DIR__ . '/../../uploads/carousel/',
                'characteristics' => __DIR__ . '/../../uploads/characteristics/',
                'packs' => __DIR__ . '/../../uploads/packs/',
                'videos' => __DIR__ . '/../../uploads/videos/'
            ];

            foreach ($uploadDirs as $dir) {
                if (!file_exists($dir)) {
                    mkdir($dir, 0755, true);
                }
            }

            try {
                // Récupérer les données existantes du produit
                $existingProduct = $manager->getProducts($productId);

                // Traitement de l'image principale
                $mainImageName = safeStoredUploadName($_POST['existing_main_image'] ?? '');

                // Supprimer l'image principale si demandé
                if (isset($_POST['delete_main_image']) && !empty($mainImageName)) {
                    $filePath = $uploadDirs['main'] . $mainImageName;
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                    $mainImageName = '';
                }

                // Uploader une nouvelle image principale
                if (isset($_FILES['mainImage']) && $_FILES['mainImage']['error'] === UPLOAD_ERR_OK) {
                    // Supprimer l'ancienne image si elle existe
                    if (!empty($mainImageName)) {
                        $oldFilePath = $uploadDirs['main'] . $mainImageName;
                        if (file_exists($oldFilePath)) {
                            unlink($oldFilePath);
                        }
                    }

                    $mainImageName = safeUploadName($_FILES['mainImage']['name'], ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                    move_uploaded_file(
                        $_FILES['mainImage']['tmp_name'],
                        $uploadDirs['main'] . $mainImageName
                    );
                }

                // Traitement des images du carousel
                $carouselImages = ['', '', '', '', ''];
                $existingCarousel = isset($_POST['existing_carousel_images']) && is_array($_POST['existing_carousel_images'])
                    ? array_map('safeStoredUploadName', $_POST['existing_carousel_images'])
                    : [];

                // Gérer la suppression des images du carousel
                if (isset($_POST['delete_carousel_images'])) {
                    foreach ($_POST['delete_carousel_images'] as $imageToDelete) {
                        $imageToDelete = safeStoredUploadName($imageToDelete);
                        $filePath = $uploadDirs['carousel'] . $imageToDelete;
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }

                        // Retirer l'image du tableau existant
                        $key = array_search($imageToDelete, $existingCarousel);
                        if ($key !== false) {
                            unset($existingCarousel[$key]);
                        }
                    }
                }

                // Réindexer et remplir le tableau carouselImages avec les images existantes
                $existingCarousel = array_values($existingCarousel);
                for ($i = 0; $i < 5; $i++) {
                    if (isset($existingCarousel[$i])) {
                        $carouselImages[$i] = $existingCarousel[$i];
                    }
                }

                // Traiter les nouvelles images du carousel
                if (isset($_FILES['carouselImages'])) {
                    $newIndex = count($existingCarousel);
                    foreach ($_FILES['carouselImages']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['carouselImages']['error'][$key] === UPLOAD_ERR_OK && $newIndex < 5) {
                            $fileName = safeUploadName($_FILES['carouselImages']['name'][$key], ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                            if (move_uploaded_file($tmp_name, $uploadDirs['carousel'] . $fileName)) {
                                $carouselImages[$newIndex] = $fileName;
                                $newIndex++;
                            }
                        }
                    }
                }

                // Mise à jour du produit principal
                $productData = [
                    'name' => $nameInput,
                    'purchase_price' => $purchasePrice,
                    'shipping_price' => $shippingPrice,
                    'quantity' => $quantityInput,
                    'image' => $mainImageName,
                    'description' => (string)($_POST['description'] ?? ''),
                    'carousel1' => $carouselImages[0],
                    'carousel2' => $carouselImages[1],
                    'carousel3' => $carouselImages[2],
                    'carousel4' => $carouselImages[3],
                    'carousel5' => $carouselImages[4],
                ];

                $manager->updateProduct($productId, $productData);
                
                // Mettre à jour les managers
                if (isset($_POST['manager_ids']) && is_array($_POST['manager_ids'])) {
                    // Récupérer les managers actuels (avec leur pays, pour la réattribution des commandes en cours)
                    $currentManagers = $manager->getProductManagers($productId);
                    $currentManagerIds = array_column($currentManagers, 'id');
                    $newManagerIds = array_map('intval', $_POST['manager_ids']);

                    $removedManagerIds = array_filter($currentManagerIds, fn($id) => !in_array($id, $newManagerIds));
                    $addedManagerIds = array_filter($newManagerIds, fn($id) => !in_array($id, $currentManagerIds));

                    // Supprimer les managers qui ne sont plus sélectionnés
                    foreach ($removedManagerIds as $managerId) {
                        $manager->removeProductManager($productId, $managerId);
                    }

                    // Ajouter les nouveaux managers
                    foreach ($addedManagerIds as $managerId) {
                        $manager->addProductManager($productId, $managerId);
                    }

                    // Réattribuer les commandes pas encore finalisées (livrées/annulées) d'un
                    // assistant retiré vers son remplaçant du même pays : sans ça, ces commandes
                    // restent assignées à un assistant qui n'a plus le produit et n'apparaissent
                    // plus dans la file de personne tant qu'une nouvelle commande n'arrive pas.
                    if (!empty($removedManagerIds) && !empty($addedManagerIds)) {
                        $countryByManagerId = [];
                        foreach ($currentManagers as $cm) {
                            $countryByManagerId[(int)$cm['id']] = $cm['country_code'] ?? null;
                        }
                        $stmtManagerCountry = $cnx->prepare(
                            "SELECT c.code AS country_code FROM users u LEFT JOIN countries c ON u.country = c.id WHERE u.id = :id"
                        );
                        foreach ($addedManagerIds as $newManagerId) {
                            $stmtManagerCountry->execute(['id' => $newManagerId]);
                            $countryByManagerId[$newManagerId] = $stmtManagerCountry->fetchColumn() ?: null;
                        }

                        $orderManager = new Order($cnx);
                        foreach ($removedManagerIds as $oldManagerId) {
                            $oldCountry = $countryByManagerId[$oldManagerId] ?? null;
                            if (!$oldCountry) {
                                continue;
                            }
                            foreach ($addedManagerIds as $newManagerId) {
                                if (($countryByManagerId[$newManagerId] ?? null) === $oldCountry) {
                                    $orderManager->reassignPendingOrders($productId, $oldManagerId, $newManagerId);
                                    break;
                                }
                            }
                        }
                    }
                }
                
                // Mettre à jour les pays et prix
                if (isset($_POST['country_ids']) && is_array($_POST['country_ids'])) {
                    // Récupérer les pays actuels
                    $currentCountries = $manager->getProductCountries($productId);
                    $currentCountryIds = array_column($currentCountries, 'id');
                    
                    // Supprimer les pays qui ne sont plus sélectionnés
                    foreach ($currentCountryIds as $countryId) {
                        if (!in_array($countryId, $_POST['country_ids'])) {
                            $manager->removeProductCountry($productId, $countryId);
                        }
                    }
                    
                    // Ajouter ou mettre à jour les pays
                    foreach ($_POST['country_ids'] as $countryId) {
                        $countryId = intval($countryId);
                        $sellingPrice = floatval($_POST['country_prices'][$countryId] ?? 0);
                        
                        if (in_array($countryId, $currentCountryIds)) {
                            // Mettre à jour le prix si nécessaire
                            $stmt = $cnx->prepare("UPDATE product_countries SET selling_price = :price WHERE product_id = :product_id AND country_id = :country_id");
                            $stmt->execute([
                                'price' => $sellingPrice,
                                'product_id' => $productId,
                                'country_id' => $countryId
                            ]);
                        } else {
                            // Ajouter le nouveau pays
                            $manager->addProductCountry($productId, $countryId, $sellingPrice);
                        }
                    }
                }

                // Traitement des caractéristiques existantes
                if (isset($_POST['existing_char_id'])) {
                    foreach ($_POST['existing_char_id'] as $index => $charId) {
                        // Vérifier si la caractéristique doit être supprimée
                        if (isset($_POST['delete_characteristic']) && in_array($charId, $_POST['delete_characteristic'])) {
                            // Récupérer l'image associée pour la supprimer
                            $existingChar = $manager->getCaracteristicById($charId);
                            if (!empty($existingChar) && !empty($existingChar['image'])) {
                                $filePath = $uploadDirs['characteristics'] . $existingChar['image'];
                                if (file_exists($filePath)) {
                                    unlink($filePath);
                                }
                            }
                            // Supprimer la caractéristique
                            $manager->deleteCaracteristic($charId);
                            continue;
                        }

                        $charImage = safeStoredUploadName($_POST['existing_char_image'][$index] ?? '');

                        // Supprimer l'image si demandé
                        if (isset($_POST['delete_char_image']) && in_array($charId, $_POST['delete_char_image']) && !empty($charImage)) {
                            $filePath = $uploadDirs['characteristics'] . $charImage;
                            if (file_exists($filePath)) {
                                unlink($filePath);
                            }
                            $charImage = '';
                        }

                        // Traiter la nouvelle image
                        if (isset($_FILES['char_image']['tmp_name'][$index]) && $_FILES['char_image']['error'][$index] === UPLOAD_ERR_OK) {
                            // Supprimer l'ancienne image si elle existe
                            if (!empty($charImage)) {
                                $oldFilePath = $uploadDirs['characteristics'] . $charImage;
                                if (file_exists($oldFilePath)) {
                                    unlink($oldFilePath);
                                }
                            }

                            $charImage = safeUploadName($_FILES['char_image']['name'][$index], ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                            move_uploaded_file(
                                $_FILES['char_image']['tmp_name'][$index],
                                $uploadDirs['characteristics'] . $charImage
                            );
                        }

                        // Mettre à jour la caractéristique
                        $characteristicData = [
                            'title' => trim((string)($_POST['existing_char_title'][$index] ?? '')),
                            'image' => $charImage,
                            'description' => trim((string)($_POST['existing_char_description'][$index] ?? ''))
                        ];

                        $manager->updateCaracteristic($charId, $characteristicData);
                    }
                }

                // Traitement des nouvelles caractéristiques
                if (isset($_POST['characteristic_title'])) {
                    foreach ($_POST['characteristic_title'] as $key => $title) {
                        if (!empty($title)) {
                            $characteristicImage = '';
                            if (
                                isset($_FILES['characteristic_image']['tmp_name'][$key]) &&
                                $_FILES['characteristic_image']['error'][$key] === UPLOAD_ERR_OK
                            ) {

                                $characteristicImage = safeUploadName($_FILES['characteristic_image']['name'][$key], ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                move_uploaded_file(
                                    $_FILES['characteristic_image']['tmp_name'][$key],
                                    $uploadDirs['characteristics'] . $characteristicImage
                                );
                            }

                            $characteristicData = [
                                'product_id' => $productId,
                                'title' => trim((string)$title),
                                'image' => $characteristicImage,
                                'description' => trim((string)($_POST['characteristic_description'][$key] ?? ''))
                            ];

                            $manager->createCaracteristics($characteristicData);
                        }
                    }
                }

                // Traitement des vidéos existantes
                if (isset($_POST['existing_video_id'])) {
                    foreach ($_POST['existing_video_id'] as $index => $videoId) {
                        // Vérifier si la vidéo doit être supprimée
                        if (isset($_POST['delete_video']) && in_array($videoId, $_POST['delete_video'])) {
                            // Récupérer le fichier vidéo associé pour le supprimer
                            $existingVideo = $manager->getVideoById($videoId);
                            if (!empty($existingVideo) && !empty($existingVideo['video_url']) && !filter_var($existingVideo['video_url'], FILTER_VALIDATE_URL)) {
                                $filePath = $uploadDirs['videos'] . $existingVideo['video_url'];
                                if (file_exists($filePath)) {
                                    unlink($filePath);
                                }
                            }
                            // Supprimer la vidéo
                            $manager->deleteVideo($videoId);
                            continue;
                        }

                        $videoUrlRaw = $_POST['existing_video_url'][$index] ?? '';
                        $videoUrl = is_string($videoUrlRaw) && filter_var($videoUrlRaw, FILTER_VALIDATE_URL)
                            ? $videoUrlRaw
                            : safeStoredUploadName($videoUrlRaw);

                        // Supprimer le fichier vidéo si demandé
                        if (
                            isset($_POST['delete_video_file']) && in_array($videoId, $_POST['delete_video_file']) &&
                            !empty($videoUrl) && !filter_var($videoUrl, FILTER_VALIDATE_URL)
                        ) {
                            $filePath = $uploadDirs['videos'] . $videoUrl;
                            if (file_exists($filePath)) {
                                unlink($filePath);
                            }
                            $videoUrl = '';
                        }

                        // Traiter la nouvelle vidéo (fichier)
                        // Fichier de remplacement pour une vidéo existante
                        if (isset($_FILES['existing_video_file']['tmp_name'][$index]) && $_FILES['existing_video_file']['error'][$index] === UPLOAD_ERR_OK) {
                            // Supprimer l'ancien fichier s'il existe
                            if (!empty($videoUrl) && !filter_var($videoUrl, FILTER_VALIDATE_URL)) {
                                $oldFilePath = $uploadDirs['videos'] . $videoUrl;
                                if (file_exists($oldFilePath)) {
                                    unlink($oldFilePath);
                                }
                            }

                            $videoUrl = safeUploadName($_FILES['existing_video_file']['name'][$index], ['mp4', 'webm', 'ogg', 'mov']);
                            move_uploaded_file(
                                $_FILES['existing_video_file']['tmp_name'][$index],
                                $uploadDirs['videos'] . $videoUrl
                            );
                        }

                        // Mettre à jour la vidéo
                        $videoData = [
                            'video_url' => $videoUrl,
                            'texte' => trim((string)($_POST['existing_video_text'][$index] ?? ''))
                        ];

                        $manager->updateVideo($videoId, $videoData);
                    }
                }

                // Traitement des nouvelles vidéos
                if (isset($_FILES['new_video'])) {
                    foreach ($_FILES['new_video']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['new_video']['error'][$key] === UPLOAD_ERR_OK) {
                            $videoName = safeUploadName($_FILES['new_video']['name'][$key], ['mp4', 'webm', 'ogg', 'mov']);
                            if (move_uploaded_file($tmp_name, $uploadDirs['videos'] . $videoName)) {
                                $videoData = [
                                    'product_id' => $productId,
                                    'video_url' => $videoName,
                                    'texte' => trim((string)($_POST['new_video_text'][$key] ?? ''))
                                ];

                                $manager->createVideos($videoData);
                            }
                        }
                    }
                }
                // Compat: si le formulaire a envoyé des nouvelles vidéos sous le nom "video[]"
                elseif (isset($_FILES['video'])) {
                    foreach ($_FILES['video']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['video']['error'][$key] === UPLOAD_ERR_OK) {
                            $videoName = safeUploadName($_FILES['video']['name'][$key], ['mp4', 'webm', 'ogg', 'mov']);
                            if (move_uploaded_file($tmp_name, $uploadDirs['videos'] . $videoName)) {
                                $videoData = [
                                    'product_id' => $productId,
                                    'video_url' => $videoName,
                                    'texte' => trim((string)($_POST['video_text'][$key] ?? ''))
                                ];
                                $manager->createVideos($videoData);
                            }
                        }
                    }
                }


                // Traitement des packs existants
                if (isset($_POST['existing_pack_id'])) {
                    foreach ($_POST['existing_pack_id'] as $index => $packId) {
                        // Vérifier si le pack doit être supprimé
                        if (isset($_POST['delete_pack']) && in_array($packId, $_POST['delete_pack'])) {
                            $existingPack = $manager->getPackById($packId);
                            if (!empty($existingPack) && !empty($existingPack['image'])) {
                                $filePath = $uploadDirs['packs'] . $existingPack['image'];
                                if (file_exists($filePath)) {
                                    unlink($filePath);
                                }
                            }
                            $manager->deletePacks($packId);
                            continue;
                        }

                        // Récupérer les valeurs envoyées
                        $packName        = $_POST['existing_pack_name'][$index] ?? '';
                        $packQuantity     = (int)($_POST['existing_pack_quantity'][$index] ?? 0);
                        $packPrice    = (int)($_POST['existing_pack_price'][$index] ?? 0);
                        $packImage        = safeStoredUploadName($_POST['existing_pack_image'][$index] ?? '');

                        // Suppression d’image si demandé
                        if (
                            isset($_POST['delete_pack_image']) &&
                            in_array($packId, $_POST['delete_pack_image']) &&
                            !empty($packImage)
                        ) {
                            $filePath = $uploadDirs['packs'] . $packImage;
                            if (file_exists($filePath)) {
                                unlink($filePath);
                            }
                            $packImage = '';
                        }

                        // Upload nouvelle image pour un pack existant
                        if (isset($_FILES['existing_pack_image_file']['tmp_name'][$index]) && $_FILES['existing_pack_image_file']['error'][$index] === UPLOAD_ERR_OK) {
                            if (!empty($packImage)) {
                                $oldFilePath = $uploadDirs['packs'] . $packImage;
                                if (file_exists($oldFilePath)) {
                                    unlink($oldFilePath);
                                }
                            }

                            $packImage = safeUploadName($_FILES['existing_pack_image_file']['name'][$index], ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                            move_uploaded_file(
                                $_FILES['existing_pack_image_file']['tmp_name'][$index],
                                $uploadDirs['packs'] . $packImage
                            );
                        }

                        // Mettre à jour le pack
                        $packData = [
                            'pack_name'           => trim((string)$packName),
                            'pack_image'           => $packImage,
                            'pack_quantity'        => $packQuantity,
                            'pack_price'           => $packPrice,
                        ];

                        $manager->updatePack($packId, $packData);
                    }
                }

                // Traitement des nouveaux packs (ton code adapté)
                if (isset($_POST['pack_titre'])) {
                    foreach ($_POST['pack_titre'] as $key => $titre) {
                        if (!empty($titre)) {
                            $packImage = '';

                            if (
                                isset($_FILES['pack_image']['tmp_name'][$key]) &&
                                $_FILES['pack_image']['error'][$key] === UPLOAD_ERR_OK
                            ) {
                                $packImage = safeUploadName($_FILES['pack_image']['name'][$key], ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                move_uploaded_file(
                                    $_FILES['pack_image']['tmp_name'][$key],
                                    $uploadDirs['packs'] . $packImage
                                );
                            }

                            $packData = [
                                'product_id'      => $productId,
                                'titre'           => trim((string)$titre),
                                'image'           => $packImage,
                                'quantity'        => (int)($_POST['pack_quantity'][$key] ?? 0),
                                'price_reduction' => (int)($_POST['pack_price_reduction'][$key] ?? 0),
                                'price_normal'    => (int)($_POST['pack_price'][$key] ?? 0)
                            ];

                            $manager->createPacks($packData);
                        }
                    }
                }


                $message = "Produit mis à jour avec succès !";
                header('Location: index.php?message=' . urlencode($message));
                exit;
            } catch (Exception $e) {
                $message = "Erreur lors de la mise à jour du produit : " . $e->getMessage();
                header('Location: update.php?id=' . $productId . '&error=' . urlencode($message));
                exit;
            }
        }
        break;

    default:
        header("Location: /error.php?code=400");
        exit;
}
