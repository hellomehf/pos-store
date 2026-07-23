<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\DailyReportController;
use App\Http\Controllers\Api\StoreSettingController;
use App\Http\Controllers\Api\PendingPaymentController;
use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\AbaWebhookController;
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

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);
    Route::middleware('admin')->group(function () {
        Route::apiResource('categories', CategoryController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('products', ProductController::class)->only(['store', 'update', 'destroy']);
        Route::post('products/{product}/restock', [ProductController::class, 'restock']);
    });
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/preview-sku', [ProductController::class, 'previewSku']);
    Route::get('/products/{product}', [ProductController::class, 'show']);

    Route::get('/sales', [SaleController::class, 'index']);
    Route::get('/sales/{sale}', [SaleController::class, 'show']);
    Route::post('/sales', [SaleController::class, 'store']);

    Route::get('/reports', [ReportController::class, 'index']);
    Route::middleware('admin')->group(function () {
        Route::get('/reports/export-pdf', [ReportController::class, 'exportPdf']);
    });
    Route::get('/daily-reports', [DailyReportController::class, 'index']);
    Route::middleware('admin')->group(function () {
        Route::get('/daily-reports/{id}/export/csv', [DailyReportController::class, 'exportCsv']);
        Route::get('/daily-reports/{id}/export/pdf', [DailyReportController::class, 'exportPdf']);
    });
    Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock']);

    Route::post('/payment/generate-qr', [PaymentController::class, 'generateQr']);
    Route::post('/payment/check-status', [PaymentController::class, 'checkStatus']);
    Route::post('/payments/generate-qr', [PaymentController::class, 'generateQr']);
    Route::post('/payments/check-status', [PaymentController::class, 'checkStatus']);

    Route::post('/pending-payments', [PendingPaymentController::class, 'store']);
    Route::get('/pending-payments/{sessionId}', [PendingPaymentController::class, 'show']);
    Route::post('/pending-payments/{sessionId}/confirm', [PendingPaymentController::class, 'confirm']);

    Route::middleware('admin')->group(function () {
        Route::get('/settings/store', [StoreSettingController::class, 'show']);
        Route::post('/settings/store', [StoreSettingController::class, 'update']);
    });

    Route::apiResource('users', UserController::class)->middleware('admin');
});
