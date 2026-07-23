<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function lowStock(Request $request): JsonResponse
    {
        $threshold = $request->get('threshold', 10);

        $products = Product::with('category')
            ->where('stock_qty', '<=', $threshold)
            ->orderBy('stock_qty')
            ->get();

        return response()->json($products);
    }
}
