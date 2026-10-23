<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\RestockLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with('category', 'creator');

        if ($request->has('category') && $request->category) {
            $query->where('category_id', $request->category);
        }

        if ($request->has('search') && $request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('sku', 'like', "%{$request->search}%")
                    ->orWhere('barcode', 'like', "%{$request->search}%");
            });
        }

        if ($request->has('low_stock') && $request->low_stock) {
            $query->where('stock_qty', '<=', 10);
        }

        $products = $query->orderBy('name')->get();

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:50|unique:products,sku',
            'barcode' => 'nullable|string|max:50|unique:products,barcode',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock_qty' => 'required|integer|min:0',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['sku'])) {
            $category = Category::find($validated['category_id']);
            $validated['sku'] = Product::generateSku($category->name, $validated['category_id']);
        }

        $validated['cost_price'] ??= 0;
        $validated['created_by'] = $request->user()->id;

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($validated);
        $product->load('category', 'creator');

        return response()->json($product, 201);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load('category', 'creator');

        return response()->json($product);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:50|unique:products,sku,' . $product->id,
            'barcode' => 'nullable|string|max:50|unique:products,barcode,' . $product->id,
            'price' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
            'stock_qty' => 'required|integer|min:0',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($validated);
        $product->load('category');

        return response()->json($product);
    }

    public function destroy(Product $product): JsonResponse
    {
        $salesCount = $product->saleItems()->count();

        if ($salesCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete product. It appears in {$salesCount} historical sale record(s). Deactivate it instead.",
            ], 422);
        }

        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        try {
            $deleted = $product->delete();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Product delete failed: " . $e->getMessage());
            return response()->json(['message' => 'Delete failed: ' . $e->getMessage()], 500);
        }

        if (!$deleted) {
            return response()->json(['message' => 'Delete returned false — product may not exist.'], 404);
        }

        return response()->json(['message' => 'Product deleted successfully']);
    }

    public function restock(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'note'     => 'nullable|string|max:255',
        ]);

        try {
            DB::transaction(function () use ($request, $product, $validated) {
                $previousStock = $product->stock_qty;
                $product->increment('stock_qty', $validated['quantity']);
                $product->refresh();

                RestockLog::create([
                    'product_id'     => $product->id,
                    'user_id'        => $request->user()->id,
                    'quantity_added' => $validated['quantity'],
                    'previous_stock' => $previousStock,
                    'new_stock'      => $product->stock_qty,
                    'note'           => $validated['note'] ?? null,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error("Restock failed for product #{$product->id}: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Failed to restock product.'], 500);
        }

        return response()->json($product->fresh('category'));
    }

    public function previewSku(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
        ]);

        $category = Category::find($request->category_id);

        $prefix = null;
        $existingSku = Product::where('category_id', $category->id)
            ->where('sku', 'like', '___-%')
            ->value('sku');
        if ($existingSku && preg_match('/^([A-Z]{2,4})-/', $existingSku, $m)) {
            $prefix = $m[1];
        }

        if (!$prefix) {
            $clean = preg_replace('/[^A-Za-z]/', '', $category->name);
            $prefix = strtoupper(substr($clean, 0, 3));
            if (strlen($prefix) < 3) {
                $prefix = str_pad($prefix, 3, 'X');
            }
        }

        $lastSku = Product::where('sku', 'like', "$prefix-%")
            ->orderByRaw("CAST(SUBSTRING(sku, LOCATE('-', sku) + 1) AS UNSIGNED) DESC")
            ->value('sku');

        if ($lastSku && preg_match('/-(\d+)$/', $lastSku, $m)) {
            $next = (int) $m[1] + 1;
        } else {
            $next = 1;
        }

        $sku = $prefix . '-' . str_pad($next, 3, '0', STR_PAD_LEFT);

        while (Product::where('sku', $sku)->exists()) {
            $next++;
            $sku = $prefix . '-' . str_pad($next, 3, '0', STR_PAD_LEFT);
        }

        return response()->json(['sku' => $sku]);
    }
}
