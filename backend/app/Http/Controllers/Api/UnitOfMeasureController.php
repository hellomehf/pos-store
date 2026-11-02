<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UnitOfMeasure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitOfMeasureController extends Controller
{
    public function index(): JsonResponse
    {
        $units = UnitOfMeasure::orderBy('name')->get();
        return response()->json($units);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'abbreviation' => 'required|string|max:10',
            'unit_type' => 'required|in:count,weight,volume',
        ]);

        $unit = UnitOfMeasure::create($validated);
        return response()->json($unit, 201);
    }
}
