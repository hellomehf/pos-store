<?php

namespace App\Http\Controllers;

use App\Models\PendingPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AbaWebhookController extends Controller
{
    public function handleWebhook(Request $request): JsonResponse
    {
        $tranId = $request->input('tran_id');
        $status = (string) $request->input('status');

        Log::info('ABA Webhook received', [
            'tran_id' => $tranId,
            'status'  => $status,
            'payload' => $request->all(),
        ]);

        if ($status === '0' || $status === '00') {
            $amount = $request->input('amount') ?? $request->input('total_amount');
            if ($amount !== null) {
                $amount = (float) str_replace(',', '', $amount);
            }
            $payer = $request->input('payer_name') ?? $request->input('payer') ?? null;

            $pending = null;

            if ($tranId) {
                $pending = PendingPayment::where('payment_reference', $tranId)
                    ->where('status', 'pending')
                    ->first();
            }

            if (!$pending && $amount !== null) {
                $pending = PendingPayment::where('status', 'pending')
                    ->where(function ($q) use ($amount) {
                        $q->whereBetween('expected_amount', [$amount - 0.02, $amount + 0.02])
                          ->orWhereBetween('expected_amount_khr', [($amount * 4100) - 50, ($amount * 4100) + 50]);
                    })
                    ->orderByDesc('created_at')
                    ->first();
            }

            if (!$pending) {
                Log::warning('ABA Webhook: no matching pending payment', [
                    'tran_id' => $tranId,
                    'amount'  => $amount,
                ]);
                return response()->json(['status' => 1, 'message' => 'No matching pending payment found']);
            }

            $pending->update([
                'status'              => 'completed',
                'payment_reference'   => $tranId ?? $pending->payment_reference,
                'sender_name'         => $payer ?? 'ABA Webhook',
                'telegram_message_raw'=> json_encode($request->all()),
            ]);

            Log::info('ABA Webhook: payment matched and completed', [
                'pending_id' => $pending->id,
                'session_id' => $pending->session_id,
                'tran_id'    => $tranId,
                'amount'     => $amount,
            ]);

            $this->sendTelegramNotification($amount, $tranId, $payer);

            return response()->json(['status' => 0, 'message' => 'Success']);
        } else {
            Log::warning("ABA Webhook non-success status: {$status} for transaction {$tranId}");
            return response()->json(['status' => 1, 'message' => 'Payment not successful']);
        }
    }

    private function sendTelegramNotification(?float $amount, ?string $tranId, ?string $payer): void
    {
        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.group_chat_id');

        if (!$token || !$chatId) {
            Log::warning('ABA Webhook: Telegram config missing');
            return;
        }

        $amountStr = $amount !== null ? number_format($amount, 2) : 'N/A';
        $payerStr  = $payer ?? 'N/A';
        $refStr    = $tranId ?? 'N/A';

        $text = "✅ *Payment Received!*\n\n"
              . "*Amount:* USD {$amountStr}\n"
              . "*Ref ID:* {$refStr}\n"
              . "*Status:* Completed";

        try {
            $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id'     => $chatId,
                'text'        => $text,
                'parse_mode'  => 'Markdown',
            ]);

            if (!$response->successful()) {
                Log::error('ABA Webhook: Telegram send failed', ['response' => $response->body()]);
            }
        } catch (\Exception $e) {
            Log::error('ABA Webhook: Telegram send exception', ['error' => $e->getMessage()]);
        }
    }
}
