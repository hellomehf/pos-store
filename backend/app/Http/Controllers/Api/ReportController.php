<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const MAX_DATE_RANGE_DAYS = 90;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'required|in:daily,weekly,monthly',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $dateFrom = Carbon::parse($request->date_from)->startOfDay();
        $dateTo = Carbon::parse($request->date_to)->endOfDay();

        if ($dateFrom->diffInDays($dateTo) > self::MAX_DATE_RANGE_DAYS) {
            return response()->json([
                'success' => false,
                'message' => 'Date range cannot exceed ' . self::MAX_DATE_RANGE_DAYS . ' days.',
            ], 422);
        }

        $data = $this->buildReport($dateFrom, $dateTo);

        return response()->json($data);
    }

    public function exportPdf(Request $request): StreamedResponse
    {
        try {
            $request->validate([
                'date_from' => 'required|date',
                'date_to' => 'required|date|after_or_equal:date_from',
            ]);

            $dateFrom = Carbon::parse($request->date_from)->startOfDay();
            $dateTo = Carbon::parse($request->date_to)->endOfDay();

            if ($dateFrom->diffInDays($dateTo) > self::MAX_DATE_RANGE_DAYS) {
                abort(422, 'Date range cannot exceed ' . self::MAX_DATE_RANGE_DAYS . ' days.');
            }

            $data = $this->buildReport($dateFrom, $dateTo);

            $html = view('reports.sales-pdf', [
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'summary' => $data['summary'],
                'salesData' => $data['sales_data'],
                'categoryData' => $data['category_data'],
                'paymentBreakdown' => $data['payment_breakdown'],
            ])->render();

            $pdf = Pdf::loadHtml($html)
                ->setPaper('a4', 'portrait')
                ->setOption('isFontSubsettingEnabled', true);

            $filename = 'sales_report_' . $dateFrom->format('Y-m-d') . '_to_' . $dateTo->format('Y-m-d') . '.pdf';

            return response()->streamDownload(function () use ($pdf) {
                echo $pdf->output();
            }, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Exception $e) {
            Log::error("PDF report export failed: {$e->getMessage()}", ['trace' => $e->getTraceAsString()]);
            abort(500, 'Failed to generate PDF report');
        }
    }

    private function buildReport(Carbon $dateFrom, Carbon $dateTo): array
    {
        $salesData = Sale::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(total) as revenue'),
            DB::raw('COUNT(*) as transactions'),
            DB::raw('SUM(tax_amount) as tax'),
        )
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $categoryData = SaleItem::select(
            'product_name',
            DB::raw('SUM(quantity) as total_qty'),
            DB::raw('SUM(line_total) as total_revenue'),
        )
            ->whereHas('sale', function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('created_at', [$dateFrom, $dateTo]);
            })
            ->groupBy('product_name')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        $paymentBreakdown = Sale::select(
            'payment_method',
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(total) as total'),
        )
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('payment_method')
            ->get();

        $totalRevenue = (float) $salesData->sum('revenue');
        $totalTax = (float) $salesData->sum('tax');
        $totalTransactions = (int) $salesData->sum('transactions');

        $totalExpenses = (float) SaleItem::whereHas('sale', function ($query) use ($dateFrom, $dateTo) {
            $query->whereBetween('created_at', [$dateFrom, $dateTo]);
        })->sum(DB::raw('cost_price * quantity'));

        $summary = [
            'total_revenue' => $totalRevenue,
            'total_expenses' => $totalExpenses,
            'net_profit' => $totalRevenue - $totalExpenses,
            'total_transactions' => $totalTransactions,
            'total_tax' => $totalTax,
            'avg_transaction' => $totalTransactions > 0
                ? round($totalRevenue / $totalTransactions, 2)
                : 0,
        ];

        return [
            'summary' => $summary,
            'sales_data' => $salesData,
            'category_data' => $categoryData,
            'payment_breakdown' => $paymentBreakdown,
        ];
    }
}
