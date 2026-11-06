<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Aba\AbaQrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Legacy QR endpoints kept for existing integrations.
 *
 * All signing/HTTP now goes through AbaQrService, which uses the same
 * PAYWAY_* credentials and the QR API hash order as the rest of the app.
 */
class PaymentController extends Controller
{
    public function __construct(private readonly AbaQrService $service) {}

    public function generateQr(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount'         => 'required|numeric|min:0.01',
            'currency'       => 'nullable|string|size:3',
            'invoice_number' => 'nullable|string|max:20',
        ]);

        $amount   = (float) $validated['amount'];
        $currency = strtoupper($validated['currency'] ?? 'USD');
        $tranId   = $validated['invoice_number'] ?? null;

        try {
            $qr = $this->service->createQr($amount, $currency, $tranId);
        } catch (\Throwable $e) {
            return response()->json([
                'success'      => false,
                'message'      => 'Payment gateway error: ' . $e->getMessage(),
                'raw_response' => null,
            ], 422);
        }

        return response()->json([
            'success'      => true,
            'status'       => $qr['raw']['status'] ?? null,
            'qr_data'      => $qr['qr_string'],
            'qr_image'     => $qr['qr_image'],
            'deeplink'     => $qr['deeplink'],
            'tran_id'      => $qr['tran_id'],
            'amount'       => $qr['amount'],
            'currency'     => $qr['currency'],
            'raw_response' => $qr['raw'],
        ]);
    }

    public function checkStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tran_id' => 'required|string|max:20',
        ]);

        try {
            $result = $this->service->checkRaw($validated['tran_id']);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment status lookup failed: ' . $e->getMessage(),
                'tran_id' => $validated['tran_id'],
            ], 502);
        }

        $body = $result['raw'];

        return response()->json([
            'success'             => true,
            'approved'            => $result['status'] === 'PAID',
            'status'              => $result['status'],
            'payment_status'      => $body['data']['payment_status'] ?? $body['payment_status'] ?? null,
            'payment_status_code' => $body['data']['payment_status_code'] ?? $body['payment_status_code'] ?? null,
            'tran_id'             => $validated['tran_id'],
            'raw_response'        => $body,
        ]);
    }
}
