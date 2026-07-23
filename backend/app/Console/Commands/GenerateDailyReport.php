<?php

namespace App\Console\Commands;

use App\Models\DailyReport;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GenerateDailyReport extends Command
{
    protected $signature = 'report:daily {--date=}';
    protected $description = 'Generate end-of-day sales snapshot';

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();

        $date->startOfDay();
        $nextDay = (clone $date)->copy()->addDay();

        if (DailyReport::where('report_date', $date->toDateString())->exists()) {
            $this->info("Report for {$date->toDateString()} already exists. Skipping.");
            return self::SUCCESS;
        }

        $grossRevenue = (float) Sale::whereBetween('created_at', [$date, $nextDay])->sum('total');
        $totalTax = (float) Sale::whereBetween('created_at', [$date, $nextDay])->sum('tax_amount');
        $totalTransactions = (int) Sale::whereBetween('created_at', [$date, $nextDay])->count();

        $totalExpenses = (float) SaleItem::whereHas('sale', function ($q) use ($date, $nextDay) {
            $q->whereBetween('created_at', [$date, $nextDay]);
        })->sum(DB::raw('cost_price * quantity'));

        $paymentCounts = Sale::whereBetween('created_at', [$date, $nextDay])
            ->select('payment_method', DB::raw('count(*) as cnt'))
            ->groupBy('payment_method')
            ->pluck('cnt', 'payment_method');

        $cashCount = (int) ($paymentCounts['cash'] ?? 0);
        $cardCount = (int) ($paymentCounts['card'] ?? 0);

        DailyReport::create([
            'report_date' => $date->toDateString(),
            'gross_revenue' => round($grossRevenue, 2),
            'total_expenses' => round($totalExpenses, 2),
            'net_profit' => round($grossRevenue - $totalExpenses, 2),
            'total_tax_collected' => round($totalTax, 2),
            'total_transactions' => $totalTransactions,
            'cash_count' => $cashCount,
            'card_count' => $cardCount,
        ]);

        $this->info("Daily report generated for {$date->toDateString()}: Revenue=$grossRevenue, Expenses=$totalExpenses, Profit=" . round($grossRevenue - $totalExpenses, 2));
        return self::SUCCESS;
    }
}
