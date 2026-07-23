<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->date('report_date')->unique();
            $table->decimal('gross_revenue', 12, 2)->default(0);
            $table->decimal('total_expenses', 12, 2)->default(0);
            $table->decimal('net_profit', 12, 2)->default(0);
            $table->decimal('total_tax_collected', 12, 2)->default(0);
            $table->integer('total_transactions')->default(0);
            $table->integer('cash_count')->default(0);
            $table->integer('card_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_reports');
    }
};
