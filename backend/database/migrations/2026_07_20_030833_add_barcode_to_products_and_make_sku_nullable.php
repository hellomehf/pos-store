<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'barcode')) {
                $table->string('barcode', 50)->nullable()->unique()->after('sku');
            }
            if (DB::getSchemaBuilder()->hasIndex('products', 'products_sku_unique')) {
                $table->dropIndex('products_sku_unique');
            }
            $table->string('sku', 50)->nullable()->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (DB::getSchemaBuilder()->hasIndex('products', 'products_barcode_unique')) {
                $table->dropIndex('products_barcode_unique');
            }
            $table->dropColumn('barcode');
            $table->string('sku', 50)->nullable(false)->unique()->change();
        });
    }
};
