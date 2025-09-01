<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Prahsys\LaravelClerk\Database\Factories\PrahsysWebhookEventFactory;

class PrahsysWebhookEvent extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'clerk_webhook_events';

    protected $fillable = [
        'payment_session_id',
        'event_id',
        'event_type',
        'status',
        'payload',
        'signature',
        'processed_at',
        'failed_at',
        'retry_count',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
        'failed_at' => 'datetime',
        'retry_count' => 'integer',
    ];

    public function paymentSession(): BelongsTo
    {
        return $this->belongsTo(PaymentSession::class);
    }

    public function isProcessed(): bool
    {
        return $this->status === 'processed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function canRetry(): bool
    {
        return $this->isFailed() && $this->retry_count < config('clerk.webhooks.max_attempts', 3);
    }

    public function needsRetry(): bool
    {
        return $this->isFailed() && $this->retry_count < config('clerk.webhooks.max_retry_attempts', 5);
    }

    public function incrementRetryCount(): void
    {
        $this->increment('retry_count');
    }

    public function markAsProcessed(): void
    {
        $this->update([
            'status' => 'processed',
            'processed_at' => now(),
            'error_message' => null,
        ]);
    }

    public function markAsFailed(string $errorMessage = null): void
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }

    protected static function newFactory(): PrahsysWebhookEventFactory
    {
        return PrahsysWebhookEventFactory::new();
    }
}