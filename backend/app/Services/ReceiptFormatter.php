<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Setting;

class ReceiptFormatter
{
    private const WIDTH = 36;

    public function format(Sale $sale): string
    {
        $sale->loadMissing(['user', 'items']);

        $storeName = Setting::getValue('store_name', 'POS Store');
        $storeAddress = Setting::getValue('store_address');
        $storePhone = Setting::getValue('store_phone');

        $lines = [];

        $lines[] = $this->center($storeName);
        if ($storeAddress) {
            $lines[] = $this->center($storeAddress);
        }
        if ($storePhone) {
            $lines[] = $this->center($storePhone);
        }
        $lines[] = $this->rule();
        $lines[] = '';

        $lines[] = 'Invoice  ' . $sale->invoice_number;
        $lines[] = 'Date     ' . ($sale->created_at?->format('Y-m-d H:i:s') ?? '-');
        $lines[] = 'Cashier  ' . ($sale->user?->name ?? '-');
        $lines[] = $this->rule();

        foreach ($sale->items as $item) {
            $name = mb_strimwidth((string) $item->product_name, 0, 17, '…');
            $qty = '×' . (int) $item->quantity;
            $unit = '$' . number_format((float) $item->unit_price, 2);
            $amount = '$' . number_format((float) $item->line_total, 2);

            $lines[] = str_pad($name, 17)
                . ' ' . str_pad($qty, 4, ' ', STR_PAD_LEFT)
                . ' ' . str_pad($unit, 5, ' ', STR_PAD_LEFT)
                . ' ' . str_pad($amount, 7, ' ', STR_PAD_LEFT);
        }

        $lines[] = $this->rule();
        $lines[] = $this->row('Subtotal', '$' . number_format((float) $sale->subtotal, 2));
        $lines[] = $this->row(
            'Tax (' . number_format((float) $sale->tax_rate, 0) . '%)',
            '$' . number_format((float) $sale->tax_amount, 2)
        );
        $lines[] = $this->row('TOTAL', '$' . number_format((float) $sale->total, 2));
        $lines[] = $this->row('TOTAL KHR', number_format((float) $sale->total_khr) . '៛');
        $lines[] = '';

        $lines[] = 'Payment  ' . $this->paymentLabel($sale->payment_method);

        if ($sale->payment_method === 'cash') {
            $lines[] = 'Tendered  $' . number_format((float) $sale->tendered_amount, 2);
            $lines[] = 'Change    $' . number_format((float) $sale->change_amount, 2)
                . ' / ' . number_format((float) $sale->change_khr) . '៛';
        }

        $lines[] = 'Rate     $1.00 = ' . number_format((float) $sale->exchange_rate) . '៛';
        $lines[] = $this->rule();
        $lines[] = '';
        $lines[] = $this->center('Thank you for shopping!');

        $body = implode("\n", $lines);

        return '<b>' . htmlspecialchars($storeName) . '</b> — Sale Receipt'
            . "\n<pre>" . htmlspecialchars($body) . '</pre>';
    }

    private function paymentLabel(string $method): string
    {
        return match ($method) {
            'cash' => '💵 CASH',
            'card' => '💳 CARD',
            'khqr' => '📱 KHQR',
            default => strtoupper($method),
        };
    }

    private function rule(): string
    {
        return str_repeat('─', self::WIDTH);
    }

    private function row(string $label, string $value): string
    {
        return str_pad($label, self::WIDTH - 10) . str_pad($value, 10, ' ', STR_PAD_LEFT);
    }

    private function center(string $text): string
    {
        return str_pad($text, self::WIDTH, ' ', STR_PAD_BOTH);
    }
}
