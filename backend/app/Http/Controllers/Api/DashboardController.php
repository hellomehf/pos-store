<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $today = Carbon::today();

        $todaySales = Sale::whereDate('created_at', $today)->sum('total');
        $todayTransactions = Sale::whereDate('created_at', $today)->count();
        $totalProducts = Product::count();
        $lowStockProducts = Product::where('stock_qty', '<=', 10)->count();

        $recentTransactions = Sale::with('user')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($sale) => [
                'id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'cashier' => $sale->user->name,
                'total' => $sale->total,
                'payment_method' => $sale->payment_method,
                'created_at' => $sale->created_at,
            ]);

        $salesChart = Sale::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(total) as total'),
            DB::raw('COUNT(*) as count'),
        )
            ->whereDate('created_at', '>=', Carbon::now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'today_sales' => (float) $todaySales,
            'today_transactions' => $todayTransactions,
            'total_products' => $totalProducts,
            'low_stock_products' => $lowStockProducts,
            'recent_transactions' => $recentTransactions,
            'sales_chart' => $salesChart,
        ]);
    }
}
