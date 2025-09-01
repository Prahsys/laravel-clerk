<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Prahsys\LaravelClerk\Models\PaymentSession;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clerk_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(PaymentSession::class)->nullable()->constrained('clerk_payment_sessions')->nullOnDelete();
            $table->string('event_id')->unique();
            $table->string('event_type')->index();
            $table->string('status')->default('pending')->index();
            $table->json('payload');
            $table->string('signature')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->integer('retry_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_type', 'status']);
            $table->index(['status', 'retry_count']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clerk_webhook_events');
    }
};