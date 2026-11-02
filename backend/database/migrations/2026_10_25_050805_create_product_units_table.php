<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units_of_measure')->cascadeOnDelete();
            $table->decimal('conversion_factor', 10, 6)->default(1);
            $table->decimal('purchase_cost', 10, 2)->nullable();
            $table->decimal('selling_price', 10, 2)->nullable();
            $table->string('barcode')->nullable()->unique();
            $table->boolean('is_base_unit')->default(false);
            $table->boolean('is_purchase_unit')->default(false);
            $table->boolean('is_selling_unit')->default(true);
            $table->timestamps();
            
            $table->unique(['product_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};
