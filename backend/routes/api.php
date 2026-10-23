<?php

use App\Http\Controllers\AbaWebhookController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DailyReportController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PendingPaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RestockReportController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\StoreSettingController;
use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'timestamp' => now()->toIso8601String(),
]));

Route::post('/login', [AuthController::class, 'login']);
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle']);
Route::post('/aba/webhook', [AbaWebhookController::class, 'handleWebhook']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Read-only — accessible by all authenticated users
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/preview-sku', [ProductController::class, 'previewSku']);
    Route::get('/products/{product}', [ProductController::class, 'show']);

    Route::get('/sales', [SaleController::class, 'index']);
    Route::get('/sales/{sale}', [SaleController::class, 'show']);
    Route::post('/sales', [SaleController::class, 'store']);

    Route::get('/reports', [ReportController::class, 'index']);

    // Create & restock — admin and stocker
    Route::middleware('role:admin,stocker')->group(function () {
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::post('products/{product}/restock', [ProductController::class, 'restock']);
        Route::get('/reports/restock', [RestockReportController::class, 'index']);
        Route::get('/reports/restock/pdf', [RestockReportController::class, 'exportPdf']);
        Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock']);
    });

    // Modify & delete — admin only
    Route::middleware('role:admin')->group(function () {
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
        Route::get('/reports/export-pdf', [ReportController::class, 'exportPdf']);
        Route::post('/settings/store', [StoreSettingController::class, 'update']);
        Route::apiResource('users', UserController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    });

    Route::get('/daily-reports', [DailyReportController::class, 'index']);
    Route::middleware('role:admin')->group(function () {
        Route::get('/daily-reports/{id}/export/csv', [DailyReportController::class, 'exportCsv']);
        Route::get('/daily-reports/{id}/export/pdf', [DailyReportController::class, 'exportPdf']);
    });

    Route::post('/payment/generate-qr', [PaymentController::class, 'generateQr']);
    Route::post('/payment/check-status', [PaymentController::class, 'checkStatus']);
    Route::post('/payments/generate-qr', [PaymentController::class, 'generateQr']);
    Route::post('/payments/check-status', [PaymentController::class, 'checkStatus']);

    Route::post('/pending-payments', [PendingPaymentController::class, 'store']);
    Route::get('/pending-payments/{sessionId}', [PendingPaymentController::class, 'show']);
    Route::post('/pending-payments/{sessionId}/confirm', [PendingPaymentController::class, 'confirm']);

    Route::get('/settings/store', [StoreSettingController::class, 'show']);
});
