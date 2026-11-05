<?php

namespace App\Http\Controllers\Api\Aba;

use App\Http\Controllers\Controller;
use App\Services\Aba\AbaConfig;
use App\Services\Aba\AbaSigner;
use App\Services\Aba\AbaPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/aba/callback  (return_url webhook)
 *
 * Security model:
 *   1. Verify X-PAYWAY-HMAC-SHA512 header first (reject if invalid).
 *   2. Re-confirm via Check Transaction (never trust payload alone).
 *   3. Update payment + sale only if confirmed PAID.
 *
 * This route is intentionally outside auth:sanctum.
 * It is whitelisted only for PayWay IP ranges in production.
 */
class AbaCallbackController extends Controller
{
    public function __construct(private readonly AbaPaymentService $service) {}

    public function __invoke(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $header  = $request->header('X-PAYWAY-HMAC-SHA512', '');

        // --- Step 1: verify HMAC header -----------------------------------------
        try {
            $cfg    = AbaConfig::get();
            $apiKey = $cfg['api_key'];
        } catch (\Throwable $e) {
            Log::error('ABA Callback: config error', ['error' => $e->getMessage()]);

            return response()->json(['status' => 1, 'message' => 'Config error'], 500);
        }

        if (!AbaSigner::verifyCallbackHmac($rawBody, $header, $apiKey)) {
            Log::warning('ABA Callback: HMAC verification failed', [
                'ip'     => $request->ip(),
                'header' => substr($header, 0, 20) . '…',
            ]);

            return response()->json(['status' => 1, 'message' => 'Invalid signature'], 403);
        }

        // --- Step 2: parse tran_id from body ------------------------------------
        $payload = json_decode($rawBody, true) ?? [];
        $tranId  = $payload['tran_id'] ?? null;

        if (!$tranId) {
            // Try form-encoded fallback
            $tranId = $request->input('tran_id');
        }

        if (!$tranId) {
            Log::warning('ABA Callback: missing tran_id', ['payload' => $payload]);

            return response()->json(['status' => 1, 'message' => 'Missing tran_id'], 400);
        }

        Log::info('ABA Callback: received', [
            'tran_id' => $tranId,
            'payload' => $payload,
        ]);

        // --- Step 3: re-confirm via Check Transaction ---------------------------
        try {
            $result = $this->service->checkTransaction($tranId);
        } catch (\Throwable $e) {
            Log::error('ABA Callback: check-transaction failed', [
                'tran_id' => $tranId,
                'error'   => $e->getMessage(),
            ]);

            // Return 200 so PayWay doesn't retry endlessly; we'll reconcile later.
            return response()->json(['status' => 0, 'message' => 'Acknowledged (check failed)']);
        }

        return response()->json([
            'status'  => 0,
            'message' => 'Callback processed',
            'result'  => $result['status'],
        ]);
    }
}
