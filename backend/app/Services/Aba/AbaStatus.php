<?php

namespace App\Services\Aba;

use App\Models\AbaPayment;

/**
 * Maps PayWay transaction responses to the POS payment statuses.
 *
 * Shared by the hosted Checkout flow and the dynamic QR (KHQR) flow.
 * PayWay returns status codes as strings ("0" / "00"), so comparisons are
 * done against string values to avoid PHP's loose-cast surprises.
 */
class AbaStatus
{
    /**
     * @param  array<string,mixed>  $body  Decoded check-transaction-2 response.
     * @return string AbaPayment::STATUS_PAID | STATUS_FAILED | STATUS_PENDING
     */
    public static function map(array $body): string
    {
        $apiCode = $body['status']['code'] ?? $body['status_code'] ?? null;

        if ($apiCode !== null && !in_array((string) $apiCode, ['0', '00'], true)) {
            // API-level error (invalid hash, transaction not found, rate limit, ...).
            // This is not a payment outcome — keep waiting.
            return AbaPayment::STATUS_PENDING;
        }

        $paymentStatus = strtoupper((string) (
            $body['data']['payment_status']
            ?? $body['payment_status']
            ?? ''
        ));

        $paymentCode = $body['data']['payment_status_code']
            ?? $body['payment_status_code']
            ?? null;

        if (in_array($paymentStatus, ['APPROVED', 'PRE-AUTH'], true)) {
            return AbaPayment::STATUS_PAID;
        }

        if (is_numeric($paymentCode) && (int) $paymentCode === 0) {
            return AbaPayment::STATUS_PAID;
        }

        if (in_array($paymentStatus, ['DECLINED', 'FAILED', 'CANCELLED', 'CANCELED', 'REJECTED'], true)) {
            return AbaPayment::STATUS_FAILED;
        }

        // payment_status_code: 3 = DECLINED, 7 = CANCELLED
        if (is_numeric($paymentCode) && in_array((int) $paymentCode, [3, 7], true)) {
            return AbaPayment::STATUS_FAILED;
        }

        return AbaPayment::STATUS_PENDING;
    }
}
