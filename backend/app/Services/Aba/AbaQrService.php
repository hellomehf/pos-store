<?php

namespace App\Services\Aba;

use App\Models\AbaPayment;
use App\Models\PendingPayment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * ABA PayWay QR API (dynamic KHQR) service.
 *
 * Flow:
 *   createForPending() -> POST /generate-qr, keeps the real qrString/qrImage
 *                         on the pending_payments row.
 *   refreshStatus()    -> POST /check-transaction-2 and mark the row
 *                         completed once ABA reports APPROVED.
 *
 * Signing uses AbaSigner::qrHash() with the QR API field order. The API key
 * never leaves the server.
 */
class AbaQrService
{
    private string $merchantId;

    private string $apiKey;

    private string $qrUrl;

    private string $checkUrl;

    private string $imageTemplate;

    private int $lifetime;

    private string $callbackUrl;

    public function __construct()
    {
        $cfg = AbaConfig::get();

        $this->merchantId = $cfg['merchant_id'];
        $this->apiKey = $cfg['api_key'];
        $this->qrUrl = $cfg['qr_url'];
        $this->checkUrl = $cfg['check_url'];
        $this->imageTemplate = AbaConfig::QR_IMAGE_TEMPLATE;
        $this->lifetime = max(3, (int) ($cfg['qr_lifetime'] ?? 15));
        $this->callbackUrl = (string) ($cfg['qr_callback_url'] ?? '');
    }

    /**
     * Generate a dynamic KHQR without persisting it.
     *
     * @param  array<int,array{name:string,quantity:int|float|string,price:float|string}>  $items
     * @return array<string,mixed>
     *
     * @throws RuntimeException
     */
    public function createQr(
        float $amount,
        string $currency = 'USD',
        ?string $tranId = null,
        array $items = []
    ): array {
        $currency = strtoupper($currency);
        if (! in_array($currency, ['USD', 'KHR'], true)) {
            throw new RuntimeException("ABA QR: unsupported currency {$currency}.");
        }

        $amount = number_format($amount, 2, '.', '');
        $tranId = $tranId ?: $this->generateTranId();

        $fields = [
            'req_time' => AbaSigner::reqTime(),
            'merchant_id' => $this->merchantId,
            'tran_id' => $tranId,
            'amount' => $amount,
            'currency' => $currency,
            'purchase_type' => 'purchase',
            'payment_option' => 'abapay_khqr',
            'lifetime' => $this->lifetime,
        ];

        if (! empty($items)) {
            $fields['items'] = base64_encode(json_encode($items, JSON_UNESCAPED_UNICODE));
        }
        if ($this->callbackUrl !== '') {
            $fields['callback_url'] = base64_encode($this->callbackUrl);
        }

        // QR API requires qr_image_template and signs it as the final field,
        // after lifetime. AbaSigner::QR_HASH_ORDER enforces that order.
        $fields['qr_image_template'] = $this->imageTemplate;

        $fields['hash'] = AbaSigner::qrHash($fields, $this->apiKey);

        $response = Http::timeout(15)->acceptJson()->post($this->qrUrl, $fields);
        $body = $response->json();

        if (! is_array($body)) {
            throw new RuntimeException(
                'ABA QR: invalid gateway response (HTTP '.$response->status().').'
            );
        }

        $code = (string) ($body['status']['code'] ?? '');
        if (! in_array($code, ['0', '00'], true)) {
            $message = $body['status']['message']
                ?? $body['description']
                ?? 'Unknown error';

            throw new RuntimeException("ABA QR: {$message} (code {$code}).");
        }

        $qrString = $body['qrString'] ?? $body['qr_string'] ?? null;
        $qrImage = $body['qrImage'] ?? $body['qr_image'] ?? null;

        if (! $qrString || ! $qrImage) {
            throw new RuntimeException('ABA QR: gateway did not return a QR code.');
        }

        return [
            'tran_id' => $body['status']['tran_id'] ?? $tranId,
            'amount' => $body['amount'] ?? (float) $amount,
            'currency' => $body['currency'] ?? $currency,
            'qr_string' => $qrString,
            'qr_image' => $qrImage,
            'deeplink' => $body['abapay_deeplink'] ?? null,
            'raw' => $body,
        ];
    }

    /**
     * Generate (or reuse) the real KHQR for a pending POS payment session.
     *
     * @return array<string,mixed>
     *
     * @throws RuntimeException
     */
    public function createForPending(PendingPayment $pending): array
    {
        if ($pending->payment_reference && $pending->qr_string) {
            return $this->payloadFor($pending);
        }

        $qr = $this->createQr(
            (float) $pending->expected_amount,
            $pending->currency ?: 'USD',
            $this->generateTranIdForPending($pending)
        );

        $pending->update([
            'payment_reference' => $qr['tran_id'],
            'qr_string' => $qr['qr_string'],
            'qr_image' => $qr['qr_image'],
            'qr_deeplink' => $qr['deeplink'],
        ]);

        Log::info('ABA QR: dynamic KHQR created', [
            'pending_id' => $pending->id,
            'session_id' => $pending->session_id,
            'tran_id' => $qr['tran_id'],
            'amount' => $pending->expected_amount,
            'currency' => $pending->currency,
        ]);

        return $this->payloadFor($pending->fresh());
    }

    /**
     * Ask PayWay for the current status and update the pending payment.
     *
     * @return array{status:string, raw:array<string,mixed>}
     */
    public function refreshStatus(PendingPayment $pending): array
    {
        if ($pending->status === 'completed') {
            return ['status' => AbaPayment::STATUS_PAID, 'raw' => []];
        }

        if ($pending->status === 'failed') {
            return ['status' => AbaPayment::STATUS_FAILED, 'raw' => []];
        }

        if ($pending->status === 'expired') {
            return ['status' => 'EXPIRED', 'raw' => []];
        }

        $body = [];
        $status = AbaPayment::STATUS_PENDING;

        if ($pending->payment_reference) {
            $body = $this->checkTransaction($pending->payment_reference);
            $status = AbaStatus::map($body);

            if ($status === AbaPayment::STATUS_PAID) {
                $pending->update([
                    'status' => 'completed',
                    'sender_name' => $pending->sender_name ?: 'ABA KHQR',
                ]);

                return ['status' => $status, 'raw' => $body];
            }

            if ($status === AbaPayment::STATUS_FAILED) {
                $pending->update(['status' => 'failed']);

                return ['status' => $status, 'raw' => $body];
            }
        }

        if ($pending->expires_at && now()->gt($pending->expires_at)) {
            $pending->update(['status' => 'expired']);

            return ['status' => 'EXPIRED', 'raw' => $body];
        }

        return ['status' => $status, 'raw' => $body];
    }

    /**
     * Check a standalone QR transaction (kept for the legacy payment routes).
     *
     * @return array{status:string, raw:array<string,mixed>}
     */
    public function checkRaw(string $tranId): array
    {
        $body = $this->checkTransaction($tranId);

        return ['status' => AbaStatus::map($body), 'raw' => $body];
    }

    /**
     * @return array<string,mixed>
     */
    private function checkTransaction(string $tranId): array
    {
        $reqTime = AbaSigner::reqTime();
        $hash = AbaSigner::checkTransactionHash(
            $reqTime,
            $this->merchantId,
            $tranId,
            $this->apiKey
        );

        $response = Http::timeout(15)->acceptJson()->post($this->checkUrl, [
            'req_time' => $reqTime,
            'merchant_id' => $this->merchantId,
            'tran_id' => $tranId,
            'hash' => $hash,
        ]);

        return $response->json() ?? [];
    }

    /**
     * @return array<string,mixed>
     */
    private function payloadFor(PendingPayment $pending): array
    {
        return [
            'tran_id' => $pending->payment_reference,
            'amount' => (float) $pending->expected_amount,
            'currency' => $pending->currency,
            'qr_string' => $pending->qr_string,
            'qr_image' => $pending->qr_image,
            'deeplink' => $pending->qr_deeplink,
            'expires_at' => $pending->expires_at,
            'raw' => [],
        ];
    }

    private function generateTranId(): string
    {
        // K + 12-digit UTC timestamp + 6 random digits = 19 chars (max 20).
        return 'K'.now()->utc()->format('ymdHis').random_int(100000, 999999);
    }

    private function generateTranIdForPending(PendingPayment $pending): string
    {
        $suffix = substr((string) round(microtime(true) * 1000), -10);

        return substr('K'.$pending->id.$suffix, 0, 20);
    }
}
