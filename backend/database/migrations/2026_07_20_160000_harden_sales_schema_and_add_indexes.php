<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // === FIX 1: Change products.category_id from cascadeOnDelete to restrictOnDelete ===
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete();
        });

        // === FIX 2: Add SoftDeletes to products ===
        Schema::table('products', function (Blueprint $table) {
            $table->softDeletes();
        });

        // === FIX 3: Add SoftDeletes to categories ===
        Schema::table('categories', function (Blueprint $table) {
            $table->softDeletes();
        });

        // === FIX 4: Add SoftDeletes to users ===
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });

        // === FIX 5: Add performance indexes to sales table ===
        Schema::table('sales', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('created_at');
            $table->index('payment_method');
        });

        // === FIX 6: Add performance indexes to sale_items table ===
        Schema::table('sale_items', function (Blueprint $table) {
            $table->index('product_id');
        });

        // === FIX 7: Add index to daily_reports.report_date ===
        Schema::table('daily_reports', function (Blueprint $table) {
            $table->index('report_date');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->dropSoftDeletes();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['payment_method']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
        });

        Schema::table('daily_reports', function (Blueprint $table) {
            $table->dropIndex(['report_date']);
        });
    }
};
