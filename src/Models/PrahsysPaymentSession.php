<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Prahsys\LaravelClerk\Database\Factories\PrahsysPaymentSessionFactory;
use Prahsys\LaravelClerk\Traits\HasAuditLog;

class PrahsysPaymentSession extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog;

    protected $table = 'clerk_payment_sessions';

    protected $fillable = [
        'session_id',
        'payment_id',
        'merchant_id',
        'status',
        'amount',
        'currency',
        'description',
        'customer_email',
        'customer_name',
        'payment_method',
        'card_last4',
        'card_brand',
        'portal_configuration',
        'success_indicator',
        'result_indicator',
        'metadata',
        'expires_at',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'portal_configuration' => 'array',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(WebhookEvent::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && now()->isAfter($this->expires_at);
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, ['captured', 'authorized']);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isPortalSession(): bool
    {
        return !empty($this->portal_configuration);
    }

    protected static function newFactory(): PrahsysPaymentSessionFactory
    {
        return PrahsysPaymentSessionFactory::new();
    }
}