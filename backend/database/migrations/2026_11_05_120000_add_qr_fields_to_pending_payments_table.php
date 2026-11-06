<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_payments', function (Blueprint $table) {
            // Real ABA KHQR data returned by the PayWay QR API.
            $table->text('qr_string')->nullable();
            $table->longText('qr_image')->nullable();
            $table->string('qr_deeplink', 512)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pending_payments', function (Blueprint $table) {
            $table->dropColumn(['qr_string', 'qr_image', 'qr_deeplink']);
        });
    }
};
