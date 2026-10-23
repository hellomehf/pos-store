<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    public function sendMessage(string $chatId, string $text, string $parseMode = 'HTML'): bool
    {
        $token = config('services.telegram.bot_token');

        if (!$token) {
            Log::warning('TelegramService: bot token not configured.');

            return false;
        }

        try {
            $response = Http::timeout(10)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => $parseMode,
                    'disable_web_page_preview' => true,
                ]);

            if (!$response->successful() || !($response->json('ok') ?? false)) {
                Log::error('TelegramService: send failed', [
                    'chat_id' => $chatId,
                    'response' => $response->json(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('TelegramService: send exception', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function groupChatId(): ?string
    {
        return config('services.telegram.group_chat_id');
    }
}
