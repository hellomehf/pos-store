<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PendingPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PendingPaymentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'expected_amount' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'exchange_rate' => 'nullable|numeric|min:0',
        ]);

        $amount = (float) $validated['expected_amount'];
        $currency = $validated['currency'];
        $rate = (float) ($validated['exchange_rate'] ?? 4100);

        $amountKhr = $currency === 'KHR' ? $amount : round($amount * $rate);

        $pending = PendingPayment::create([
            'session_id' => Str::random(32),
            'expected_amount' => $amount,
            'expected_amount_khr' => $amountKhr,
            'currency' => $currency,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(15),
        ]);

        return response()->json([
            'success' => true,
            'pending_payment' => [
                'id' => $pending->id,
                'session_id' => $pending->session_id,
                'expected_amount' => $pending->expected_amount,
                'expected_amount_khr' => $pending->expected_amount_khr,
                'currency' => $pending->currency,
                'status' => $pending->status,
                'expires_at' => $pending->expires_at,
            ],
        ], 201);
    }

    public function show(string $sessionId): JsonResponse
    {
        $pending = PendingPayment::where('session_id', $sessionId)->first();

        if (!$pending) {
            return response()->json([
                'success' => false,
                'message' => 'Pending payment not found.',
            ], 404);
        }

        if ($pending->status === 'pending' && $pending->expires_at && now()->gt($pending->expires_at)) {
            $pending->update(['status' => 'expired']);
        }

        return response()->json([
            'success' => true,
            'pending_payment' => [
                'id' => $pending->id,
                'session_id' => $pending->session_id,
                'expected_amount' => $pending->expected_amount,
                'expected_amount_khr' => $pending->expected_amount_khr,
                'currency' => $pending->currency,
                'status' => $pending->status,
                'payment_reference' => $pending->payment_reference,
                'sender_name' => $pending->sender_name,
                'expires_at' => $pending->expires_at,
            ],
        ]);
    }

    public function confirm(string $sessionId): JsonResponse
    {
        $pending = PendingPayment::where('session_id', $sessionId)->first();

        if (!$pending) {
            return response()->json([
                'success' => false,
                'message' => 'Pending payment not found.',
            ], 404);
        }

        if ($pending->status === 'completed') {
            return response()->json([
                'success' => true,
                'message' => 'Payment was already confirmed.',
                'pending_payment' => [
                    'id' => $pending->id,
                    'session_id' => $pending->session_id,
                    'status' => $pending->status,
                    'payment_reference' => $pending->payment_reference,
                ],
            ]);
        }

        if ($pending->status === 'expired') {
            return response()->json([
                'success' => false,
                'message' => 'Payment has expired and cannot be confirmed.',
            ], 422);
        }

        $pending->update([
            'status' => 'completed',
            'sender_name' => 'Manual Confirmation',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment manually confirmed',
            'pending_payment' => [
                'id' => $pending->id,
                'session_id' => $pending->session_id,
                'status' => $pending->status,
                'expected_amount' => $pending->expected_amount,
            ],
        ]);
    }
}
