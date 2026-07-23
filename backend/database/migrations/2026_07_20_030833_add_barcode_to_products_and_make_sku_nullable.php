<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // 1. Add barcode if it doesn't exist
            if (!Schema::hasColumn('products', 'barcode')) {
                $table->string('barcode', 50)->nullable()->unique()->after('sku');
            }

            // 2. Drop the unique constraint (works for both Postgres and MySQL)
            // Using try/catch or Laravel's Schema check safely
            $table->dropUnique('products_sku_unique');

            // 3. Make SKU nullable
            $table->string('sku', 50)->nullable()->change();

            // 4. Re-add unique index to nullable SKU
            $table->unique('sku', 'products_sku_unique');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'barcode')) {
                $table->dropUnique('products_barcode_unique');
                $table->dropColumn('barcode');
            }

            $table->dropUnique('products_sku_unique');
            $table->string('sku', 50)->nullable(false)->change();
            $table->unique('sku', 'products_sku_unique');
        });
    }
};
