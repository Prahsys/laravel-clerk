<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Prahsys\LaravelClerk\Database\Factories\PrahsysPaymentTransactionFactory;
use Prahsys\LaravelClerk\Traits\HasAuditLog;

class PrahsysPaymentTransaction extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog;

    protected $table = 'clerk_payment_transactions';

    protected $fillable = [
        'payment_session_id',
        'transaction_id',
        'type',
        'status',
        'amount',
        'currency',
        'reference',
        'gateway_response',
        'card_data',
        'customer_data',
        'processed_at',
        'captured_at',
        'refunded_at',
        'voided_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
        'card_data' => 'array',
        'customer_data' => 'array',
        'processed_at' => 'datetime',
        'captured_at' => 'datetime',
        'refunded_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function paymentSession(): BelongsTo
    {
        return $this->belongsTo(PaymentSession::class);
    }

    public function isPayment(): bool
    {
        return $this->type === 'payment';
    }

    public function isCapture(): bool
    {
        return $this->type === 'capture';
    }

    public function isRefund(): bool
    {
        return $this->type === 'refund';
    }

    public function isVoid(): bool
    {
        return $this->type === 'void';
    }

    public function isSuccessful(): bool
    {
        return in_array($this->status, ['captured', 'authorized', 'completed']);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    protected static function newFactory(): PrahsysPaymentTransactionFactory
    {
        return PrahsysPaymentTransactionFactory::new();
    }
}