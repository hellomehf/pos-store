<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('total_khr', 14, 2)->nullable()->after('total');
            $table->decimal('tendered_khr', 14, 2)->nullable()->after('tendered_amount');
            $table->decimal('change_khr', 14, 2)->nullable()->after('change_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['total_khr', 'tendered_khr', 'change_khr']);
        });
    }
};
