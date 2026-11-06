<?php

namespace App\Services\Aba;

use RuntimeException;

/**
 * Loads and validates ABA PayWay environment config.
 * Call AbaConfig::get() to retrieve values; it throws if a required key is missing.
 */
class AbaConfig
{
    /**
     * PayWay QR image template. This is the single source of truth; do not
     * add per-environment overrides for the template.
     */
    public const QR_IMAGE_TEMPLATE = 'template6_color';

    /** @var array<string,string>|null */
    private static ?array $config = null;

    public static function get(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $merchantId = config('services.aba_payway.merchant_id');
        $apiKey = config('services.aba_payway.api_key');
        $purchaseUrl = config('services.aba_payway.purchase_url');
        $env = config('services.aba_payway.env', 'sandbox');

        if (empty($merchantId)) {
            throw new RuntimeException('ABA PayWay: PAYWAY_MERCHANT_ID is not set.');
        }
        if (empty($apiKey)) {
            throw new RuntimeException('ABA PayWay: PAYWAY_API_KEY is not set.');
        }
        if (empty($purchaseUrl)) {
            throw new RuntimeException('ABA PayWay: PAYWAY_PURCHASE_URL is not set.');
        }

        self::$config = [
            'merchant_id' => $merchantId,
            'api_key' => $apiKey,
            'purchase_url' => $purchaseUrl,
            'qr_url' => preg_replace('/\/purchase$/', '/generate-qr', $purchaseUrl),
            'check_url' => preg_replace('/\/purchase$/', '/check-transaction-2', $purchaseUrl),
            'env' => $env,
            'qr_lifetime' => max(3, (int) config('services.aba_payway.qr_lifetime', 15)),
            'qr_callback_url' => (string) config('services.aba_payway.qr_callback_url', ''),
        ];

        return self::$config;
    }

    /** Reset cached config (useful in tests). */
    public static function reset(): void
    {
        self::$config = null;
    }
}
