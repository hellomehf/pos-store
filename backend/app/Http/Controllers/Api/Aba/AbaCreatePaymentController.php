<?php

namespace App\Http\Controllers\Api\Aba;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\Aba\AbaPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class AbaCreatePaymentController extends Controller
{
    public function __construct(private readonly AbaPaymentService $service) {}

    /**
     * POST /api/aba/create-payment
     *
     * Accepts: { order_id: int }
     * Amount comes from the DB — the client cannot influence it.
     *
     * Rate-limited: 10 requests / minute per user.
     */
    public function __invoke(Request $request): JsonResponse
    {
        // Rate limit: 10 per minute per authenticated user.
        $key      = 'aba_create:' . $request->user()->id;
        $maxTries = 10;

        if (RateLimiter::tooManyAttempts($key, $maxTries)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'success' => false,
                'message' => "Too many payment requests. Try again in {$seconds} seconds.",
            ], 429);
        }

        RateLimiter::hit($key, 60);

        $validated = $request->validate([
            'order_id' => 'required|integer|exists:sales,id',
        ]);

        $sale = Sale::with(['user', 'items'])->findOrFail($validated['order_id']);

        try {
            $fields = $this->service->createPayment($sale);
        } catch (\Throwable $e) {
            Log::error('ABA PayWay: createPayment failed', [
                'order_id' => $sale->id,
                'error'    => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'fields'  => $fields,
        ]);
    }
}
