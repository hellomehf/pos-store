<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aba_payments', function (Blueprint $table) {
            $table->id();
            $table->string('tran_id', 20)->unique();   // PayWay tran_id — max 20 chars
            $table->unsignedBigInteger('order_id')->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status', 10)->default('PENDING'); // PENDING | PAID | FAILED
            $table->timestamps();

            // FK is advisory; we don't cascade-delete payments when a sale is deleted.
            $table->foreign('order_id')->references('id')->on('sales')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aba_payments');
    }
};
