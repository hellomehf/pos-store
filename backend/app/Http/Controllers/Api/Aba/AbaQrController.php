<?php

namespace App\Http\Controllers\Api\Aba;

use App\Http\Controllers\Controller;
use App\Models\PendingPayment;
use App\Services\Aba\AbaQrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * POST /api/aba/create-qr           — generate a real ABA KHQR for a pending payment.
 * GET  /api/aba/qr-status/{session} — poll the PayWay transaction status.
 *
 * Both routes are authenticated. The amount always comes from the persisted
 * pending_payments row, never from the client.
 */
class AbaQrController extends Controller
{
    public function __construct(private readonly AbaQrService $service) {}

    public function create(Request $request): JsonResponse
    {
        $key = 'aba_qr_create:' . $request->user()->id;

        if (RateLimiter::tooManyAttempts($key, 10)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'success' => false,
                'message' => "Too many QR requests. Try again in {$seconds} seconds.",
            ], 429);
        }

        RateLimiter::hit($key, 60);

        $validated = $request->validate([
            'session_id' => 'required|string|max:64',
        ]);

        $pending = PendingPayment::where('session_id', $validated['session_id'])->first();

        if (! $pending) {
            return response()->json([
                'success' => false,
                'message' => 'Pending payment not found.',
            ], 404);
        }

        if ($pending->status === 'pending' && $pending->expires_at && now()->gt($pending->expires_at)) {
            $pending->update(['status' => 'expired']);

            return response()->json([
                'success' => false,
                'message' => 'This payment session has expired. Please start a new payment.',
            ], 422);
        }

        if ($pending->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => "This payment session is {$pending->status}.",
            ], 422);
        }

        try {
            $qr = $this->service->createForPending($pending);
        } catch (\Throwable $e) {
            Log::error('ABA QR: create failed', [
                'session_id' => $pending->session_id,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate ABA QR: ' . $e->getMessage(),
            ], 502);
        }

        return response()->json([
            'success'    => true,
            'tran_id'    => $qr['tran_id'],
            'amount'     => $qr['amount'],
            'currency'   => $qr['currency'],
            'qr_string'  => $qr['qr_string'],
            'qr_image'   => $qr['qr_image'],
            'deeplink'   => $qr['deeplink'],
            'expires_at' => $qr['expires_at'],
        ]);
    }

    public function status(Request $request, string $sessionId): JsonResponse
    {
        $key = 'aba_qr_status:' . $request->user()->id;

        if (RateLimiter::tooManyAttempts($key, 120)) {
            return response()->json([
                'success' => false,
                'status'  => 'PENDING',
                'message' => 'Too many status requests. Please wait.',
            ], 429);
        }

        RateLimiter::hit($key, 60);

        $pending = PendingPayment::where('session_id', $sessionId)->first();

        if (! $pending) {
            return response()->json([
                'success' => false,
                'message' => 'Pending payment not found.',
            ], 404);
        }

        try {
            $result = $this->service->refreshStatus($pending);
        } catch (\Throwable $e) {
            Log::error('ABA QR: status check failed', [
                'session_id' => $sessionId,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'status'  => 'PENDING',
                'message' => 'Could not reach ABA PayWay. Retrying…',
            ], 502);
        }

        $pending->refresh();

        return response()->json([
            'success'         => true,
            'status'          => $result['status'],
            'tran_id'         => $pending->payment_reference,
            'pending_payment' => [
                'session_id'          => $pending->session_id,
                'expected_amount'     => $pending->expected_amount,
                'expected_amount_khr' => $pending->expected_amount_khr,
                'currency'            => $pending->currency,
                'status'              => $pending->status,
                'payment_reference'   => $pending->payment_reference,
                'sender_name'         => $pending->sender_name,
                'expires_at'          => $pending->expires_at,
            ],
            'raw' => $result['raw'],
        ]);
    }
}
