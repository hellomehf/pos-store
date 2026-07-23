<?php

namespace App\Console\Commands;

use App\Models\PendingPayment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramPollCommand extends Command
{
    protected $signature = 'telegram:poll';
    protected $description = 'Poll Telegram getUpdates for ABA payment notifications';

    private int $offset = 0;

    private array $allowedSenders = [
        'aba_paymentnoti_bot',
        'PayWayByABA_bot',
        'ABAPAYKH',
    ];

    private array $allowedSenderIds = [
    ];

    public function handle(): int
    {
        $token = env('TELEGRAM_BOT_TOKEN') ?: config('services.telegram.bot_token');
        $allowedChatId = env('TELEGRAM_GROUP_CHAT_ID') ?: config('services.telegram.group_chat_id');

        if (!$token) {
            $this->error('TELEGRAM_BOT_TOKEN not set in .env');
            return self::FAILURE;
        }

        if (!$allowedChatId) {
            $this->error('TELEGRAM_GROUP_CHAT_ID not set in .env');
            return self::FAILURE;
        }

        $this->log("=== POLL START | chat_id={$allowedChatId} ===");
        $this->info("Polling Telegram bot for group {$allowedChatId}...");
        $this->info("Press Ctrl+C to stop.");

        $this->clearWebhookIfNeeded($token);

        while (true) {
            try {
                $this->pollOnce($token, (string) $allowedChatId);
            } catch (\Throwable $e) {
                $this->error("Poll error: {$e->getMessage()}");
                $this->log("POLL ERROR: {$e->getMessage()}");
            }

            sleep(3);
        }
    }

    private function clearWebhookIfNeeded(string $token): void
    {
        try {
            $resp = Http::timeout(5)->get("https://api.telegram.org/bot{$token}/getWebhookInfo");
            $info = $resp->json('result', []);
            if (!empty($info['url'])) {
                $this->warn("Webhook detected ({$info['url']}), removing...");
                Http::timeout(5)->get("https://api.telegram.org/bot{$token}/deleteWebhook", ['drop_pending_updates' => true]);
                $this->info("Webhook removed.");
                $this->log("CLEARED webhook: {$info['url']}");
                sleep(2);
            } else {
                $this->log("No webhook registered, proceeding.");
            }
        } catch (\Throwable $e) {
            $this->log("Webhook check failed: {$e->getMessage()}");
        }
    }

    private function pollOnce(string $token, string $allowedChatId): void
    {
        $url = "https://api.telegram.org/bot{$token}/getUpdates";

        $params = [
            'offset' => $this->offset,
            'limit' => 10,
            'timeout' => 5,
        ];

        $response = Http::timeout(10)->get($url, $params);

        if ($response->failed()) {
            $status = $response->status();
            $body = $response->body();
            $this->error("Telegram API error: {$status}");
            $this->log("TG API ERROR: status={$status} body={$body}");

            if ($status === 409) {
                $this->warn("409 Conflict: another process is polling. Retrying in 10s...");
                $this->log("409 CONFLICT: another instance polling, sleeping 10s");
                sleep(10);
            }
            return;
        }

        $data = $response->json();

        if (!$data['ok'] || empty($data['result'])) {
            return;
        }

        $this->log("Got " . count($data['result']) . " update(s)");

        foreach ($data['result'] as $update) {
            $this->offset = $update['update_id'] + 1;

            $message = $update['message'] ?? $update['channel_post'] ?? null;

            if (!$message) {
                $this->log("SKIP: no message object in update_id={$update['update_id']}");
                continue;
            }

            if (!isset($message['text'])) {
                $this->log("SKIP: message has no text");
                continue;
            }

            $chatId = $message['chat']['id'] ?? null;

            if ((string) $chatId !== $allowedChatId) {
                $this->log("SKIP: chat_id mismatch (got={$chatId}, expected={$allowedChatId})");
                continue;
            }

            $senderName = trim(
                ($message['from']['first_name'] ?? $message['sender_chat']['title'] ?? '')
                . ' '
                . ($message['from']['last_name'] ?? '')
            );
            $senderUsername = $message['from']['username'] ?? null;
            $senderId = $message['from']['id'] ?? null;
            $isForwarded = isset($message['forward_from']) || isset($message['forward_from_chat']);
            $forwardFromChat = $message['forward_from_chat']['username'] ?? null;

            $text = trim($message['text']);

            $isAllowedSender = false;
            if ($senderUsername && in_array(strtolower($senderUsername), array_map('strtolower', $this->allowedSenders))) {
                $isAllowedSender = true;
            }
            if ($forwardFromChat && in_array(strtolower($forwardFromChat), array_map('strtolower', $this->allowedSenders))) {
                $isAllowedSender = true;
            }
            if ($senderId && in_array((string) $senderId, array_map('strval', $this->allowedSenderIds))) {
                $isAllowedSender = true;
            }

            if (!$isAllowedSender) {
                $displayName = $senderUsername ? "@{$senderUsername}" : $senderName;
                Log::info("Skipped message: Not sent by ABA Bot", [
                    'from' => $message['from'] ?? null,
                    'forward_from_chat' => $message['forward_from_chat'] ?? null,
                    'text' => mb_substr($text, 0, 100),
                ]);
                $this->log("SKIP: sender not ABA bot. from=[{$displayName}] id={$senderId} text=" . mb_substr($text, 0, 80));
                $this->info("Skipped: Message sent by normal user {$senderName}");
                continue;
            }

            $parsed = $this->parseAbapayAlert($text);

            if (!$parsed) {
                Log::info("Skipped message: Not an ABA payment alert", [
                    'from' => $message['from'] ?? null,
                    'text' => mb_substr($text, 0, 200),
                    'chat_id' => $chatId,
                ]);
                $this->log("SKIP: not ABA alert. sender=[{$senderName}] text=[{$text}]");
                $this->line("  <comment>[{$senderName}]: {$text}</comment>");
                $this->line("  <comment>  ^ not an ABA payment alert, skipping.</comment>");
                continue;
            }

            $this->line("<info>ABA PAY:</info> {$parsed['currency']} {$parsed['amount']}" . ($parsed['reference'] ? " (Ref: {$parsed['reference']})" : '') . " from [{$senderName}]");
            $this->log("PARSED: currency={$parsed['currency']} amount={$parsed['amount']} ref=" . ($parsed['reference'] ?? 'none'));

            $match = $this->findMatchingPending($parsed['amount'], $parsed['currency']);

            if (!$match) {
                $this->line("  <comment>No matching pending payment found.</comment>");
                $this->log("NO MATCH for amount={$parsed['amount']} currency={$parsed['currency']}");
                continue;
            }

            $match->update([
                'status' => 'completed',
                'payment_reference' => $parsed['reference'] ?? null,
                'sender_name' => $senderName ?: null,
                'telegram_message_raw' => json_encode($update),
            ]);

            $this->line("  <info>MATCHED! PendingPayment #{$match->id} (session: {$match->session_id}) -> COMPLETED</info>");
            $this->log("SETTLED: pending_id={$match->id} session={$match->session_id}");
        }
    }

    private function parseAbapayAlert(string $text): ?array
    {
        $currency = null;
        $amount = null;
        $reference = null;

        if (preg_match('/Trx\.?\s*ID\s*[:#]?\s*(\d+)/i', $text, $m)) {
            $reference = $m[1];
        }

        $pattern = '/(?:(?<currency>\$|៛)\s*(?<amount>[0-9,]+(?:\.[0-9]+)?)|(?<amount>[0-9,]+(?:\.[0-9]+)?)\s*(?<currency>\$|៛))\s+paid\s+by/ui';

        if (preg_match($pattern, $text, $m)) {
            $rawAmount = $m['amount'];
            $symbol = $m['currency'];

            $numericAmount = (float) str_replace(',', '', $rawAmount);

            if ($symbol === '៛') {
                $currency = 'KHR';
                $amount = $numericAmount;
            } else {
                $currency = 'USD';
                $amount = $numericAmount;
            }
        }

        if ($amount === null || $amount === 0.0) {
            $hasKhrSymbol = preg_match('/\x{17DB}/u', $text);
            $hasUsdSymbol = (strpos($text, '$') !== false);

            if ($hasKhrSymbol) {
                $currency = 'KHR';
                if (preg_match('/\x{17DB}\s*([\d,]+)/u', $text, $m)) {
                    $amount = (float) str_replace(',', '', $m[1]);
                }
            } elseif ($hasUsdSymbol) {
                $currency = 'USD';
                if (preg_match('/\$([\d,]+(?:\.\d{1,2})?)/', $text, $m)) {
                    $amount = (float) str_replace(',', '', $m[1]);
                }
            }
        }

        if ($amount === null || $amount === 0.0) {
            if (preg_match('/([\d,]+(?:\.\d{1,2})?)/', $text, $m)) {
                $amount = (float) str_replace(',', '', $m[1]);
                $currency = $currency ?: 'USD';
            }
        }

        if ($amount === null || $amount === 0.0) {
            return null;
        }

        Log::info('[TG_PARSER] Text parsed successfully', [
            'currency' => $currency,
            'amount'   => $amount,
            'reference'=> $reference,
            'raw_text' => $text,
        ]);

        return [
            'amount'    => $amount,
            'currency'  => $currency,
            'reference' => $reference,
        ];
    }

    private function findMatchingPending(float $amount, string $currency): ?PendingPayment
    {
        $usdTolerance = 0.02;
        $khrTolerance = 50;

        $query = PendingPayment::where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });

        if ($currency === 'KHR') {
            $query->where(function ($q) use ($amount, $khrTolerance) {
                $q->whereBetween('expected_amount_khr', [$amount - $khrTolerance, $amount + $khrTolerance]);
                $approxUsd = round($amount / 4100, 2);
                $q->orWhereBetween('expected_amount', [$approxUsd - 0.02, $approxUsd + 0.02]);
            });
        } else {
            $query->where(function ($q) use ($amount, $usdTolerance) {
                $q->whereBetween('expected_amount', [$amount - $usdTolerance, $amount + $usdTolerance]);
                $approxKhr = $amount * 4100;
                $q->orWhereBetween('expected_amount_khr', [$approxKhr - 50, $approxKhr + 50]);
            });
        }

        $match = $query->orderBy('created_at', 'desc')->first();

        if ($match) {
            $this->log("MATCH found #{$match->id}: session={$match->session_id} expected_usd={$match->expected_amount} expected_khr={$match->expected_amount_khr}");
            return $match;
        }

        $fallback = PendingPayment::where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderBy('created_at', 'desc')
            ->first();

        if ($fallback) {
            $this->log("MATCH FALLBACK #{$fallback->id}: session={$fallback->session_id} expected_usd={$fallback->expected_amount}");
        }

        return $fallback;
    }

    private function log(string $msg): void
    {
        $line = '[' . now()->toDateTimeString() . '] [TG_POLL] ' . $msg . PHP_EOL;
        file_put_contents(
            storage_path('logs/telegram_poll.log'),
            $line,
            FILE_APPEND | LOCK_EX
        );
    }
}
