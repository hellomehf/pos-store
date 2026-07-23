<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    private const MAX_DATE_RANGE_DAYS = 365;

    public function index(Request $request): JsonResponse
    {
        $query = Sale::with('user');

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('date_from') && $request->date_from && $request->has('date_to') && $request->date_to) {
            $dateFrom = \Illuminate\Support\Carbon::parse($request->date_from);
            $dateTo = \Illuminate\Support\Carbon::parse($request->date_to);
            if ($dateFrom->diffInDays($dateTo) > self::MAX_DATE_RANGE_DAYS) {
                return response()->json([
                    'success' => false,
                    'message' => 'Date range cannot exceed ' . self::MAX_DATE_RANGE_DAYS . ' days.',
                ], 422);
            }
        }

        if ($request->has('user_id') && $request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        $sales = $query->latest()->paginate(20);

        return response()->json($sales);
    }

    public function show(Sale $sale): JsonResponse
    {
        $sale->load(['user', 'items.product']);

        return response()->json($sale);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|in:cash,card,khqr',
            'tendered_amount' => 'required_if:payment_method,cash|nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'exchange_rate' => 'nullable|integer|min:1',
        ]);

        $taxRate = $request->has('tax_rate') ? (float) $request->tax_rate : (float) Setting::getValue('tax_rate', '0');
        $exchangeRate = $request->has('exchange_rate') ? (int) $request->exchange_rate : 4100;

        $subtotal = 0;
        $itemsData = [];

        foreach ($validated['items'] as $item) {
            $product = Product::find($item['product_id']);

            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => "Product not found (ID: {$item['product_id']})",
                ], 422);
            }

            if ($product->stock_qty < $item['quantity']) {
                return response()->json([
                    'success' => false,
                    'message' => "Insufficient stock for \"{$product->name}\". Available: {$product->stock_qty}, requested: {$item['quantity']}",
                ], 422);
            }

            $lineTotal = $product->price * $item['quantity'];
            $subtotal += $lineTotal;

            $itemsData[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'unit_price' => $product->price,
                'cost_price' => $product->cost_price,
                'quantity' => $item['quantity'],
                'line_total' => $lineTotal,
            ];
        }

        $taxAmount = round($subtotal * ($taxRate / 100), 2);
        $total = $subtotal + $taxAmount;
        $tendered = $validated['tendered_amount'] ?? $total;
        $change = in_array($validated['payment_method'], ['cash', 'khqr'])
            ? max(0, $tendered - $total)
            : 0;

        $totalKhr = round($total * $exchangeRate);
        $tenderedKhr = round($tendered * $exchangeRate);
        $changeKhr = round($change * $exchangeRate);

        if ($validated['payment_method'] === 'cash' && $tendered < $total) {
            return response()->json([
                'success' => false,
                'message' => 'Tendered amount is less than total due.',
            ], 422);
        }

        $cashierId = $request->user()->id;
        $productIds = array_column($itemsData, 'product_id');

        $sale = DB::transaction(function () use ($validated, $subtotal, $taxRate, $taxAmount, $total, $tendered, $change, $totalKhr, $tenderedKhr, $changeKhr, $itemsData, $cashierId, $exchangeRate, $productIds) {
            $lockedProducts = Product::whereIn('id', $productIds)->lockForUpdate()->get();

            foreach ($validated['items'] as $item) {
                $locked = $lockedProducts->firstWhere('id', $item['product_id']);
                if (! $locked || $locked->stock_qty < $item['quantity']) {
                    throw new \App\Exceptions\InsufficientStockException(
                        $locked?->name ?? "Product #{$item['product_id']}",
                        $locked->stock_qty ?? 0,
                        $item['quantity']
                    );
                }
            }

            $sale = Sale::create([
                'user_id' => $cashierId,
                'invoice_number' => Sale::generateInvoiceNumber(),
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'total_khr' => $totalKhr,
                'payment_method' => $validated['payment_method'],
                'tendered_amount' => $tendered,
                'tendered_khr' => $tenderedKhr,
                'change_amount' => $change,
                'change_khr' => $changeKhr,
                'exchange_rate' => $exchangeRate,
            ]);

            foreach ($itemsData as $itemData) {
                SaleItem::create(array_merge($itemData, ['sale_id' => $sale->id]));
                Product::where('id', $itemData['product_id'])
                    ->decrement('stock_qty', $itemData['quantity']);
            }

            return $sale;
        });

        $sale->load(['user', 'items.product']);

        return response()->json($sale, 201);
    }
}
