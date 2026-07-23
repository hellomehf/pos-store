<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DailyReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reports = DailyReport::orderByDesc('report_date')->paginate(30);
        return response()->json($reports);
    }

    public function exportCsv(int $id): StreamedResponse
    {
        try {
            $report = DailyReport::findOrFail($id);

            return response()->streamDownload(function () use ($report) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['Daily Sales Report - ' . $report->report_date->format('M d, Y')]);
                fputcsv($handle, []);
                fputcsv($handle, ['Metric', 'Value']);
                fputcsv($handle, ['Gross Revenue', '$' . number_format($report->gross_revenue, 2)]);
                fputcsv($handle, ['Total Expenses (COGS)', '$' . number_format($report->total_expenses, 2)]);
                fputcsv($handle, ['Net Profit', '$' . number_format($report->net_profit, 2)]);
                fputcsv($handle, ['Tax Collected', '$' . number_format($report->total_tax_collected, 2)]);
                fputcsv($handle, []);
                fputcsv($handle, ['Total Transactions', $report->total_transactions]);
                fputcsv($handle, ['Cash Transactions', $report->cash_count]);
                fputcsv($handle, ['Card Transactions', $report->card_count]);
                fclose($handle);
            }, "daily-report-{$report->report_date->format('Y-m-d')}.csv", ['Content-Type' => 'text/csv']);
        } catch (\Exception $e) {
            Log::error("CSV export failed for report #{$id}: {$e->getMessage()}", ['trace' => $e->getTraceAsString()]);
            abort(500, 'Failed to generate CSV export');
        }
    }

    public function exportPdf(int $id): StreamedResponse
    {
        try {
            $report = DailyReport::findOrFail($id);

            $html = view('reports.daily-pdf', compact('report'))->render();

            $pdf = Pdf::loadHtml($html)
                ->setPaper('a5', 'portrait')
                ->setOption('isFontSubsettingEnabled', true);

            return response()->streamDownload(function () use ($pdf) {
                echo $pdf->output();
            }, "daily-report-{$report->report_date->format('Y-m-d')}.pdf", [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Exception $e) {
            Log::error("PDF export failed for report #{$id}: {$e->getMessage()}", ['trace' => $e->getTraceAsString()]);
            abort(500, 'Failed to generate PDF export');
        }
    }
}
