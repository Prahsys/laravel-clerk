<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Tests\Unit\Models;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use Prahsys\LaravelClerk\Models\AuditLog;
use Prahsys\LaravelClerk\Models\PaymentSession;
use Prahsys\LaravelClerk\Models\PaymentTransaction;
use Prahsys\LaravelClerk\Models\WebhookEvent;
use Prahsys\LaravelClerk\ClerkServiceProvider;

class PaymentSessionTest extends TestCase
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

    public function test_payment_session_has_correct_table_name(): void
    {
        $session = new PaymentSession();
        
        $this->assertEquals('clerk_payment_sessions', $session->getTable());
    }

    public function test_payment_session_has_correct_fillable_attributes(): void
    {
        $expected = [
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

        $session = new PaymentSession();
        
        $this->assertEquals($expected, $session->getFillable());
    }

    public function test_payment_session_casts_correctly(): void
    {
        $session = PaymentSession::factory()->create([
            'amount' => '99.99',
            'portal_configuration' => ['operation' => 'PAY'],
            'metadata' => ['test' => 'value'],
            'expires_at' => '2024-01-01 12:00:00',
            'completed_at' => '2024-01-01 13:00:00',
        ]);

        $this->assertIsFloat($session->amount);
        $this->assertEquals(99.99, $session->amount);
        $this->assertIsArray($session->portal_configuration);
        $this->assertIsArray($session->metadata);
        $this->assertInstanceOf(Carbon::class, $session->expires_at);
        $this->assertInstanceOf(Carbon::class, $session->completed_at);
    }

    public function test_payment_session_has_transactions_relationship(): void
    {
        $session = PaymentSession::factory()->create();
        $transaction = PaymentTransaction::factory()->create([
            'payment_session_id' => $session->id,
        ]);

        $this->assertTrue($session->transactions()->exists());
        $this->assertEquals(1, $session->transactions()->count());
        $this->assertEquals($transaction->id, $session->transactions->first()->id);
    }

    public function test_payment_session_has_webhook_events_relationship(): void
    {
        $session = PaymentSession::factory()->create();
        $webhookEvent = WebhookEvent::factory()->create([
            'payment_session_id' => $session->id,
        ]);

        $this->assertTrue($session->webhookEvents()->exists());
        $this->assertEquals(1, $session->webhookEvents()->count());
        $this->assertEquals($webhookEvent->id, $session->webhookEvents->first()->id);
    }

    public function test_payment_session_has_audit_logs_relationship(): void
    {
        $session = PaymentSession::factory()->create();
        
        // The HasAuditLog trait should automatically create audit logs
        $this->assertTrue($session->auditLogs()->exists());
        $this->assertEquals('created', $session->auditLogs->first()->event_type);
    }

    public function test_is_expired_returns_true_when_expired(): void
    {
        $session = PaymentSession::factory()->create([
            'expires_at' => now()->subHour(),
        ]);

        $this->assertTrue($session->isExpired());
    }

    public function test_is_expired_returns_false_when_not_expired(): void
    {
        $session = PaymentSession::factory()->create([
            'expires_at' => now()->addHour(),
        ]);

        $this->assertFalse($session->isExpired());
    }

    public function test_is_expired_returns_false_when_expires_at_is_null(): void
    {
        $session = PaymentSession::factory()->create([
            'expires_at' => null,
        ]);

        $this->assertFalse($session->isExpired());
    }

    public function test_is_completed_returns_true_for_captured_status(): void
    {
        $session = PaymentSession::factory()->create([
            'status' => 'captured',
        ]);

        $this->assertTrue($session->isCompleted());
    }

    public function test_is_completed_returns_true_for_authorized_status(): void
    {
        $session = PaymentSession::factory()->create([
            'status' => 'authorized',
        ]);

        $this->assertTrue($session->isCompleted());
    }

    public function test_is_completed_returns_false_for_other_statuses(): void
    {
        $statuses = ['pending', 'failed', 'expired', 'created'];
        
        foreach ($statuses as $status) {
            $session = PaymentSession::factory()->create([
                'status' => $status,
            ]);

            $this->assertFalse($session->isCompleted(), "Failed for status: {$status}");
        }
    }

    public function test_is_pending_returns_true_for_pending_status(): void
    {
        $session = PaymentSession::factory()->create([
            'status' => 'pending',
        ]);

        $this->assertTrue($session->isPending());
    }

    public function test_is_pending_returns_false_for_other_statuses(): void
    {
        $session = PaymentSession::factory()->create([
            'status' => 'captured',
        ]);

        $this->assertFalse($session->isPending());
    }

    public function test_is_failed_returns_true_for_failed_status(): void
    {
        $session = PaymentSession::factory()->create([
            'status' => 'failed',
        ]);

        $this->assertTrue($session->isFailed());
    }

    public function test_is_failed_returns_false_for_other_statuses(): void
    {
        $session = PaymentSession::factory()->create([
            'status' => 'captured',
        ]);

        $this->assertFalse($session->isFailed());
    }

    public function test_is_portal_session_returns_true_when_portal_configuration_exists(): void
    {
        $session = PaymentSession::factory()->portal()->create();

        $this->assertTrue($session->isPortalSession());
    }

    public function test_is_portal_session_returns_false_when_portal_configuration_is_empty(): void
    {
        $session = PaymentSession::factory()->create([
            'portal_configuration' => [],
        ]);

        $this->assertFalse($session->isPortalSession());
    }

    public function test_is_portal_session_returns_false_when_portal_configuration_is_null(): void
    {
        $session = PaymentSession::factory()->create([
            'portal_configuration' => null,
        ]);

        $this->assertFalse($session->isPortalSession());
    }

    public function test_soft_deletes_are_enabled(): void
    {
        $session = PaymentSession::factory()->create();
        $sessionId = $session->id;

        $session->delete();

        $this->assertSoftDeleted('clerk_payment_sessions', ['id' => $sessionId]);
        $this->assertNotNull(PaymentSession::withTrashed()->find($sessionId)->deleted_at);
    }

    public function test_payment_session_factory_creates_valid_instances(): void
    {
        $session = PaymentSession::factory()->create();

        $this->assertInstanceOf(PaymentSession::class, $session);
        $this->assertNotNull($session->session_id);
        $this->assertNotNull($session->payment_id);
        $this->assertNotNull($session->amount);
        $this->assertEquals('USD', $session->currency);
    }

    public function test_payment_session_factory_portal_state(): void
    {
        $session = PaymentSession::factory()->portal()->create();

        $this->assertTrue($session->isPortalSession());
        $this->assertArrayHasKey('operation', $session->portal_configuration);
        $this->assertNotNull($session->success_indicator);
    }

    public function test_payment_session_factory_completed_state(): void
    {
        $session = PaymentSession::factory()->completed()->create();

        $this->assertTrue($session->isCompleted());
        $this->assertNotNull($session->completed_at);
    }

    public function test_payment_session_factory_expired_state(): void
    {
        $session = PaymentSession::factory()->expired()->create();

        $this->assertTrue($session->isExpired());
    }

    public function test_payment_session_factory_card_present_state(): void
    {
        $session = PaymentSession::factory()->cardPresent()->create();

        $this->assertEquals('card_present', $session->payment_method);
        $this->assertArrayHasKey('terminal_id', $session->metadata);
        $this->assertArrayHasKey('receipt_number', $session->metadata);
    }
}