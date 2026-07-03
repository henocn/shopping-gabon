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
        $eventTime = time();

        // Construire les user_data hashées
        $userData = $this->buildUserData($clientData, $browserData);

        // Construire les custom_data
        $customData = [
            'currency' => $currency,
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

        // Construire le payload
        $eventPayload = [
            'event_name' => 'Purchase',
            'event_time' => $eventTime,
            'event_id' => $eventId,
            'event_source_url' => $browserData['source_url'] ?? '',
            'action_source' => 'website',
            'user_data' => $userData,
            'custom_data' => $customData,
        ];

        // Supprimer les clés vides
        if (empty($eventPayload['event_source_url'])) {
            unset($eventPayload['event_source_url']);
        }

        // Envoyer à chaque pixel avec son token spécifique
        $results = [];
        foreach ($this->pixelTokens as $pixelId => $token) {
            $results[$pixelId] = $this->sendEvent($pixelId, $token, $eventPayload);
        }

        return $results;
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
     * Envoie un événement à l'API Graph de Facebook via cURL.
     *
     * @param string $pixelId ID du pixel/dataset
     * @param string $token Token d'accès spécifique au pixel
     * @param array $eventPayload Données de l'événement
     * @return array{success: bool, response?: string, error?: string}
     */
    private function sendEvent(string $pixelId, string $token, array $eventPayload): array
    {
        if (empty($token)) {
            return ['success' => false, 'error' => 'Token manquant pour ce pixel'];
        }

        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $pixelId . '/events';

        $postData = [
            'data' => json_encode([$eventPayload]),
            'access_token' => $token,
        ];

        // Mode test : ajouter le test_event_code
        if ($this->testMode && $this->testEventCode) {
            $postData['test_event_code'] = $this->testEventCode;
        }

        try {
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

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            curl_close($ch);

            if ($error) {
                error_log("[FacebookCAPI] cURL error pour pixel $pixelId: $error");
                return ['success' => false, 'error' => $error];
            }

            $decoded = json_decode($response, true);

            if ($httpCode >= 200 && $httpCode < 300) {
                error_log(
                    "[FacebookCAPI] Purchase OK pour pixel $pixelId, events_received="
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
        } catch (\Throwable $e) {
            error_log("[FacebookCAPI] Exception pour pixel $pixelId: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Charge la configuration depuis le fichier .env.
     */
    private function loadConfig(): array
    {
        $envFile = __DIR__ . '/.env';

        if (!file_exists($envFile)) {
            return [];
        }

        $ini = parse_ini_file($envFile, true);
        if (!$ini) {
            return [];
        }

        $config = [];
        
        if (isset($ini['facebook'])) {
            $config['test_mode'] = filter_var($ini['facebook']['test_mode'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $config['test_event_code'] = $ini['facebook']['test_event_code'] ?? null;
        }

        if (isset($ini['facebook_pixels'])) {
            $config['pixels'] = $ini['facebook_pixels'];
        }

        return $config;
    }
}
