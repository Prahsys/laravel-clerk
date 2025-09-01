<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Prahsys\LaravelClerk\Database\Factories\PrahsysAuditLogFactory;

class PrahsysAuditLog extends Model
{
    use HasFactory;

    protected $table = 'clerk_audit_logs';

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'event_type',
        'user_id',
        'user_type',
        'ip_address',
        'user_agent',
        'old_values',
        'new_values',
        'metadata',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
    ];

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function hasAuditChanges(): bool
    {
        return !empty($this->old_values) || !empty($this->new_values);
    }

    public function getChangedAttributes(): array
    {
        if (!$this->hasAuditChanges()) {
            return [];
        }

        $changes = [];
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];

        foreach (array_merge(array_keys($old), array_keys($new)) as $key) {
            $oldValue = $old[$key] ?? null;
            $newValue = $new[$key] ?? null;

            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }

    public function scopeForAuditable($query, $auditable)
    {
        return $query->where('auditable_type', get_class($auditable))
                    ->where('auditable_id', $auditable->getKey());
    }

    public function scopeForEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeRecent($query, ?int $limit = null)
    {
        $query = $query->orderBy('created_at', 'desc');
        
        return $limit ? $query->limit($limit) : $query;
    }

    protected static function newFactory(): PrahsysAuditLogFactory
    {
        return PrahsysAuditLogFactory::new();
    }
}