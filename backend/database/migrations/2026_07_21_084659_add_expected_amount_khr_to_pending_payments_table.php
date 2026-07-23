<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_payments', function (Blueprint $table) {
            $table->decimal('expected_amount_khr', 14, 2)->nullable()->after('expected_amount');
        });
    }

    public function down(): void
    {
        Schema::table('pending_payments', function (Blueprint $table) {
            $table->dropColumn('expected_amount_khr');
        });
    }
};
