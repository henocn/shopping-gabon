<?php

namespace src;

/**
 * Facebook Conversions API (CAPI) — Envoi serveur-à-serveur.
 *
 * Envoie les événements de conversion directement à l'API Graph de Facebook
 * pour compléter le Pixel navigateur et améliorer la collecte de données.
 *
 * Endpoint : POST https://graph.facebook.com/v25.0/{PIXEL_ID}/events
 *
 * @see https://developers.facebook.com/docs/marketing-api/conversions-api
 */
class FacebookCAPI
{
    /** @var string Version de l'API Graph */
    private const API_VERSION = 'v25.0';

    /** @var string URL de base de l'API */
    private const BASE_URL = 'https://graph.facebook.com';

    /** @var int Timeout cURL en secondes */
    private const TIMEOUT = 5;

    /** @var array<string, string> Mapping des Pixel IDs vers leur Token d'accès système respectif */
    private array $pixelTokens = [];

    /** @var bool Mode test (envoie vers le endpoint test_event_code) */
    private bool $testMode;

    /** @var string|null Code d'événement test (depuis Events Manager) */
    private ?string $testEventCode;

    /**
     * @param array{pixels?: array<string, string>, test_mode?: bool, test_event_code?: string}|null $config
     */
    public function __construct(?array $config = null)
    {
        if ($config === null) {
            $config = $this->loadConfig();
        }

        $this->pixelTokens = $config['pixels'] ?? [];
        $this->testMode = (bool)($config['test_mode'] ?? false);
        $this->testEventCode = $config['test_event_code'] ?? null;
    }

    /**
     * Vérifie si la configuration est valide pour envoyer des événements.
     */
    public function isConfigured(): bool
    {
        return !empty($this->pixelTokens);
    }

    /**
     * Envoie un événement Purchase après la création réussie d'une commande.
     *
     * @param array $orderData Données de la commande (product_id, total_price, quantity, etc.)
     * @param array $clientData Données client (client_name, client_phone, client_country)
     * @param string $eventId ID unique pour la déduplication avec le Pixel navigateur
     * @param array $browserData Données navigateur (_fbp, _fbc, user_agent, ip)
     * @param string $currency Code devise (XOF, GNF, etc.)
     * @return array Résultats de l'envoi pour chaque pixel
     */
    public function sendPurchaseEvent(
        array $orderData,
        array $clientData,
        string $eventId,
        array $browserData = [],
        string $currency = 'XOF'
    ): array {
        // Construire les custom_data
        $customData = [
            'value' => (float)($orderData['total_price'] ?? 0),
            'content_ids' => [(string)($orderData['product_id'] ?? '')],
            'content_type' => 'product',
            'contents' => [
                [
                    'id' => (string)($orderData['product_id'] ?? ''),
                    'quantity' => (int)($orderData['quantity'] ?? 1),
                    'item_price' => (float)($orderData['unit_price'] ?? $orderData['total_price'] ?? 0),
                ]
            ],
            'num_items' => (int)($orderData['quantity'] ?? 1),
        ];

        // Si un pack est sélectionné, adapter les content_ids
        if (!empty($orderData['pack_id'])) {
            $customData['content_ids'] = [(string)$orderData['pack_id']];
            $customData['contents'][0]['id'] = (string)$orderData['pack_id'];
        }

        return $this->sendEvent('Purchase', $customData, $clientData, $eventId, $browserData, $currency);
    }

    /**
     * Envoie un événement générique (PageView, ViewContent, InitiateCheckout, ...)
     * à l'API Graph de Facebook. Utilisé à la fois par sendPurchaseEvent() et par
     * le relais des événements navigateur (capi-relay.php).
     *
     * @param array $customData Données spécifiques à l'événement (content_ids, value, ...)
     * @param array $clientData Données client (client_name, client_phone, client_country) — vide pour les events pré-achat
     * @param array $browserData Données navigateur (_fbp, _fbc, user_agent, ip, source_url)
     * @return array Résultats de l'envoi pour chaque pixel
     */
    public function sendEvent(
        string $eventName,
        array $customData,
        array $clientData,
        string $eventId,
        array $browserData = [],
        string $currency = 'XOF',
        ?string $eventSourceUrl = null
    ): array {
        $userData = $this->buildUserData($clientData, $browserData);

        if (!isset($customData['currency'])) {
            $customData['currency'] = $currency;
        }

        $eventPayload = [
            'event_name' => $eventName,
            'event_time' => time(),
            'event_id' => $eventId,
            'event_source_url' => $eventSourceUrl ?? ($browserData['source_url'] ?? ''),
            'action_source' => 'website',
            'user_data' => $userData,
            'custom_data' => $customData,
        ];

        if (empty($eventPayload['event_source_url'])) {
            unset($eventPayload['event_source_url']);
        }

        // Envoyer à tous les pixels en parallèle : le temps total est borné par le
        // pixel le plus lent, pas par la somme de tous (important dès qu'il y a
        // plus de 2-3 pixels, sinon un Purchase peut bloquer la commande plusieurs secondes).
        return $this->dispatchToAllPixels($eventPayload);
    }

    /**
     * Construit les user_data avec hashage SHA-256.
     *
     * @param array $clientData Données brutes du client
     * @param array $browserData Données navigateur
     * @return array Données utilisateur formatées pour l'API
     */
    private function buildUserData(array $clientData, array $browserData): array
    {
        $userData = [];

        // Téléphone — normaliser et hasher
        if (!empty($clientData['client_phone'])) {
            $phone = $this->normalizePhone(
                $clientData['client_phone'],
                $clientData['phone_code'] ?? ''
            );
            if ($phone) {
                $userData['ph'] = [hash('sha256', $phone)];
            }
        }

        // Nom — séparer en prénom/nom si possible, normaliser et hasher
        if (!empty($clientData['client_name'])) {
            $nameParts = $this->splitName($clientData['client_name']);
            if ($nameParts['first']) {
                $userData['fn'] = [hash('sha256', $nameParts['first'])];
            }
            if ($nameParts['last']) {
                $userData['ln'] = [hash('sha256', $nameParts['last'])];
            }
        }

        // Code pays
        if (!empty($clientData['country_code'])) {
            $userData['country'] = [hash('sha256', strtolower($clientData['country_code']))];
        }

        // IP client
        if (!empty($browserData['ip'])) {
            $userData['client_ip_address'] = $browserData['ip'];
        }

        // User Agent
        if (!empty($browserData['user_agent'])) {
            $userData['client_user_agent'] = $browserData['user_agent'];
        }

        // Cookie _fbp (First-Party Browser Pixel cookie)
        if (!empty($browserData['fbp'])) {
            $userData['fbp'] = $browserData['fbp'];
        }

        // Cookie _fbc (Click ID cookie, vient de fbclid dans l'URL)
        if (!empty($browserData['fbc'])) {
            $userData['fbc'] = $browserData['fbc'];
        }

        return $userData;
    }

    /**
     * Normalise un numéro de téléphone pour le format E.164 attendu par Facebook.
     */
    private function normalizePhone(string $phone, string $phoneCode = ''): string
    {
        // Supprimer tout sauf les chiffres et le +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Si le numéro ne commence pas par +, ajouter le code pays
        if (!str_starts_with($phone, '+') && !empty($phoneCode)) {
            $phoneCode = preg_replace('/[^0-9+]/', '', $phoneCode);
            if (!str_starts_with($phoneCode, '+')) {
                $phoneCode = '+' . $phoneCode;
            }
            // Éviter la duplication du code pays
            $codeDigits = ltrim($phoneCode, '+');
            if (!str_starts_with($phone, $codeDigits)) {
                $phone = $phoneCode . $phone;
            } else {
                $phone = '+' . $phone;
            }
        }

        // Supprimer le + pour le hashage (Facebook attend le format sans +)
        return ltrim($phone, '+');
    }

    /**
     * Sépare un nom complet en prénom et nom de famille.
     */
    private function splitName(string $fullName): array
    {
        $fullName = mb_strtolower(trim($fullName), 'UTF-8');
        $parts = preg_split('/\s+/', $fullName, 2);

        return [
            'first' => $parts[0] ?? '',
            'last' => $parts[1] ?? '',
        ];
    }

    /**
     * Envoie l'événement à tous les pixels configurés EN PARALLÈLE via curl_multi.
     * Le temps total est borné par le pixel le plus lent, pas par la somme de tous —
     * indispensable dès qu'il y a plus de 2-3 pixels pour ne pas ralentir la commande.
     *
     * @return array<string, array{success: bool, response?: string, error?: string}>
     */
    private function dispatchToAllPixels(array $eventPayload): array
    {
        $results = [];
        $handles = [];
        $multiHandle = curl_multi_init();

        try {
            foreach ($this->pixelTokens as $pixelId => $token) {
                if (empty($token)) {
                    $results[$pixelId] = ['success' => false, 'error' => 'Token manquant pour ce pixel'];
                    continue;
                }
                $ch = $this->buildCurlHandle($pixelId, $token, $eventPayload);
                curl_multi_add_handle($multiHandle, $ch);
                $handles[$pixelId] = $ch;
            }

            $running = null;
            do {
                $status = curl_multi_exec($multiHandle, $running);
                if ($running > 0) {
                    curl_multi_select($multiHandle, 1.0);
                }
            } while ($running > 0 && $status === CURLM_OK);

            foreach ($handles as $pixelId => $ch) {
                $results[$pixelId] = $this->parseCurlResult($ch, $pixelId);
                curl_multi_remove_handle($multiHandle, $ch);
                curl_close($ch);
            }
        } catch (\Throwable $e) {
            error_log('[FacebookCAPI] Exception dispatch parallèle: ' . $e->getMessage());
            foreach ($handles as $pixelId => $ch) {
                if (!isset($results[$pixelId])) {
                    $results[$pixelId] = ['success' => false, 'error' => $e->getMessage()];
                }
                curl_multi_remove_handle($multiHandle, $ch);
                curl_close($ch);
            }
        } finally {
            curl_multi_close($multiHandle);
        }

        return $results;
    }

    private function buildCurlHandle(string $pixelId, string $token, array $eventPayload)
    {
        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $pixelId . '/events';

        $postData = [
            'data' => json_encode([$eventPayload]),
            'access_token' => $token,
        ];

        // Mode test : ajouter le test_event_code
        if ($this->testMode && $this->testEventCode) {
            $postData['test_event_code'] = $this->testEventCode;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        return $ch;
    }

    /**
     * @return array{success: bool, response?: string, error?: string}
     */
    private function parseCurlResult($ch, string $pixelId): array
    {
        $response = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($error) {
            error_log("[FacebookCAPI] cURL error pour pixel $pixelId: $error");
            return ['success' => false, 'error' => $error];
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            error_log(
                "[FacebookCAPI] Event OK pour pixel $pixelId, events_received="
                . (string)($decoded['events_received'] ?? 0)
            );

            return [
                'success' => true,
                'response' => $decoded,
                'events_received' => $decoded['events_received'] ?? 0,
            ];
        }

        $errorMessage = $decoded['error']['message'] ?? 'HTTP ' . $httpCode;
        error_log("[FacebookCAPI] Erreur API pour pixel $pixelId: $errorMessage");

        return ['success' => false, 'error' => $errorMessage, 'http_code' => $httpCode];
    }

    /**
     * Charge la configuration depuis le fichier .env.
     */
    private function loadConfig(): array
    {
        return FacebookTrackingConfig::getCapiConfig();
    }
}
