<?php
session_start();

require("../../vendor/autoload.php");

use src\Connectbd;
use src\Product;
use src\Order;
use src\Pack;
use src\Depense;
use src\Country;

$cnx = Connectbd::getConnection();


if (isset($_POST['valider'])) {
    $connect = strtolower(htmlspecialchars($_POST['valider']));
    $productManager = new Product($cnx);
    $packManager = new Pack($cnx);
    $orderManager = new Order($cnx);
    $depenseManager = new Depense($cnx);


    switch ($connect) {

        case 'commander':
            if (
                isset($_POST['product_id']) &&
                isset($_POST['client_name']) &&
                isset($_POST['client_country']) &&
                isset($_POST['client_phone'])
            ) {
                $productId = (int)($_POST['product_id'] ?? 0);
                $selectedCountryId = (int)($_POST['client_country'] ?? 0);
                $redirectUrl = "../../index.php?id=" . $productId . ($selectedCountryId > 0 ? "&country=" . $selectedCountryId : "");

                // Anti-spam: Prevent double submissions
                $orderHash = md5($_POST['product_id'] . $_POST['client_name'] . $_POST['client_phone']);
                if (isset($_SESSION['last_order_hash']) && $_SESSION['last_order_hash'] === $orderHash && isset($_SESSION['last_order_time']) && (time() - $_SESSION['last_order_time']) < 60) {
                    $_SESSION['order_message'] = "Votre commande a déjà été enregistrée. Merci !";
                    header("Location: " . $redirectUrl);
                    exit;
                }
                $_SESSION['last_order_hash'] = $orderHash;
                $_SESSION['last_order_time'] = time();

                $packId = !empty($_POST['pack_id']) ? htmlspecialchars($_POST['pack_id']) : null;
                $pack = null;

                if($packId != null) {
                    $pack = $packManager->getPackById($packId);
                }

                //$pack = $packManager->getPackById($packId);
                $product = $productManager->getProducts($productId);
                if (!$product) {
                    $_SESSION['order_message'] = "Produit introuvable. Veuillez réessayer.";
                    header("Location: " . $redirectUrl);
                    exit;
                }

                // Le formulaire envoie l’id du pays (client_country = id). On garde cet id pour la commande.
                $clientCountryId = (int) ($_POST['client_country'] ?? 0);
                $countryManager = new Country($cnx);
                $clientCountryCode = $countryManager->getCodeById($clientCountryId);
                if (!$clientCountryId || !$clientCountryCode) {
                    $_SESSION['order_message'] = "Pays invalide. Veuillez réessayer.";
                    header("Location: " . $redirectUrl);
                    exit;
                }

                $productCountries = $productManager->getProductCountries($productId);
                $sellingPrice = 0;
                foreach ($productCountries as $countryPrice) {
                    if ((int) $countryPrice['id'] === $clientCountryId) {
                        $sellingPrice = $countryPrice['selling_price'];
                        break;
                    }
                }
                if ($sellingPrice == 0) {
                    $_SESSION['order_message'] = "Une erreur est survenue lors de la passation de votre commande. Veuillez réessayer.";
                    header("Location: " . $redirectUrl);
                    exit;
                }

                // Manager du produit pour ce pays uniquement (assistant dont le pays = pays du client)
                $productManagers = $productManager->getProductManagers($productId);
                $managerId = 0;
                foreach ($productManagers as $manager) {
                    if (isset($manager['country_code']) && (string) $manager['country_code'] === $clientCountryCode) {
                        $managerId = (int) $manager['id'];
                        break;
                    }
                }

                $data = [
                    'product_id'    => $productId,
                    'pack_id'       => $packId,
                    'client_name'   => $_POST['client_name'],
                    'client_country' => $clientCountryId,
                    'client_adress' => trim((string)($_POST['client_adress'] ?? '')),
                    'client_phone'  => trim((string)($_POST['client_phone'] ?? '')),
                    'client_note'   => trim((string)($_POST['client_note'] ?? '')),
                    'purchase_price'    => $product['purchase_price'],
                    'total_price'   => !empty($pack['price']) ? $pack['price'] : $sellingPrice,
                    'unit_price'    => !empty($pack['price'])
                        ? (int) round(((int) $pack['price']) / max(1, (int) ($pack['quantity'] ?? 1)))
                        : (int) $sellingPrice,
                    'quantity'      => !empty($pack['quantity']) ? $pack['quantity'] : 1,
                    'manager_id'   => $managerId,
                ];



                if ($orderManager->CreateOrder($data)) {
                    // ── Push notification ──
                    try {
                        $push = new \src\PushNotification($cnx);
                        $push->notifyNewOrder(
                            (string)($_POST['client_name'] ?? ''),
                            (string)($product['name'] ?? ''),
                            isset($data['total_price']) ? (int)$data['total_price'] : null
                        );
                    } catch (\Throwable $e) {
                        // Ne pas bloquer la commande si la push échoue
                    }

                    // ── Facebook Conversions API (CAPI) — envoi serveur Purchase ──
                    try {
                        $fbCapi = new \src\FacebookCAPI();
                        if ($fbCapi->isConfigured()) {
                            // Récupérer l'event_id pour la déduplication avec le Pixel navigateur
                            $fbEventId = trim((string)($_POST['fb_event_id'] ?? ''));
                            if (empty($fbEventId)) {
                                // Générer un event_id si le navigateur ne l'a pas fourni
                                $fbEventId = bin2hex(random_bytes(12));
                            }

                            // Déterminer la devise
                            $normalizedCountryCode = strtoupper(trim($clientCountryCode));
                            $currencyCode = 'XOF';
                            if ($normalizedCountryCode === 'GN') {
                                $currencyCode = 'GNF';
                            }

                            // Récupérer le phone_code du pays pour normalisation du téléphone
                            $phoneCode = '';
                            foreach ($productCountries as $ctry) {
                                if ((int)$ctry['id'] === $clientCountryId) {
                                    $phoneCode = $ctry['phone_code'] ?? '';
                                    break;
                                }
                            }

                            $capiResults = $fbCapi->sendPurchaseEvent(
                                [
                                    'product_id'  => $productId,
                                    'pack_id'     => $packId,
                                    'total_price' => $data['total_price'],
                                    'unit_price'  => $data['unit_price'],
                                    'quantity'    => $data['quantity'],
                                ],
                                [
                                    'client_name'  => $_POST['client_name'] ?? '',
                                    'client_phone' => $_POST['client_phone'] ?? '',
                                    'country_code' => $clientCountryCode,
                                    'phone_code'   => $phoneCode,
                                ],
                                $fbEventId,
                                [
                                    'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
                                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                                    'fbp'        => trim((string)($_POST['fb_fbp'] ?? ($_COOKIE['_fbp'] ?? ''))),
                                    'fbc'        => trim((string)($_POST['fb_fbc'] ?? ($_COOKIE['_fbc'] ?? ''))),
                                    'source_url' => 'https://luxemarket.click/index.php?id=' . $productId,
                                ],
                                $currencyCode
                            );

                            foreach ($capiResults as $pixelId => $result) {
                                if (!($result['success'] ?? false)) {
                                    error_log('[CAPI] Purchase échoué pixel ' . $pixelId . ': ' . ($result['error'] ?? 'Erreur inconnue'));
                                }
                            }
                        } else {
                            error_log('[CAPI] Configuration absente: aucun pixel/token chargé depuis src/.env');
                        }
                    } catch (\Throwable $e) {
                        // Ne JAMAIS bloquer la commande si le CAPI échoue
                        error_log('[CAPI] Erreur envoi Purchase: ' . $e->getMessage());
                    }

                    $_SESSION['order_message'] = "Votre commande a été passée avec succès. Nous vous contacterons bientôt.";
                    header("Location: " . $redirectUrl);
                } else {
                    $_SESSION['order_message'] = "Une erreur est survenue lors de la passation de votre commande. Veuillez réessayer.";
                    header("Location: " . $redirectUrl);
                }
            }
            break;

        case 'update':
            if (isset($_POST['order_id'])) {
                $orderId = (int)($_POST['order_id'] ?? 0);
                $existingOrder = $orderManager->getOrderById($orderId);

                if (!$existingOrder) {
                    $isAjax = isset($_POST['is_ajax']) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && in_array(strtolower($_SERVER['HTTP_X_REQUESTED_WITH']), ['fetch', 'xmlhttprequest']));
                if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => false,
                            'error'   => 'Commande introuvable'
                        ]);
                        exit;
                    }

                    $message = urlencode("Commande introuvable.");
                    header("Location: index.php?message=" . $message);
                    exit;
                }

                $allowedStatuses = ['new', 'remind', 'unreachable', 'processing', 'deliver', 'canceled'];
                $incomingStatus = strtolower(trim((string)($_POST['newstat'] ?? '')));
                $newStatus = in_array($incomingStatus, $allowedStatuses, true)
                    ? $incomingStatus
                    : (string)$existingOrder['newstat'];
                $updatedQuantity = (int)($_POST['quantity'] ?? $existingOrder['quantity']);
                $updatedTotal = (float)($_POST['total_price'] ?? $existingOrder['total_price']);
                $managerNote = trim((string)($_POST['manager_note'] ?? ''));

                $data = [
                    'id'           => $orderId,
                    'quantity'     => $updatedQuantity,
                    'total_price'  => $updatedTotal,
                    'newstat'      => $newStatus,
                    'manager_note' => $managerNote,
                    'updated_at'   => date('Y-m-d H:i:s'),
                ];

                $orderManager->updateOrder($data);

                $shouldDecreaseStock = $newStatus === 'deliver' && $existingOrder['newstat'] !== 'deliver';

                if ($shouldDecreaseStock && !empty($existingOrder['product_id'])) {
                    $productManager->decrementQuantity((int)$existingOrder['product_id'], $updatedQuantity);
                }

                // Enregistrer les frais de livraison si fournis
                if ($newStatus === 'deliver' && isset($_POST['delivery_fee']) && $_POST['delivery_fee'] > 0) {
                    $depenseData = [
                        'type'        => 'livraison',
                        'product_id'  => (int)$existingOrder['product_id'],
                        'cout'        => (int)$_POST['delivery_fee'],
                        'date'        => date('Y-m-d H:i:s'),
                        'description' => 'Livraison'
                    ];
                    $depenseManager->createDepense($depenseData);
                }

                $isAjax = isset($_POST['is_ajax']) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && in_array(strtolower($_SERVER['HTTP_X_REQUESTED_WITH']), ['fetch', 'xmlhttprequest']));
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'newstat'  => $data['newstat']
                    ]);
                    exit;
                }

                $message = urlencode("Commande mise à jour avec succès.");
                header("Location: index.php?message=" . $message);
                exit;
            } else {
                $isAjax = isset($_POST['is_ajax']) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && in_array(strtolower($_SERVER['HTTP_X_REQUESTED_WITH']), ['fetch', 'xmlhttprequest']));
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'error'   => 'Données manquantes pour mettre à jour la commande'
                    ]);
                    exit;
                }

                $message = urlencode("Données manquantes pour mettre à jour le statut de la commande.");
                header("Location: index.php?message=" . $message);
                exit;
            }
        default:
            header("Location: /error.php?code=400");
    }
} else {
    header("Location: /error.php?code=400");
}
