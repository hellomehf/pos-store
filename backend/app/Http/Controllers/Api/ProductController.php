<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with('category');

        if ($request->has('category') && $request->category) {
            $query->where('category_id', $request->category);
        }

        if ($request->has('search') && $request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('sku', 'like', "%{$request->search}%");
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
            'cost_price' => 'required|numeric|min:0',
            'stock_qty' => 'required|integer|min:0',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['sku'])) {
            $category = Category::find($validated['category_id']);
            $validated['sku'] = Product::generateSku($category->name);
        }

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($validated);
        $product->load('category');

        return response()->json($product, 201);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load('category');

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

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }

    public function restock(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $product->increment('stock_qty', $validated['quantity']);

        return response()->json($product->fresh('category'));
    }

    public function previewSku(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
        ]);

        $category = Category::find($request->category_id);
        $sku = Product::generateSku($category->name);

        return response()->json(['sku' => $sku]);
    }
}
