<?php

namespace App\Http\Controllers\Api\Aba;

use App\Http\Controllers\Controller;
use App\Services\Aba\AbaPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AbaCheckTransactionController extends Controller
{
    public function __construct(private readonly AbaPaymentService $service) {}

    /**
     * POST /api/aba/check-transaction
     *
     * Accepts: { tran_id: string }
     * Returns: { status: "PAID"|"FAILED"|"PENDING", payment: {...}, raw: {...} }
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tran_id' => 'required|string|max:20',
        ]);

        try {
            $result = $this->service->checkTransaction($validated['tran_id']);
        } catch (\Throwable $e) {
            Log::error('ABA PayWay: checkTransaction failed', [
                'tran_id' => $validated['tran_id'],
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'status'  => 'PENDING',
                'message' => 'Check-transaction request failed: ' . $e->getMessage(),
            ], 502);
        }

        $payment = $result['payment'];

        return response()->json([
            'success' => true,
            'status'  => $result['status'],
            'payment' => $payment ? [
                'tran_id'   => $payment->tran_id,
                'order_id'  => $payment->order_id,
                'amount'    => $payment->amount,
                'currency'  => $payment->currency,
                'status'    => $payment->status,
                'updated_at'=> $payment->updated_at,
            ] : null,
            'raw' => $result['raw'],
        ]);
    }
}
