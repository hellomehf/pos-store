<?php

namespace App\Jobs;

use App\Models\Sale;
use App\Services\ReceiptFormatter;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSaleReceipt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $saleId)
    {
    }

    public function handle(TelegramService $telegram, ReceiptFormatter $formatter): void
    {
        $sale = Sale::with(['items', 'user'])->find($this->saleId);

        if (!$sale) {
            return;
        }

        $chatId = $telegram->groupChatId();

        if (!$chatId) {
            \Illuminate\Support\Facades\Log::warning('SendSaleReceipt: no group chat id configured.');

            return;
        }

        $telegram->sendMessage($chatId, $formatter->format($sale));
    }
}
