<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Prahsys\LaravelClerk\Models\PaymentSession;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clerk_payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(PaymentSession::class)->constrained('clerk_payment_sessions')->cascadeOnDelete();
            $table->string('transaction_id')->unique();
            $table->string('type')->index(); // payment, capture, refund, void
            $table->string('status')->default('pending')->index();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('reference')->nullable();
            $table->json('gateway_response')->nullable();
            $table->json('card_data')->nullable();
            $table->json('customer_data')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['payment_session_id', 'type']);
            $table->index(['type', 'status']);
            $table->index(['processed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clerk_payment_transactions');
    }
};