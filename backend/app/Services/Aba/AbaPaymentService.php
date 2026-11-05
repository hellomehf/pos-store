<?php

namespace App\Services\Aba;

use App\Models\AbaPayment;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * ABA PayWay Payments Service.
 *
 * createPayment($order): builds signed fields from a POS Sale record,
 *   generates a unique tran_id, persists a PENDING aba_payments row,
 *   then returns the fields ready for the front-end form.
 *
 * checkTransaction($tranId): calls PayWay check-transaction-2,
 *   maps the result to PAID / FAILED / PENDING,
 *   and updates aba_payments + sales idempotently.
 */
class AbaPaymentService
{
    private string $merchantId;
    private string $apiKey;
    private string $purchaseUrl;
    private string $checkUrl;

    public function __construct()
    {
        $cfg = AbaConfig::get();
        $this->merchantId  = $cfg['merchant_id'];
        $this->apiKey      = $cfg['api_key'];
        $this->purchaseUrl = $cfg['purchase_url'];
        $this->checkUrl    = $cfg['check_url'];
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Build a signed payment request for a given sale.
     *
     * Amount is always read from the DB record — the client cannot influence it.
     *
     * @param  Sale  $sale
     * @return array<string,mixed>  Fields to inject into the PayWay form.
     * @throws RuntimeException
     */
    public function createPayment(Sale $sale): array
    {
        $tranId   = $this->generateTranId($sale->id);
        $reqTime  = AbaSigner::reqTime();
        $amount   = number_format((float) $sale->total, 2, '.', '');

        // Build the customer name from the sale's cashier (best available info).
        $cashier    = $sale->user;
        $nameParts  = explode(' ', $cashier?->name ?? 'POS Customer', 2);
        $firstname  = $nameParts[0];
        $lastname   = $nameParts[1] ?? '';

        // Items string: PayWay expects JSON array of {name, quantity, price}.
        $itemsJson = $this->buildItemsJson($sale);

        $returnUrl          = config('services.aba_payway.return_url', '');
        $cancelUrl          = config('services.aba_payway.cancel_url', '');
        $continueSuccessUrl = config('services.aba_payway.continue_success_url', '');

        $fields = array_filter([
            'req_time'             => $reqTime,
            'merchant_id'          => $this->merchantId,
            'tran_id'              => $tranId,
            'amount'               => $amount,
            'items'                => $itemsJson,
            'firstname'            => $firstname,
            'lastname'             => $lastname,
            'phone'                => '',
            'email'                => '',
            'payment_option'       => '',       // empty = show all enabled methods
            'currency'             => 'USD',
            'return_url'           => $returnUrl,
            'cancel_url'           => $cancelUrl,
            'continue_success_url' => $continueSuccessUrl,
        ], fn ($v) => $v !== '' && $v !== null);

        // Always include fields that the signed string needs even if empty.
        $fields['req_time']   = $reqTime;
        $fields['merchant_id'] = $this->merchantId;
        $fields['tran_id']    = $tranId;
        $fields['amount']     = $amount;
        $fields['currency']   = 'USD';

        $fields['hash'] = AbaSigner::purchaseHash($fields, $this->apiKey);

        // payment_gate: 0 is required for Checkout 2.0 but is NOT part of the hash.
        $fields['payment_gate'] = 0;

        // Persist before returning (fail fast if DB write fails).
        DB::transaction(function () use ($sale, $tranId, $amount) {
            AbaPayment::create([
                'tran_id'   => $tranId,
                'order_id'  => $sale->id,
                'amount'    => $amount,
                'currency'  => 'USD',
                'status'    => AbaPayment::STATUS_PENDING,
            ]);
        });

        Log::info('ABA PayWay: payment created', [
            'tran_id'  => $tranId,
            'order_id' => $sale->id,
            'amount'   => $amount,
        ]);

        return $fields;
    }

    /**
     * Call PayWay check-transaction-2, map to PAID/FAILED/PENDING,
     * update aba_payments and sales idempotently.
     *
     * @param  string  $tranId
     * @return array{status: string, payment: AbaPayment, raw: array}
     */
    public function checkTransaction(string $tranId): array
    {
        $reqTime = AbaSigner::reqTime();
        $hash    = AbaSigner::checkTransactionHash($reqTime, $this->merchantId, $tranId, $this->apiKey);

        $response = Http::timeout(15)->post($this->checkUrl, [
            'req_time'    => $reqTime,
            'merchant_id' => $this->merchantId,
            'tran_id'     => $tranId,
            'hash'        => $hash,
        ]);

        $body   = $response->json() ?? [];
        $status = $this->mapStatus($body);

        Log::info('ABA PayWay: check-transaction result', [
            'tran_id' => $tranId,
            'status'  => $status,
            'http'    => $response->status(),
        ]);

        /** @var AbaPayment|null $payment */
        $payment = AbaPayment::where('tran_id', $tranId)->first();

        if ($payment) {
            $this->applyStatusUpdate($payment, $status);
        }

        return [
            'status'  => $status,
            'payment' => $payment,
            'raw'     => $body,
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Generate a unique tran_id.
     * Format: P{order_id}{timestamp_ms_last6}
     * Max 20 chars, unique index on aba_payments ensures DB-level uniqueness.
     */
    public function generateTranId(int $orderId): string
    {
        // 'P' + up-to-7-digit orderId + 12-digit microsecond suffix = ≤ 20 chars
        $ms     = (int) round(microtime(true) * 1000);
        $suffix = substr((string) $ms, -10); // last 10 digits
        $id     = 'P' . $orderId . $suffix;

        // Safety: truncate to 20 chars
        return substr($id, 0, 20);
    }

    private function buildItemsJson(Sale $sale): string
    {
        $items = $sale->items->map(fn ($item) => [
            'name'     => $item->product_name ?? "Item #{$item->product_id}",
            'quantity' => (string) ($item->quantity ?? 1),
            'price'    => number_format((float) ($item->line_total ?? 0), 2, '.', ''),
        ])->values()->all();

        if (empty($items)) {
            $items = [['name' => 'Sale', 'quantity' => '1', 'price' => number_format((float) $sale->total, 2, '.', '')]];
        }

        return json_encode($items, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Map PayWay check-transaction-2 response to one of PAID / FAILED / PENDING.
     */
    public function mapStatus(array $body): string
    {
        // PayWay check-transaction-2 returns status.code 0 = success.
        $code = $body['status']['code'] ?? $body['status_code'] ?? null;

        if ($code !== null && (int) $code !== 0) {
            // Non-zero top-level status = API-level error (not payment status)
            return AbaPayment::STATUS_PENDING;
        }

        // Payment-level status
        $paymentStatus = strtoupper((string) (
            $body['data']['payment_status']
            ?? $body['payment_status']
            ?? ''
        ));
        $paymentCode = $body['data']['payment_status_code']
            ?? $body['payment_status_code']
            ?? null;

        if ($paymentStatus === 'APPROVED' || (is_numeric($paymentCode) && (int) $paymentCode === 0)) {
            return AbaPayment::STATUS_PAID;
        }

        if (in_array($paymentStatus, ['DECLINED', 'FAILED', 'CANCELLED', 'REJECTED'], true)) {
            return AbaPayment::STATUS_FAILED;
        }

        return AbaPayment::STATUS_PENDING;
    }

    /**
     * Update payment row + related sale idempotently.
     * A second "paid" check will not double-update stock or totals.
     */
    private function applyStatusUpdate(AbaPayment $payment, string $status): void
    {
        if ($payment->status === $status) {
            return; // idempotent — no-op
        }

        // If already PAID, never downgrade to FAILED or PENDING.
        if ($payment->status === AbaPayment::STATUS_PAID) {
            return;
        }

        DB::transaction(function () use ($payment, $status) {
            $payment->update(['status' => $status]);

            if ($status === AbaPayment::STATUS_PAID) {
                Sale::where('id', $payment->order_id)
                    ->where('payment_method', '!=', 'aba') // avoid double-write
                    ->update(['payment_method' => 'aba']);

                Log::info('ABA PayWay: sale marked paid via PayWay', [
                    'tran_id'  => $payment->tran_id,
                    'order_id' => $payment->order_id,
                ]);
            }
        });
    }
}
