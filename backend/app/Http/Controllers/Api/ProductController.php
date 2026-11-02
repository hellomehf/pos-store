<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\RestockLog;
use App\Models\UnitOfMeasure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'creator', 'productUnits.unit', 'baseUnit']);

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
            'base_unit_id' => 'nullable|exists:units_of_measure,id',
            'units' => 'nullable|array',
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

        $product = DB::transaction(function () use ($validated, $request) {
            $product = Product::create($validated);
            if (isset($validated['units']) && is_array($validated['units'])) {
                $this->syncProductUnits($product, $validated['units']);
            } elseif (!empty($validated['base_unit_id'])) {
                ProductUnit::updateOrCreate(
                    ['product_id' => $product->id, 'unit_id' => $validated['base_unit_id']],
                    [
                        'conversion_factor' => 1,
                        'purchase_cost' => $validated['cost_price'] ?? 0,
                        'selling_price' => $validated['price'] ?? 0,
                        'is_base_unit' => true,
                        'is_purchase_unit' => true,
                        'is_selling_unit' => true,
                    ]
                );
            }
            return $product;
        });

        $product->load(['category', 'creator', 'productUnits.unit', 'baseUnit']);

        return response()->json($product, 201);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load(['category', 'creator', 'productUnits.unit', 'baseUnit']);

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
            'base_unit_id' => 'nullable|exists:units_of_measure,id',
            'units' => 'nullable|array',
        ]);

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $product = DB::transaction(function () use ($validated, $request, $product) {
            $oldBaseUnitId = $product->base_unit_id;
            $product->update($validated);

            if (isset($validated['units']) && is_array($validated['units'])) {
                $this->syncProductUnits($product, $validated['units']);
            } elseif (array_key_exists('base_unit_id', $validated)) {
                $newBaseUnitId = $validated['base_unit_id'];
                if ($oldBaseUnitId && (int)$oldBaseUnitId !== (int)$newBaseUnitId) {
                    ProductUnit::where('product_id', $product->id)
                        ->where('unit_id', $oldBaseUnitId)
                        ->where('conversion_factor', 1)
                        ->delete();
                }

                if (!empty($newBaseUnitId)) {
                    ProductUnit::where('product_id', $product->id)
                        ->where('unit_id', '!=', $newBaseUnitId)
                        ->where('is_base_unit', true)
                        ->update(['is_base_unit' => false]);

                    ProductUnit::updateOrCreate(
                        ['product_id' => $product->id, 'unit_id' => $newBaseUnitId],
                        [
                            'conversion_factor' => 1,
                            'purchase_cost' => $product->cost_price ?? 0,
                            'selling_price' => $product->price ?? 0,
                            'is_base_unit' => true,
                            'is_purchase_unit' => true,
                            'is_selling_unit' => true,
                        ]
                    );
                } else {
                    ProductUnit::where('product_id', $product->id)
                        ->where('is_base_unit', true)
                        ->update(['is_base_unit' => false]);
                }
            }

            return $product;
        });

        $product->load(['category', 'creator', 'productUnits.unit', 'baseUnit']);

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
            'quantity' => 'required|numeric|min:0.000001',
            'note'     => 'nullable|string|max:255',
            'unit_id'  => 'nullable|exists:units_of_measure,id',
            'product_unit_id' => 'nullable|exists:product_units,id',
        ]);

        try {
            DB::transaction(function () use ($request, $product, $validated) {
                $previousStock = $product->stock_qty;
                $qty = (float)$validated['quantity'];
                $conversion = 1;
                
                if (!empty($validated['product_unit_id'])) {
                    $pu = ProductUnit::find($validated['product_unit_id']);
                    if ($pu && $pu->product_id == $product->id) {
                        $conversion = (float)$pu->conversion_factor ?: 1;
                    }
                } elseif (!empty($validated['unit_id'])) {
                    $pu = ProductUnit::where('product_id', $product->id)->where('unit_id', $validated['unit_id'])->first();
                    if ($pu) {
                        $conversion = (float)$pu->conversion_factor ?: 1;
                    }
                }
                
                $baseQty = (int)round($qty * $conversion);
                if ($baseQty < 0) $baseQty = 0;
                
                $product->increment('stock_qty', $baseQty);
                $product->refresh();

                RestockLog::create([
                    'product_id'     => $product->id,
                    'user_id'        => $request->user()->id,
                    'quantity_added' => $baseQty,
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

        return response()->json($product->fresh(['category', 'productUnits.unit', 'baseUnit']));
    }

    public function units(Product $product): JsonResponse
    {
        $units = ProductUnit::with('unit')->where('product_id', $product->id)->get();
        return response()->json($units);
    }

    public function saveUnits(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'units' => 'present|array',
            'units.*.unit_id' => 'required|exists:units_of_measure,id',
            'units.*.conversion_factor' => 'nullable|numeric|min:0.000001',
            'units.*.purchase_cost' => 'nullable|numeric|min:0',
            'units.*.selling_price' => 'nullable|numeric|min:0',
            'units.*.barcode' => 'nullable|string|max:50',
            'units.*.is_base_unit' => 'nullable|boolean',
            'units.*.is_purchase_unit' => 'nullable|boolean',
            'units.*.is_selling_unit' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($validated, $product) {
            $this->syncProductUnits($product, $validated['units'] ?? []);
        });

        $product->load(['productUnits.unit', 'baseUnit']);
        return response()->json($product);
    }

    private function syncProductUnits(Product $product, array $incomingUnits): void
    {
        $incomingUnits = array_values(array_filter($incomingUnits, fn($u) => !empty($u['unit_id'])));

        // 1. Base unit resolution
        $baseUnitItem = null;
        foreach ($incomingUnits as $u) {
            if (!empty($u['is_base_unit'])) {
                $baseUnitItem = $u;
                break;
            }
        }

        if ($baseUnitItem) {
            $product->base_unit_id = (int)$baseUnitItem['unit_id'];
            $product->save();
        } elseif (!empty($incomingUnits)) {
            $hasCurrentBase = false;
            if ($product->base_unit_id) {
                foreach ($incomingUnits as $u) {
                    if ((int)$u['unit_id'] === (int)$product->base_unit_id) {
                        $hasCurrentBase = true;
                        break;
                    }
                }
            }

            if (!$hasCurrentBase) {
                $product->base_unit_id = (int)$incomingUnits[0]['unit_id'];
                $product->save();
            }
        }

        // 2. Collect allowed unit IDs
        $allowedUnitIds = [];
        foreach ($incomingUnits as $u) {
            $allowedUnitIds[] = (int)$u['unit_id'];
        }
        if ($product->base_unit_id && !in_array((int)$product->base_unit_id, $allowedUnitIds, true)) {
            $allowedUnitIds[] = (int)$product->base_unit_id;
        }
        $allowedUnitIds = array_values(array_unique($allowedUnitIds));

        // 3. Delete units that were removed by the user
        ProductUnit::where('product_id', $product->id)
            ->whereNotIn('unit_id', $allowedUnitIds)
            ->delete();

        // 4. Update or create units from incoming list
        foreach ($incomingUnits as $u) {
            $unitId = (int)$u['unit_id'];
            $isBase = ($product->base_unit_id && $unitId === (int)$product->base_unit_id) || !empty($u['is_base_unit']);

            $barcode = !empty($u['barcode']) ? trim($u['barcode']) : null;
            if ($barcode) {
                $exists = ProductUnit::where('barcode', $barcode)
                    ->where(function ($q) use ($product, $unitId) {
                        $q->where('product_id', '!=', $product->id)
                          ->orWhere('unit_id', '!=', $unitId);
                    })->exists();
                if ($exists) {
                    $barcode = null;
                }
            }

            $convFactor = $isBase ? 1 : (float)($u['conversion_factor'] ?? 1);
            if ($convFactor <= 0) $convFactor = 1;

            $purchaseCost = ($u['purchase_cost'] !== null && $u['purchase_cost'] !== '')
                ? (float)$u['purchase_cost']
                : ($isBase ? (float)($product->cost_price ?? 0) : null);

            $sellingPrice = ($u['selling_price'] !== null && $u['selling_price'] !== '')
                ? (float)$u['selling_price']
                : ($isBase ? (float)($product->price ?? 0) : null);

            ProductUnit::updateOrCreate(
                ['product_id' => $product->id, 'unit_id' => $unitId],
                [
                    'conversion_factor' => $convFactor,
                    'purchase_cost' => $purchaseCost,
                    'selling_price' => $sellingPrice,
                    'barcode' => $barcode,
                    'is_base_unit' => $isBase,
                    'is_purchase_unit' => isset($u['is_purchase_unit']) ? (bool)$u['is_purchase_unit'] : true,
                    'is_selling_unit' => isset($u['is_selling_unit']) ? (bool)$u['is_selling_unit'] : true,
                ]
            );
        }

        // 5. Ensure the base unit record is properly marked
        if ($product->base_unit_id) {
            ProductUnit::where('product_id', $product->id)
                ->where('unit_id', '!=', $product->base_unit_id)
                ->where('is_base_unit', true)
                ->update(['is_base_unit' => false]);

            ProductUnit::updateOrCreate(
                ['product_id' => $product->id, 'unit_id' => $product->base_unit_id],
                [
                    'conversion_factor' => 1,
                    'purchase_cost' => $product->cost_price ?? 0,
                    'selling_price' => $product->price ?? 0,
                    'is_base_unit' => true,
                    'is_purchase_unit' => true,
                    'is_selling_unit' => true,
                ]
            );
        } else {
            ProductUnit::where('product_id', $product->id)
                ->where('is_base_unit', true)
                ->update(['is_base_unit' => false]);
        }
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
