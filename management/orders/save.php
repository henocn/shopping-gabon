<?php
require_once __DIR__ . '/../../utils/admin-session.php';
require_once __DIR__ . '/../../utils/csrf.php';

$requestedAction = isset($_POST['valider']) && is_string($_POST['valider'])
    ? strtolower(trim($_POST['valider']))
    : '';

// Les commandes publiques utilisent leur session propre; les mises à jour
// d'administration utilisent le cookie persistant de l'espace admin.
if ($requestedAction === 'update') {
    startAdminSession();
} else {
    // Si une session admin est active, la fermer avant de démarrer la session publique
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    session_start();
}

require("../../vendor/autoload.php");

use src\Connectbd;
use src\Product;
use src\Order;
use src\Pack;
use src\Depense;
use src\Country;
use src\User;

$cnx = Connectbd::getConnection();


function orderPostString(string $key, int $maxLength = 255): string
{
    if (!isset($_POST[$key]) || !is_string($_POST[$key])) {
        return '';
    }

    $value = trim($_POST[$key]);
    return function_exists('mb_substr')
        ? mb_substr($value, 0, $maxLength)
        : substr($value, 0, $maxLength);
}

if (isset($_POST['valider']) && is_string($_POST['valider'])) {
    $connect = strtolower(trim($_POST['valider']));
    $productManager = new Product($cnx);
    $packManager = new Pack($cnx);
    $orderManager = new Order($cnx);
    $depenseManager = new Depense($cnx);


    switch ($connect) {

        case 'commander':
            verifyCsrfToken();
            if (
                isset($_POST['product_id']) &&
                isset($_POST['client_name']) &&
                isset($_POST['client_country']) &&
                isset($_POST['client_phone'])
            ) {
                $productId = is_scalar($_POST['product_id'])
                    ? filter_var($_POST['product_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                    : false;
                $selectedCountryId = is_scalar($_POST['client_country'])
                    ? filter_var($_POST['client_country'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                    : false;
                $clientName = orderPostString('client_name', 150);
                $clientPhone = orderPostString('client_phone', 40);
                $clientAddress = orderPostString('client_adress', 255);
                $clientNote = orderPostString('client_note', 1000);
                if ($productId === false || $selectedCountryId === false || $clientName === '' || strlen($clientName) < 2 || $clientPhone === '' || strlen($clientPhone) < 5 || $clientAddress === '') {
                    $_SESSION['order_message'] = "Données de commande invalides. Veuillez réessayer.";
                    header("Location: " . (isset($redirectUrl) ? $redirectUrl : '../../index.php'));
                    exit;
                }
                $redirectUrl = "../../index.php?id=" . $productId . ($selectedCountryId > 0 ? "&country=" . $selectedCountryId : "");

                // La limite côté navigateur améliore l'UX, mais la règle doit être
                // appliquée ici pour résister aux onglets et requêtes simultanées.
                $orderLimitWindow = 48 * 60 * 60;
                $orderLimits = isset($_SESSION['order_limits']) && is_array($_SESSION['order_limits'])
                    ? $_SESSION['order_limits']
                    : [];
                $orderLimitState = isset($orderLimits[$productId]) && is_array($orderLimits[$productId])
                    ? $orderLimits[$productId]
                    : ['count' => 0, 'expires_at' => 0];
                if ((int)($orderLimitState['expires_at'] ?? 0) <= time()) {
                    $orderLimitState = ['count' => 0, 'expires_at' => time() + $orderLimitWindow];
                }
                if ((int)($orderLimitState['count'] ?? 0) >= 2) {
                    $_SESSION['order_message'] = "La limite de 2 commandes pour ce produit est atteinte.";
                    header("Location: " . $redirectUrl);
                    exit;
                }

                // Anti-spam: Prevent double submissions
                $orderHash = hash('sha256', $productId . $clientName . $clientPhone);
                if (isset($_SESSION['last_order_hash']) && $_SESSION['last_order_hash'] === $orderHash && isset($_SESSION['last_order_time']) && (time() - $_SESSION['last_order_time']) < 60) {
                    $_SESSION['order_message'] = "Votre commande a déjà été enregistrée. Merci !";
                    header("Location: " . $redirectUrl);
                    exit;
                }
                $_SESSION['last_order_hash'] = $orderHash;
                $_SESSION['last_order_time'] = time();

                $packId = null;
                if (isset($_POST['pack_id']) && $_POST['pack_id'] !== '') {
                    $packId = is_scalar($_POST['pack_id'])
                        ? filter_var($_POST['pack_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                        : false;
                    if ($packId === false) {
                        $packId = null;
                    }
                }
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
                $clientCountryId = $selectedCountryId;
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
                    'client_name'   => $clientName,
                    'client_country' => $clientCountryId,
                    'client_adress' => $clientAddress,
                    'client_phone'  => $clientPhone,
                    'client_note'   => $clientNote,
                    'purchase_price'    => $product['purchase_price'],
                    'total_price'   => !empty($pack['price']) ? $pack['price'] : $sellingPrice,
                    'unit_price'    => !empty($pack['price'])
                        ? (int) round(((int) $pack['price']) / max(1, (int) ($pack['quantity'] ?? 1)))
                        : (int) $sellingPrice,
                    'quantity'      => !empty($pack['quantity']) ? $pack['quantity'] : 1,
                    'manager_id'   => $managerId,
                ];



                if ($orderManager->CreateOrder($data)) {
                    $orderLimitState['count'] = (int)($orderLimitState['count'] ?? 0) + 1;
                    $_SESSION['order_limits'][$productId] = $orderLimitState;

                    // ── Push notification ──
                    try {
                        $push = new \src\PushNotification($cnx);
                        $push->notifyNewOrder(
                            $clientName,
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

                    // ── Stocker les données Purchase en session pour le Pixel navigateur (déduplication avec CAPI) ---
                    // Ces données seront lues dans index.php pour envoyer l'événement via le navigateur
                    $_SESSION['fb_purchase_data'] = [
                        'value' => $data['total_price'] ?? 0,
                        'currency' => $currencyCode ?? 'XOF',
                        'content_ids' => $packId ?? $productId,
                        'content_name' => $product['name'] ?? '',
                        'event_id' => $fbEventId ?? bin2hex(random_bytes(12))
                    ];

                    $_SESSION['order_message'] = "Votre commande a été passée avec succès. Nous vous contacterons bientôt.";
                    header("Location: " . $redirectUrl);
                } else {
                    $_SESSION['order_message'] = "Une erreur est survenue lors de la passation de votre commande. Veuillez réessayer.";
                    header("Location: " . $redirectUrl);
                }
            }
            break;

        case 'update':
            verifyCsrfToken();
            $isAjax = isset($_POST['is_ajax']) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && in_array(strtolower($_SERVER['HTTP_X_REQUESTED_WITH']), ['fetch', 'xmlhttprequest'], true));
            $authenticatedUserId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $authenticatedUser = $authenticatedUserId !== false ? (new User($cnx))->getUserById($authenticatedUserId) : null;
            if (!$authenticatedUser || (int)($authenticatedUser['is_active'] ?? 0) !== 1 || !in_array((int)($authenticatedUser['role'] ?? 0), [0, 1], true)) {
                if ($isAjax) {
                    header('Content-Type: application/json', true, 403);
                    echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
                    exit;
                }
                header('Location: /error.php?code=403');
                exit;
            }

            if (isset($_POST['order_id'])) {
                $orderId = is_scalar($_POST['order_id'])
                    ? filter_var($_POST['order_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                    : false;
                if ($orderId === false) {
                    header('Content-Type: application/json', true, 400);
                    echo json_encode(['success' => false, 'error' => 'Identifiant de commande invalide']);
                    exit;
                }
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

                if ((int)($authenticatedUser['role'] ?? 0) !== 1 && (int)($existingOrder['manager_id'] ?? 0) !== (int) $authenticatedUserId) {
                    if ($isAjax) {
                        header('Content-Type: application/json', true, 403);
                        echo json_encode(['success' => false, 'error' => 'Commande non attribuée à cet utilisateur']);
                        exit;
                    }
                    header('Location: /error.php?code=403');
                    exit;
                }

                $allowedStatuses = ['new', 'remind', 'unreachable', 'processing', 'deliver', 'canceled'];
                $incomingStatus = isset($_POST['newstat']) && is_string($_POST['newstat'])
                    ? strtolower(trim($_POST['newstat']))
                    : '';
                $newStatus = in_array($incomingStatus, $allowedStatuses, true)
                    ? $incomingStatus
                    : (string)$existingOrder['newstat'];
                $updatedQuantityRaw = isset($_POST['quantity']) && is_scalar($_POST['quantity'])
                    ? $_POST['quantity']
                    : $existingOrder['quantity'];
                $updatedQuantityInput = filter_var($updatedQuantityRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $updatedTotalInput = $_POST['total_price'] ?? $existingOrder['total_price'];
                $updatedTotal = is_numeric($updatedTotalInput) ? (float) $updatedTotalInput : -1;
                $managerNote = orderPostString('manager_note', 1000);
                if ($updatedQuantityInput === false || !is_finite($updatedTotal) || $updatedTotal < 0) {
                    if ($isAjax) {
                        header('Content-Type: application/json', true, 400);
                        echo json_encode(['success' => false, 'error' => 'Valeurs de commande invalides']);
                        exit;
                    }
                    header('Location: index.php?message=' . urlencode('Valeurs de commande invalides.'));
                    exit;
                }
                $updatedQuantity = $updatedQuantityInput;

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
                $deliveryFeeInput = $_POST['delivery_fee'] ?? null;
                $deliveryFee = is_numeric($deliveryFeeInput) ? (float) $deliveryFeeInput : 0;
                if ($newStatus === 'deliver' && is_finite($deliveryFee) && $deliveryFee > 0) {
                    $depenseData = [
                        'type'        => 'livraison',
                        'product_id'  => (int)$existingOrder['product_id'],
                        'cout'        => (int) $deliveryFee,
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
