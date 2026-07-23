<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_payments', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->index();
            $table->string('invoice_number')->nullable()->unique();
            $table->decimal('expected_amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending');
            $table->string('payment_reference')->nullable();
            $table->string('sender_name')->nullable();
            $table->text('telegram_message_raw')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_payments');
    }
};
