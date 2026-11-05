<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendSaleReceipt;
use App\Models\Product;
use App\Models\ProductUnit;
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
            'items.*.quantity' => 'required|numeric|min:0.000001',
            'items.*.unit_id' => 'nullable|exists:units_of_measure,id',
            'items.*.product_unit_id' => 'nullable|exists:product_units,id',
            'payment_method' => 'required|in:cash,card,khqr,aba',
            'tendered_amount' => 'required_if:payment_method,cash|nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'exchange_rate' => 'nullable|integer|min:1',
        ]);

        $taxRate = $request->has('tax_rate') ? (float) $request->tax_rate : (float) Setting::getValue('tax_rate', '0');
        $exchangeRate = $request->has('exchange_rate') ? (int) $request->exchange_rate : 4100;

        $subtotal = 0;
        $itemsData = [];

        foreach ($validated['items'] as $item) {
            $product = Product::with('productUnits.unit')->find($item['product_id']);

            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => "Product not found (ID: {$item['product_id']})",
                ], 422);
            }

            $qty = (float)$item['quantity'];
            $conversion = 1;
            $unitId = null;
            $unitName = null;
            $unitPrice = (float)$product->price;
            $costPrice = (float)$product->cost_price;

            if (!empty($item['product_unit_id'])) {
                $pu = $product->productUnits->firstWhere('id', $item['product_unit_id']);
                if ($pu) {
                    $conversion = (float)$pu->conversion_factor ?: 1;
                    $unitId = $pu->unit_id;
                    $unitName = $pu->unit->name ?? null;
                    $unitPrice = $pu->selling_price !== null ? (float)$pu->selling_price : $unitPrice;
                    $costPrice = $pu->purchase_cost !== null ? (float)$pu->purchase_cost : $costPrice;
                }
            } elseif (!empty($item['unit_id'])) {
                $pu = $product->productUnits->firstWhere('unit_id', $item['unit_id']);
                if ($pu) {
                    $conversion = (float)$pu->conversion_factor ?: 1;
                    $unitId = $pu->unit_id;
                    $unitName = $pu->unit->name ?? null;
                    $unitPrice = $pu->selling_price !== null ? (float)$pu->selling_price : $unitPrice;
                    $costPrice = $pu->purchase_cost !== null ? (float)$pu->purchase_cost : $costPrice;
                }
            }

            $baseQty = (int)round($qty * $conversion);
            if ($baseQty < 1) {
                return response()->json([
                    'success' => false,
                    'message' => "Invalid quantity for \"{$product->name}\".",
                ], 422);
            }

            if ($product->stock_qty < $baseQty) {
                return response()->json([
                    'success' => false,
                    'message' => "Insufficient stock for \"{$product->name}\". Available: {$product->stock_qty}, requested: {$baseQty}",
                ], 422);
            }

            $lineTotal = round($unitPrice * $qty, 2);
            $subtotal += $lineTotal;

            $itemsData[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'unit_price' => $unitPrice,
                'cost_price' => $costPrice,
                'quantity' => $qty,
                'line_total' => $lineTotal,
                'unit_id' => $unitId,
                'unit_name' => $unitName,
                'conversion_factor' => $conversion,
                'base_qty' => $baseQty,
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

            foreach ($itemsData as $itemData) {
                $locked = $lockedProducts->firstWhere('id', $itemData['product_id']);
                if (! $locked || $locked->stock_qty < $itemData['base_qty']) {
                    throw new \App\Exceptions\InsufficientStockException(
                        $locked?->name ?? "Product #{$itemData['product_id']}",
                        $locked->stock_qty ?? 0,
                        $itemData['base_qty']
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
                $baseQty = $itemData['base_qty'];
                unset($itemData['base_qty']);
                SaleItem::create(array_merge($itemData, ['sale_id' => $sale->id]));
                Product::where('id', $itemData['product_id'])
                    ->decrement('stock_qty', $baseQty);
            }

            return $sale;
        });

        $sale->load(['user', 'items.product']);

        SendSaleReceipt::dispatch($sale->id);

        return response()->json($sale, 201);
    }
}
