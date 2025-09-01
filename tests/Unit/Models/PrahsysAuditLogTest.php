<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use Prahsys\LaravelClerk\Models\PrahsysAuditLog;
use Prahsys\LaravelClerk\Models\PaymentSession;
use Prahsys\LaravelClerk\ClerkServiceProvider;

class PrahsysAuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [ClerkServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
    
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
    }

    public function test_audit_log_has_correct_table_name(): void
    {
        $log = new PrahsysAuditLog();
        
        $this->assertEquals('clerk_audit_logs', $log->getTable());
    }

    public function test_audit_log_has_correct_fillable_attributes(): void
    {
        $expected = [
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

        $log = new PrahsysAuditLog();
        
        $this->assertEquals($expected, $log->getFillable());
    }

    public function test_audit_log_casts_correctly(): void
    {
        $log = PrahsysAuditLog::factory()->create([
            'old_values' => ['status' => 'pending'],
            'new_values' => ['status' => 'captured'],
            'metadata' => ['source' => 'test'],
        ]);

        $this->assertIsArray($log->old_values);
        $this->assertIsArray($log->new_values);
        $this->assertIsArray($log->metadata);
        $this->assertEquals(['status' => 'pending'], $log->old_values);
        $this->assertEquals(['status' => 'captured'], $log->new_values);
        $this->assertEquals(['source' => 'test'], $log->metadata);
    }

    public function test_audit_log_has_auditable_morphed_relationship(): void
    {
        $session = PaymentSession::factory()->create();
        $log = PrahsysAuditLog::factory()->create([
            'auditable_type' => PaymentSession::class,
            'auditable_id' => $session->id,
        ]);

        $this->assertInstanceOf(PaymentSession::class, $log->auditable);
        $this->assertEquals($session->id, $log->auditable->id);
    }

    public function test_audit_log_scope_for_auditable_filters_correctly(): void
    {
        $session = PaymentSession::factory()->create();
        
        // Create logs for this session
        AuditLog::factory()->count(3)->create([
            'auditable_type' => PaymentSession::class,
            'auditable_id' => $session->id,
        ]);
        
        // Create logs for another session
        $otherSession = PaymentSession::factory()->create();
        AuditLog::factory()->count(2)->create([
            'auditable_type' => PaymentSession::class,
            'auditable_id' => $otherSession->id,
        ]);

        $logs = PrahsysAuditLog::forAuditable($session)->get();

        $this->assertEquals(3, $logs->count());
        $logs->each(function ($log) use ($session) {
            $this->assertEquals(PaymentSession::class, $log->auditable_type);
            $this->assertEquals($session->id, $log->auditable_id);
        });
    }

    public function test_audit_log_scope_for_event_type_filters_correctly(): void
    {
        AuditLog::factory()->count(3)->create(['event_type' => 'created']);
        AuditLog::factory()->count(2)->create(['event_type' => 'updated']);
        AuditLog::factory()->count(1)->create(['event_type' => 'deleted']);

        $createdLogs = AuditLog::forEventType('created')->get();
        $updatedLogs = AuditLog::forEventType('updated')->get();
        $deletedLogs = AuditLog::forEventType('deleted')->get();

        $this->assertEquals(3, $createdLogs->count());
        $this->assertEquals(2, $updatedLogs->count());
        $this->assertEquals(1, $deletedLogs->count());
        
        $createdLogs->each(function ($log) {
            $this->assertEquals('created', $log->event_type);
        });
    }

    public function test_audit_log_scope_recent_orders_by_created_at_desc(): void
    {
        // Create logs with different timestamps
        $oldLog = AuditLog::factory()->create(['created_at' => now()->subHours(2)]);
        $newLog = AuditLog::factory()->create(['created_at' => now()]);
        $middleLog = AuditLog::factory()->create(['created_at' => now()->subHour()]);

        $recentLogs = AuditLog::recent()->get();

        $this->assertEquals($newLog->id, $recentLogs->first()->id);
        $this->assertEquals($middleLog->id, $recentLogs->skip(1)->first()->id);
        $this->assertEquals($oldLog->id, $recentLogs->skip(2)->first()->id);
    }

    public function test_audit_log_scope_recent_with_limit(): void
    {
        AuditLog::factory()->count(5)->create();

        $recentLogs = AuditLog::recent(2)->get();

        $this->assertEquals(2, $recentLogs->count());
    }

    public function test_audit_log_factory_creates_valid_instances(): void
    {
        $log = AuditLog::factory()->create();

        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertEquals(PaymentSession::class, $log->auditable_type);
        $this->assertNotNull($log->auditable_id);
        $this->assertNotNull($log->event_type);
        $this->assertIsArray($log->old_values);
        $this->assertIsArray($log->new_values);
        $this->assertIsArray($log->metadata);
    }

    public function test_audit_log_factory_created_state(): void
    {
        $log = AuditLog::factory()->created()->create();

        $this->assertEquals('created', $log->event_type);
        $this->assertEmpty($log->old_values);
        $this->assertNotEmpty($log->new_values);
        $this->assertArrayHasKey('session_id', $log->new_values);
    }

    public function test_audit_log_factory_updated_state(): void
    {
        $log = AuditLog::factory()->updated()->create();

        $this->assertEquals('updated', $log->event_type);
        $this->assertNotEmpty($log->old_values);
        $this->assertNotEmpty($log->new_values);
        $this->assertArrayHasKey('status', $log->old_values);
        $this->assertArrayHasKey('status', $log->new_values);
    }

    public function test_audit_log_factory_deleted_state(): void
    {
        $log = AuditLog::factory()->deleted()->create();

        $this->assertEquals('deleted', $log->event_type);
        $this->assertNotEmpty($log->old_values);
        $this->assertEmpty($log->new_values);
    }

    public function test_audit_log_factory_payment_processed_state(): void
    {
        $log = AuditLog::factory()->paymentProcessed()->create();

        $this->assertEquals('payment_processed', $log->event_type);
        $this->assertArrayHasKey('status', $log->metadata);
        $this->assertArrayHasKey('gateway_response', $log->metadata);
        $this->assertArrayHasKey('timestamp', $log->metadata);
    }

    public function test_audit_log_factory_webhook_processed_state(): void
    {
        $log = AuditLog::factory()->webhookProcessed()->create();

        $this->assertEquals('webhook_processed', $log->event_type);
        $this->assertArrayHasKey('webhook_event_type', $log->metadata);
        $this->assertArrayHasKey('payload_summary', $log->metadata);
        $this->assertArrayHasKey('timestamp', $log->metadata);
    }

    public function test_audit_log_factory_with_user_state(): void
    {
        $log = AuditLog::factory()->withUser()->create();

        $this->assertNotNull($log->user_id);
        $this->assertEquals('App\\Models\\User', $log->user_type);
    }

    public function test_audit_log_without_user_has_null_user_fields(): void
    {
        $log = AuditLog::factory()->create();

        $this->assertNull($log->user_id);
        $this->assertNull($log->user_type);
    }
}