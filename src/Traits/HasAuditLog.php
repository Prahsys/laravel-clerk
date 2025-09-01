<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Prahsys\LaravelClerk\Models\PrahsysAuditLog;
use Prahsys\LaravelClerk\Services\AuditLogger;

trait HasAuditLog
{
    protected static bool $auditingEnabled = true;

    public static function bootHasAuditLog(): void
    {
        if (!static::$auditingEnabled) {
            return;
        }

        static::created(function ($model) {
            app(AuditLogger::class)->logCreated($model);
        });

        static::updated(function ($model) {
            if ($model->wasChanged()) {
                app(AuditLogger::class)->logUpdated($model, $model->getOriginal());
            }
        });

        static::deleted(function ($model) {
            app(AuditLogger::class)->logDeleted($model);
        });
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(PrahsysAuditLog::class, 'auditable');
    }

    public function getLatestAuditLog(): ?PrahsysAuditLog
    {
        return $this->auditLogs()->latest()->first();
    }

    public function disableAuditing(): static
    {
        static::$auditingEnabled = false;
        return $this;
    }

    public function enableAuditing(): static
    {
        static::$auditingEnabled = true;
        return $this;
    }

    public function isAuditingEnabled(): bool
    {
        return static::$auditingEnabled;
    }
}