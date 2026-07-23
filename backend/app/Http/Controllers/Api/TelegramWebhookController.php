<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PendingPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        Log::info('LIVE TELEGRAM PAYLOAD:', $request->all());
        Log::info('Telegram Webhook Received:', $request->all());
        Log::info('TG_WEBHOOK_RAW', $request->all());

        $secret = config('services.telegram.bot_secret');
        $headerSecret = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if ($secret && $headerSecret !== $secret) {
            Log::warning('TG_WEBHOOK: invalid secret');
            return response()->json(['ok' => false], 403);
        }

        $body = $request->all();
        $message = $request->input('message') ?? $request->input('channel_post') ?? $request->input('edited_channel_post') ?? $body['edited_message'] ?? null;

        if (!$message || !isset($message['text'])) {
            return response()->json(['ok' => true]);
        }

        $chatId = $message['chat']['id'] ?? null;
        $allowedChatId = config('services.telegram.group_chat_id', '-5369598416');

        Log::info('TG_WEBHOOK_MSG', [
            'chat_id' => $chatId,
            'allowed' => $allowedChatId,
            'text' => $message['text'] ?? '',
        ]);

        if (!$chatId || !$allowedChatId || (string) $chatId !== (string) $allowedChatId) {
            Log::info('TG_WEBHOOK: ignored wrong chat');
            return response()->json(['ok' => true]);
        }

        $text = trim($message['text']);
        $senderName = trim(
            ($message['from']['first_name'] ?? $message['sender_chat']['title'] ?? '')
            . ' '
            . ($message['from']['last_name'] ?? '')
        );

        $parsed = $this->parseAbapayAlert($text);

        if (!$parsed) {
            Log::info('TG_WEBHOOK: no amount parsed', ['text' => $text]);
            return response()->json(['ok' => true]);
        }

        Log::info('TG_WEBHOOK: parsed', $parsed);

        $match = $this->findMatchingPending($parsed['amount'], $parsed['currency']);

        if (!$match) {
            Log::info('TG_WEBHOOK: no match found', $parsed);
            return response()->json(['ok' => true]);
        }

        $match->update([
            'status' => 'completed',
            'payment_reference' => $parsed['reference'] ?? null,
            'sender_name' => $senderName ?: null,
            'telegram_message_raw' => json_encode($body),
        ]);

        Log::info('TG_WEBHOOK: settled', [
            'pending_id' => $match->id,
            'session_id' => $match->session_id,
            'amount' => $parsed['amount'],
            'currency' => $parsed['currency'],
        ]);

        return response()->json(['ok' => true]);
    }

    private function parseAbapayAlert(string $text): ?array
    {
        $currency = null;
        $amount = null;
        $reference = null;

        if (preg_match('/Trx\.?\s*ID\s*[:#]?\s*(\d+)/i', $text, $m)) {
            $reference = $m[1];
        }

        $hasKhrSymbol = preg_match('/\x{17DB}/u', $text);
        $hasUsdSymbol = (strpos($text, '$') !== false);

        if ($hasKhrSymbol) {
            $currency = 'KHR';
        } elseif ($hasUsdSymbol) {
            $currency = 'USD';
        } elseif (preg_match('/\b(USD|KHR)\b/i', $text, $m)) {
            $currency = strtoupper($m[1]);
        }

        if (!$currency) {
            return null;
        }

        if ($currency === 'KHR') {
            if (preg_match('/\x{17DB}\s*([\d,.]+)/u', $text, $m)) {
                $amountStr = $m[1];
                $amount = (float) preg_replace('/[^\d.]/', '', $amountStr);
            }
        } else {
            if (preg_match('/\$([\d,.]+)/', $text, $m)) {
                $amountStr = $m[1];
                $amount = (float) preg_replace('/[^\d.]/', '', $amountStr);
            }
        }

        if (!$amount) {
            if (preg_match('/([\d,.]+)/', $text, $m)) {
                $amountStr = $m[1];
                $amount = (float) preg_replace('/[^\d.]/', '', $amountStr);
            }
        }

        if (!$amount) {
            return null;
        }

        return [
            'amount' => $amount,
            'currency' => $currency,
            'reference' => $reference,
        ];
    }

    private function findMatchingPending(float $amount, string $currency): ?PendingPayment
    {
        $defaultRate = 4100;
        $usdTolerance = 0.02;
        $khrTolerance = 50;

        $fiveMinAgo = now()->subMinutes(5);

        $query = PendingPayment::where('status', 'pending')
            ->where('created_at', '>=', $fiveMinAgo)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });

        if ($currency === 'KHR') {
            $query->where(function ($q) use ($amount, $khrTolerance, $defaultRate, $usdTolerance) {
                $q->whereBetween('expected_amount_khr', [$amount - $khrTolerance, $amount + $khrTolerance]);
                $approxUsd = $amount / $defaultRate;
                $q->orWhereBetween('expected_amount', [$approxUsd - $usdTolerance, $approxUsd + $usdTolerance]);
            });
        } else {
            $query->where(function ($q) use ($amount, $usdTolerance, $defaultRate, $khrTolerance) {
                $q->whereBetween('expected_amount', [$amount - $usdTolerance, $amount + $usdTolerance]);
                $approxKhr = $amount * $defaultRate;
                $q->orWhereBetween('expected_amount_khr', [$approxKhr - $khrTolerance, $approxKhr + $khrTolerance]);
            });
        }

        return $query->orderBy('created_at', 'desc')->first();
    }
}
