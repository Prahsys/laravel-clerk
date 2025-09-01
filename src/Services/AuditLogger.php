<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Prahsys\LaravelClerk\Models\PrahsysAuditLog;

class AuditLogger
{
    /**
     * Log an audit event
     */
    public function log(
        Model $auditable,
        string $eventType,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
        ?Request $request = null
    ): PrahsysAuditLog {
        $request = $request ?? request();
        $user = Auth::user();

        return PrahsysAuditLog::create([
            'auditable_type' => get_class($auditable),
            'auditable_id' => $auditable->getKey(),
            'event_type' => $eventType,
            'user_id' => $user?->getKey(),
            'user_type' => $user ? get_class($user) : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'old_values' => $this->sanitizeValues($oldValues),
            'new_values' => $this->sanitizeValues($newValues),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Log creation event
     */
    public function logCreated(Model $model, array $metadata = []): PrahsysAuditLog
    {
        return $this->log(
            auditable: $model,
            eventType: 'created',
            newValues: $model->getAttributes(),
            metadata: $metadata
        );
    }

    /**
     * Log update event
     */
    public function logUpdated(Model $model, array $originalValues = [], array $metadata = []): PrahsysAuditLog
    {
        return $this->log(
            auditable: $model,
            eventType: 'updated',
            oldValues: $originalValues,
            newValues: $model->getChanges(),
            metadata: $metadata
        );
    }

    /**
     * Log deletion event
     */
    public function logDeleted(Model $model, array $metadata = []): PrahsysAuditLog
    {
        return $this->log(
            auditable: $model,
            eventType: 'deleted',
            oldValues: $model->getAttributes(),
            metadata: $metadata
        );
    }

    /**
     * Log payment processing event
     */
    public function logPaymentProcessed(Model $model, string $status, array $gatewayResponse = []): PrahsysAuditLog
    {
        return $this->log(
            auditable: $model,
            eventType: 'payment_processed',
            metadata: [
                'status' => $status,
                'gateway_response' => $this->sanitizeValues($gatewayResponse),
                'timestamp' => now()->toISOString(),
            ]
        );
    }

    /**
     * Log webhook processing event
     */
    public function logWebhookProcessed(Model $model, string $eventType, array $payload = []): PrahsysAuditLog
    {
        return $this->log(
            auditable: $model,
            eventType: 'webhook_processed',
            metadata: [
                'webhook_event_type' => $eventType,
                'payload_summary' => $this->summarizePayload($payload),
                'timestamp' => now()->toISOString(),
            ]
        );
    }

    /**
     * Log session expiry
     */
    public function logSessionExpired(Model $model, array $metadata = []): PrahsysAuditLog
    {
        return $this->log(
            auditable: $model,
            eventType: 'session_expired',
            metadata: array_merge([
                'expired_at' => now()->toISOString(),
            ], $metadata)
        );
    }

    /**
     * Log refund processing
     */
    public function logRefundProcessed(Model $model, float $amount, string $reason = null): PrahsysAuditLog
    {
        return $this->log(
            auditable: $model,
            eventType: 'refund_processed',
            metadata: [
                'refund_amount' => $amount,
                'reason' => $reason,
                'timestamp' => now()->toISOString(),
            ]
        );
    }

    /**
     * Sanitize sensitive values for logging
     */
    protected function sanitizeValues(array $values): array
    {
        $sensitiveFields = [
            'password',
            'api_key',
            'secret',
            'token',
            'card_number',
            'cvv',
            'ssn',
            'credit_card',
        ];

        return collect($values)->map(function ($value, $key) use ($sensitiveFields) {
            if (in_array(strtolower($key), $sensitiveFields)) {
                return '[REDACTED]';
            }

            if (is_string($value) && strlen($value) > 16 && preg_match('/^\d{13,19}$/', $value)) {
                // Looks like a credit card number
                return '[CARD_NUMBER_REDACTED]';
            }

            return $value;
        })->toArray();
    }

    /**
     * Create a summary of webhook payload for audit logging
     */
    protected function summarizePayload(array $payload): array
    {
        return [
            'event_id' => $payload['id'] ?? null,
            'event_type' => $payload['type'] ?? null,
            'object_id' => $payload['data']['object']['id'] ?? null,
            'amount' => $payload['data']['object']['amount'] ?? null,
            'currency' => $payload['data']['object']['currency'] ?? null,
            'status' => $payload['data']['object']['status'] ?? null,
        ];
    }

    /**
     * Get audit logs for a model
     */
    public function getAuditLogs(Model $model, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return PrahsysAuditLog::where('auditable_type', get_class($model))
            ->where('auditable_id', $model->getKey())
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Clean up old audit logs
     */
    public function cleanupOldLogs(int $daysToKeep = 365): int
    {
        $cutoffDate = now()->subDays($daysToKeep);
        
        return PrahsysAuditLog::where('created_at', '<', $cutoffDate)->delete();
    }
}