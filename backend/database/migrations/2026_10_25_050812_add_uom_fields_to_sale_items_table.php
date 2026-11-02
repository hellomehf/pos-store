<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_items', 'unit_id')) {
                $table->foreignId('unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            }
            if (!Schema::hasColumn('sale_items', 'unit_name')) {
                $table->string('unit_name')->nullable();
            }
            if (!Schema::hasColumn('sale_items', 'conversion_factor')) {
                $table->decimal('conversion_factor', 10, 6)->default(1);
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            if (Schema::hasColumn('sale_items', 'unit_id')) {
                try { $table->dropForeign(['unit_id']); } catch (\Throwable $e) {}
                $table->dropColumn('unit_id');
            }
            if (Schema::hasColumn('sale_items', 'unit_name')) {
                $table->dropColumn('unit_name');
            }
            if (Schema::hasColumn('sale_items', 'conversion_factor')) {
                $table->dropColumn('conversion_factor');
            }
        });
    }
};
