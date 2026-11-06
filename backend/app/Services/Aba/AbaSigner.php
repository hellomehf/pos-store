<?php

namespace App\Services\Aba;

/**
 * Pure signing functions for ABA PayWay Checkout 2.0.
 *
 * All signing is server-side. The API key MUST NOT be sent to the browser.
 * The PAYWAY_API_KEY is read exclusively from env through AbaConfig::get().
 */
class AbaSigner
{
    /**
     * Exact field order mandated by PayWay for the Purchase hash.
     * Fields not included in the request contribute an empty string.
     */
    public const PURCHASE_HASH_ORDER = [
        'req_time',
        'merchant_id',
        'tran_id',
        'amount',
        'items',
        'shipping',
        'firstname',
        'lastname',
        'email',
        'phone',
        'type',
        'payment_option',
        'return_url',
        'cancel_url',
        'continue_success_url',
        'return_deeplink',
        'currency',
        'custom_fields',
        'return_params',
        'payout',
        'lifetime',
        'additional_params',
        'google_pay_token',
        'skip_success_page',
    ];

    /**
     * Exact field order mandated by PayWay for the QR API (generate-qr) hash.
     * Fields not included in the request contribute an empty string.
     */
    public const QR_HASH_ORDER = [
        'req_time',
        'merchant_id',
        'tran_id',
        'amount',
        'items',
        'first_name',
        'last_name',
        'email',
        'phone',
        'purchase_type',
        'payment_option',
        'callback_url',
        'return_deeplink',
        'currency',
        'custom_fields',
        'return_params',
        'payout',
        'lifetime',
        'qr_image_template',
    ];

    /**
     * Compute the HMAC-SHA512 QR API hash.
     *
     * @param  array<string,string|int>  $fields  Fields sent to generate-qr (not including 'hash').
     * @param  string                    $apiKey  The PAYWAY_API_KEY (never expose to client).
     * @return string Base64-encoded HMAC-SHA512.
     */
    public static function qrHash(array $fields, string $apiKey): string
    {
        $raw = implode('', array_map(
            fn (string $key) => (string) ($fields[$key] ?? ''),
            self::QR_HASH_ORDER
        ));

        return base64_encode(hash_hmac('sha512', $raw, $apiKey, true));
    }

    /**
     * Compute the HMAC-SHA512 purchase hash.
     *
     * @param  array<string,string>  $fields  The fields being posted (not including 'hash').
     * @param  string                $apiKey  The PAYWAY_API_KEY (never expose to client).
     * @return string Base64-encoded HMAC-SHA512.
     */
    public static function purchaseHash(array $fields, string $apiKey): string
    {
        $raw = implode('', array_map(
            fn (string $key) => (string) ($fields[$key] ?? ''),
            self::PURCHASE_HASH_ORDER
        ));

        return base64_encode(hash_hmac('sha512', $raw, $apiKey, true));
    }

    /**
     * Compute the HMAC-SHA512 check-transaction hash.
     * Covers only: req_time + merchant_id + tran_id.
     *
     * @param  string  $reqTime
     * @param  string  $merchantId
     * @param  string  $tranId
     * @param  string  $apiKey
     * @return string Base64-encoded HMAC-SHA512.
     */
    public static function checkTransactionHash(
        string $reqTime,
        string $merchantId,
        string $tranId,
        string $apiKey
    ): string {
        $raw = $reqTime . $merchantId . $tranId;

        return base64_encode(hash_hmac('sha512', $raw, $apiKey, true));
    }

    /**
     * Verify the X-PAYWAY-HMAC-SHA512 header sent by PayWay on callbacks.
     * Uses hash_equals to prevent timing attacks.
     *
     * @param  string  $payload  Raw request body bytes.
     * @param  string  $header   Value of X-PAYWAY-HMAC-SHA512 header.
     * @param  string  $apiKey   The PAYWAY_API_KEY.
     */
    public static function verifyCallbackHmac(string $payload, string $header, string $apiKey): bool
    {
        $expected = base64_encode(hash_hmac('sha512', $payload, $apiKey, true));

        return hash_equals($expected, $header);
    }

    /**
     * Generate a req_time string in UTC, format YYYYMMDDHHmmss.
     */
    public static function reqTime(): string
    {
        return gmdate('YmdHis');
    }
}
