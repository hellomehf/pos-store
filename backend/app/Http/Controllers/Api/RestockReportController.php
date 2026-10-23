<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\RestockLog;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RestockReportController extends Controller
{
    /**
     * GET /api/reports/restock
     * Returns paginated restock log entries with product + user info.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date'       => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'product_id' => 'nullable|exists:products,id',
            'user_id'    => 'nullable|exists:users,id',
        ]);

        $query = RestockLog::with(['product.category', 'user'])
            ->orderByDesc('created_at');

        [$start, $end] = $this->resolveDateRange($request);
        if ($start && $end) {
            $query->whereBetween('created_at', [$start, $end]);
        }

        if ($request->product_id) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        $logs = $query->get();

        $summary = [
            'total_items_restocked' => $logs->count(),
            'total_qty_added'       => $logs->sum('quantity_added'),
            'date_from'             => $start?->toDateString(),
            'date_to'               => $end?->toDateString(),
        ];

        return response()->json([
            'data'    => $logs,
            'summary' => $summary,
        ]);
    }

    /**
     * GET /api/reports/restock/pdf
     * Streams a DomPDF-generated restock report.
     */
    public function exportPdf(Request $request): StreamedResponse
    {
        try {
            $request->validate([
                'date'       => 'nullable|date',
                'start_date' => 'nullable|date',
                'end_date'   => 'nullable|date|after_or_equal:start_date',
                'product_id' => 'nullable|exists:products,id',
                'user_id'    => 'nullable|exists:users,id',
                'type'       => 'nullable|in:restock_history,low_stock_check',
            ]);

            $type = $request->get('type', 'restock_history');

            [$start, $end] = $this->resolveDateRange($request);

            // Resolve store name from settings
            $storeName = Setting::where('key', 'store_name')->value('value') ?? 'POS Store';

            $preparedBy = $request->user();

            if ($type === 'low_stock_check') {
                $products = Product::with('category')
                    ->where('stock_qty', '<=', 10)
                    ->orderBy('stock_qty')
                    ->get();

                $html = view('reports.restock-pdf', [
                    'type'        => 'low_stock_check',
                    'storeName'   => $storeName,
                    'preparedBy'  => $preparedBy,
                    'dateFrom'    => $start ?? now(),
                    'dateTo'      => $end ?? now(),
                    'products'    => $products,
                    'logs'        => collect(),
                    'summary'     => [
                        'total_items_restocked' => 0,
                        'total_qty_added'       => 0,
                    ],
                ])->render();

                $filename = 'low_stock_check_' . now()->format('Y-m-d') . '.pdf';
            } else {
                $query = RestockLog::with(['product.category', 'user'])
                    ->orderBy('created_at');

                if ($start && $end) {
                    $query->whereBetween('created_at', [$start, $end]);
                }

                if ($request->product_id) {
                    $query->where('product_id', $request->product_id);
                }

                if ($request->user_id) {
                    $query->where('user_id', $request->user_id);
                }

                $logs = $query->get();

                $summary = [
                    'total_items_restocked' => $logs->count(),
                    'total_qty_added'       => $logs->sum('quantity_added'),
                ];

                $html = view('reports.restock-pdf', [
                    'type'        => 'restock_history',
                    'storeName'   => $storeName,
                    'preparedBy'  => $preparedBy,
                    'dateFrom'    => $start ?? now()->startOfDay(),
                    'dateTo'      => $end ?? now()->endOfDay(),
                    'products'    => collect(),
                    'logs'        => $logs,
                    'summary'     => $summary,
                ])->render();

                $from = $start ? $start->format('Y-m-d') : now()->format('Y-m-d');
                $to   = $end   ? $end->format('Y-m-d')   : now()->format('Y-m-d');
                $filename = "restock_report_{$from}_to_{$to}.pdf";
            }

            $pdf = Pdf::loadHtml($html)
                ->setPaper('a4', 'portrait')
                ->setOption('isFontSubsettingEnabled', true);

            return response()->streamDownload(function () use ($pdf) {
                echo $pdf->output();
            }, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Exception $e) {
            Log::error("Restock PDF export failed: {$e->getMessage()}", ['trace' => $e->getTraceAsString()]);
            abort(500, 'Failed to generate restock PDF report');
        }
    }

    /**
     * Resolve start/end Carbon instances from request params.
     */
    private function resolveDateRange(Request $request): array
    {
        if ($request->date) {
            $day = Carbon::parse($request->date);
            return [$day->copy()->startOfDay(), $day->copy()->endOfDay()];
        }

        if ($request->start_date && $request->end_date) {
            return [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay(),
            ];
        }

        // Default: today
        return [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()];
    }
}
