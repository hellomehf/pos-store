<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StoreSettingController extends Controller
{
    public function show(): JsonResponse
    {
        $storeName = Setting::getValue('store_name', 'POS Store');
        $storeLogoSetting = Setting::where('key', 'store_logo')->first();
        $storeLogo = $storeLogoSetting?->value;
        $exchangeRate = (int) Setting::getValue('exchange_rate', '4100');
        $taxRate = (float) Setting::getValue('tax_rate', '0');

        $logoUrl = null;
        if ($storeLogo && Storage::disk('public')->exists($storeLogo)) {
            $logoUrl = Storage::disk('public')->url($storeLogo) . '?v=' . $storeLogoSetting->updated_at->timestamp;
        }

        return response()->json([
            'store_name' => $storeName,
            'store_logo' => $logoUrl,
            'store_logo_path' => $storeLogo,
            'exchange_rate' => $exchangeRate,
            'tax_rate' => $taxRate,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_name' => 'nullable|string|max:255',
            'store_logo' => 'nullable|image|mimes:png,jpg,jpeg,svg|max:2048',
            'exchange_rate' => 'nullable|integer|min:1',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $changes = [];

        if (isset($validated['store_name'])) {
            $old = Setting::getValue('store_name', 'POS Store');
            Setting::setValue('store_name', $validated['store_name']);
            $changes['store_name'] = ['old' => $old, 'new' => $validated['store_name']];
        }

        if (isset($validated['exchange_rate'])) {
            $old = Setting::getValue('exchange_rate', '4100');
            Setting::setValue('exchange_rate', $validated['exchange_rate']);
            $changes['exchange_rate'] = ['old' => $old, 'new' => $validated['exchange_rate']];
        }

        if (isset($validated['tax_rate'])) {
            $old = Setting::getValue('tax_rate', '0');
            Setting::setValue('tax_rate', $validated['tax_rate']);
            $changes['tax_rate'] = ['old' => $old, 'new' => $validated['tax_rate']];
        }

        if ($request->hasFile('store_logo')) {
            $oldLogo = Setting::getValue('store_logo');

            if ($oldLogo && Storage::disk('public')->exists($oldLogo)) {
                Storage::disk('public')->delete($oldLogo);
            }

            $path = $request->file('store_logo')->store('store', 'public');
            Setting::setValue('store_logo', $path);
            $changes['store_logo'] = ['old' => $oldLogo, 'new' => $path];
        }

        if (!empty($changes)) {
            ActivityLog::log(
                'settings.updated',
                'Store settings updated: ' . implode(', ', array_keys($changes)),
                $changes
            );
        }

        $storeName = Setting::getValue('store_name', 'POS Store');
        $storeLogoSetting = Setting::where('key', 'store_logo')->first();
        $storeLogo = $storeLogoSetting?->value;

        $logoUrl = null;
        if ($storeLogo && Storage::disk('public')->exists($storeLogo)) {
            $logoUrl = Storage::disk('public')->url($storeLogo) . '?v=' . $storeLogoSetting->updated_at->timestamp;
        }

        return response()->json([
            'message' => 'Store settings updated successfully',
            'store_name' => $storeName,
            'store_logo' => $logoUrl,
            'store_logo_path' => $storeLogo,
            'exchange_rate' => (int) Setting::getValue('exchange_rate', '4100'),
            'tax_rate' => (float) Setting::getValue('tax_rate', '0'),
        ]);
    }
}
