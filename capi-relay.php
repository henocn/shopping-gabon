<?php

/**
 * Relais Conversions API pour les événements navigateur (hors Purchase).
 *
 * Appelé en best-effort par le JS (sendBeacon/fetch) pour donner un filet serveur
 * aux événements qu'un bloqueur de pub aurait pu empêcher le Pixel d'envoyer.
 * Ne bloque jamais l'expérience utilisateur : répond toujours 204.
 */

require __DIR__ . '/vendor/autoload.php';

use src\FacebookCAPI;

const ALLOWED_EVENTS = [
    'PageView',
    'ViewContent',
    'InitiateCheckout',
    'Lead',
    'AddToCart',
    'AddPaymentInfo',
    'Search',
    'CompleteRegistration',
];

const MAX_PAYLOAD_BYTES = 4096;

http_response_code(204);

$raw = file_get_contents('php://input', false, null, 0, MAX_PAYLOAD_BYTES + 1);

if ($raw === false || $raw === '' || strlen($raw) > MAX_PAYLOAD_BYTES) {
    exit;
}

$data = json_decode($raw, true);

if (!is_array($data)) {
    exit;
}

$eventName = trim((string)($data['event_name'] ?? ''));
$eventId = trim((string)($data['event_id'] ?? ''));

if (!in_array($eventName, ALLOWED_EVENTS, true) || $eventId === '' || strlen($eventId) > 100) {
    exit;
}

$customData = is_array($data['custom_data'] ?? null) ? $data['custom_data'] : [];
$currency = is_string($customData['currency'] ?? null) ? $customData['currency'] : 'XOF';
$sourceUrl = is_string($data['source_url'] ?? null) ? substr($data['source_url'], 0, 500) : null;

try {
    $fbCapi = new FacebookCAPI();
    if ($fbCapi->isConfigured()) {
        $fbCapi->sendEvent(
            $eventName,
            $customData,
            [],
            $eventId,
            [
                'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'fbp'        => trim((string)($data['fbp'] ?? '')),
                'fbc'        => trim((string)($data['fbc'] ?? '')),
            ],
            $currency,
            $sourceUrl
        );
    }
} catch (\Throwable $e) {
    error_log('[CAPI relay] Erreur envoi ' . $eventName . ': ' . $e->getMessage());
}
