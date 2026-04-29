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

                $packId = !empty($_POST['pack_id']) ? htmlspecialchars($_POST['pack_id']) : null;
                $productId = htmlspecialchars($_POST['product_id']);

                if($packId != null) {
                    $pack = $packManager->getPackById($packId);
                }

                //$pack = $packManager->getPackById($packId);
                $product = $productManager->getProducts($productId);

                // Le formulaire envoie l’id du pays (client_country = id). On garde cet id pour la commande.
                $clientCountryId = (int) ($_POST['client_country'] ?? 0);
                $countryManager = new Country($cnx);
                $clientCountryCode = $countryManager->getCodeById($clientCountryId);
                if (!$clientCountryId || !$clientCountryCode) {
                    $_SESSION['order_message'] = "Pays invalide. Veuillez réessayer.";
                    header("Location: ../../index.php?id=" . $productId);
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
                    header("Location: ../../index.php?id=" . $productId);
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
                    'client_adress' => htmlspecialchars($_POST['client_adress']),
                    'client_phone'  => htmlspecialchars($_POST['client_phone']),
                    'client_note'   => htmlspecialchars($_POST['client_note']),
                    'purchase_price'    => $product['purchase_price'],
                    'total_price'   => !empty($pack['price']) ? $pack['price'] : $sellingPrice,
                    'quantity'      => !empty($pack['quantity']) ? $pack['quantity'] : 1,
                    'manager_id'   => $managerId,
                ];



                if ($orderManager->CreateOrder($data)) {
                    try {
                        $push = new \src\PushNotification($cnx);
                        $push->notifyNewOrder(
                            (string)($_POST['client_name'] ?? ''),
                            (string)($product['name'] ?? '')
                        );
                    } catch (\Throwable $e) {
                        // Ne pas bloquer la commande si la push échoue
                    }
                    $_SESSION['order_message'] = "Votre commande a été passée avec succès. Nous vous contacterons bientôt.";
                    header("Location: ../../index.php?id=" . $productId);
                } else {
                    $_SESSION['order_message'] = "Une erreur est survenue lors de la passation de votre commande. Veuillez réessayer.";
                    header("Location: ../../index.php?id=" . $productId);
                }
            }
            break;

        case 'update':
            if (isset($_POST['order_id'])) {
                $orderId = (int)($_POST['order_id'] ?? 0);
                $existingOrder = $orderManager->getOrderById($orderId);

                if (!$existingOrder) {
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'fetch') {
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
                $managerNote = htmlspecialchars($_POST['manager_note'] ?? '');

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

                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'fetch') {
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
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'fetch') {
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
