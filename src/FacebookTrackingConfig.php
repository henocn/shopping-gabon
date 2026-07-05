<?php

namespace src;

class FacebookTrackingConfig
{
    private const DEFAULT_BROWSER_PIXEL_IDS = [
        '2261985127882624',
        '2494711927609592',
        '1388388676529395',
    ];

    public static function getBrowserPixelIds(): array
    {
        $config = self::readEnv();
        $ids = self::DEFAULT_BROWSER_PIXEL_IDS;

        if (!empty($config['facebook'])) {
            foreach (['pixel_id', 'pixel_ids', 'pixels', 'browser_pixel_ids'] as $key) {
                if (!empty($config['facebook'][$key])) {
                    $ids = array_merge($ids, self::splitPixelIds((string) $config['facebook'][$key]));
                }
            }
        }

        if (!empty($config['facebook_pixels']) && is_array($config['facebook_pixels'])) {
            $ids = array_merge($ids, array_keys($config['facebook_pixels']));
        }

        return self::normalizePixelIds($ids);
    }

    public static function getBrowserConfig(): array
    {
        $pixelIds = self::getBrowserPixelIds();

        return [
            'facebook' => [
                'enabled' => !empty($pixelIds),
                'pixels' => $pixelIds,
                'timeout' => 5000,
            ],
        ];
    }

    public static function getCapiConfig(): array
    {
        $config = self::readEnv();
        $pixels = [];

        if (!empty($config['facebook_pixels']) && is_array($config['facebook_pixels'])) {
            foreach ($config['facebook_pixels'] as $pixelId => $token) {
                $pixelId = trim((string) $pixelId);
                $token = trim((string) $token);

                if (preg_match('/^\d+$/', $pixelId) && $token !== '') {
                    $pixels[$pixelId] = $token;
                }
            }
        }

        return [
            'pixels' => $pixels,
            'test_mode' => filter_var($config['facebook']['test_mode'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'test_event_code' => self::emptyToNull($config['facebook']['test_event_code'] ?? null),
        ];
    }

    public static function assetVersion(string $absolutePath): int
    {
        return is_file($absolutePath) ? (int) filemtime($absolutePath) : time();
    }

    private static function readEnv(): array
    {
        $envFile = __DIR__ . '/.env';
        if (!is_file($envFile)) {
            return [];
        }

        $config = parse_ini_file($envFile, true);
        return is_array($config) ? $config : [];
    }

    private static function splitPixelIds(string $value): array
    {
        return preg_split('/[\s,;]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private static function normalizePixelIds(array $pixelIds): array
    {
        $normalized = [];

        foreach ($pixelIds as $pixelId) {
            $pixelId = trim((string) $pixelId);
            if (preg_match('/^\d+$/', $pixelId)) {
                $normalized[] = $pixelId;
            }
        }

        return array_values(array_unique($normalized));
    }

    private static function emptyToNull($value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
