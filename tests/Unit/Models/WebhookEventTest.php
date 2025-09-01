<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Tests\Unit\Models;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use Prahsys\LaravelClerk\Models\PaymentSession;
use Prahsys\LaravelClerk\Models\WebhookEvent;
use Prahsys\LaravelClerk\ClerkServiceProvider;

class WebhookEventTest extends TestCase
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

    public function test_webhook_event_has_correct_table_name(): void
    {
        $event = new WebhookEvent();
        
        $this->assertEquals('clerk_webhook_events', $event->getTable());
    }

    public function test_webhook_event_has_correct_fillable_attributes(): void
    {
        $expected = [
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

        $event = new WebhookEvent();
        
        $this->assertEquals($expected, $event->getFillable());
    }

    public function test_webhook_event_casts_correctly(): void
    {
        $event = WebhookEvent::factory()->create([
            'payload' => ['test' => 'value'],
            'processed_at' => '2024-01-01 12:00:00',
            'failed_at' => '2024-01-01 12:05:00',
        ]);

        $this->assertIsArray($event->payload);
        $this->assertEquals(['test' => 'value'], $event->payload);
        $this->assertInstanceOf(Carbon::class, $event->processed_at);
        $this->assertInstanceOf(Carbon::class, $event->failed_at);
    }

    public function test_webhook_event_belongs_to_payment_session(): void
    {
        $session = PaymentSession::factory()->create();
        $event = WebhookEvent::factory()->create([
            'payment_session_id' => $session->id,
        ]);

        $this->assertInstanceOf(PaymentSession::class, $event->paymentSession);
        $this->assertEquals($session->id, $event->paymentSession->id);
    }

    public function test_is_processed_returns_true_for_processed_status(): void
    {
        $event = WebhookEvent::factory()->processed()->create();

        $this->assertTrue($event->isProcessed());
        $this->assertFalse($event->isPending());
        $this->assertFalse($event->isFailed());
    }

    public function test_is_pending_returns_true_for_pending_status(): void
    {
        $event = WebhookEvent::factory()->create([
            'status' => 'pending',
        ]);

        $this->assertTrue($event->isPending());
        $this->assertFalse($event->isProcessed());
        $this->assertFalse($event->isFailed());
    }

    public function test_is_failed_returns_true_for_failed_status(): void
    {
        $event = WebhookEvent::factory()->failed()->create();

        $this->assertTrue($event->isFailed());
        $this->assertFalse($event->isProcessed());
        $this->assertFalse($event->isPending());
    }

    public function test_needs_retry_returns_true_when_failed_with_retry_count_less_than_max(): void
    {
        $event = WebhookEvent::factory()->failed()->create([
            'retry_count' => 2,
        ]);

        $this->assertTrue($event->needsRetry());
    }

    public function test_needs_retry_returns_false_when_retry_count_exceeds_max(): void
    {
        $event = WebhookEvent::factory()->failed()->create([
            'retry_count' => 5,
        ]);

        $this->assertFalse($event->needsRetry());
    }

    public function test_needs_retry_returns_false_when_processed(): void
    {
        $event = WebhookEvent::factory()->processed()->create([
            'retry_count' => 1,
        ]);

        $this->assertFalse($event->needsRetry());
    }

    public function test_needs_retry_returns_false_when_pending(): void
    {
        $event = WebhookEvent::factory()->create([
            'status' => 'pending',
            'retry_count' => 1,
        ]);

        $this->assertFalse($event->needsRetry());
    }

    public function test_increment_retry_count_increases_count(): void
    {
        $event = WebhookEvent::factory()->create([
            'retry_count' => 0,
        ]);

        $event->incrementRetryCount();

        $this->assertEquals(1, $event->retry_count);
    }

    public function test_increment_retry_count_saves_to_database(): void
    {
        $event = WebhookEvent::factory()->create([
            'retry_count' => 0,
        ]);

        $event->incrementRetryCount();

        $this->assertDatabaseHas('clerk_webhook_events', [
            'id' => $event->id,
            'retry_count' => 1,
        ]);
    }

    public function test_mark_as_processed_updates_status_and_timestamp(): void
    {
        $event = WebhookEvent::factory()->create([
            'status' => 'pending',
            'processed_at' => null,
        ]);

        $event->markAsProcessed();

        $this->assertEquals('processed', $event->status);
        $this->assertNotNull($event->processed_at);
        $this->assertTrue($event->isProcessed());
    }

    public function test_mark_as_failed_updates_status_and_error_details(): void
    {
        $event = WebhookEvent::factory()->create([
            'status' => 'pending',
            'failed_at' => null,
            'error_message' => null,
        ]);

        $errorMessage = 'Webhook processing failed';
        $event->markAsFailed($errorMessage);

        $this->assertEquals('failed', $event->status);
        $this->assertEquals($errorMessage, $event->error_message);
        $this->assertNotNull($event->failed_at);
        $this->assertTrue($event->isFailed());
    }

    public function test_soft_deletes_are_enabled(): void
    {
        $event = WebhookEvent::factory()->create();
        $eventId = $event->id;

        $event->delete();

        $this->assertSoftDeleted('clerk_webhook_events', ['id' => $eventId]);
        $this->assertNotNull(WebhookEvent::withTrashed()->find($eventId)->deleted_at);
    }

    public function test_webhook_event_factory_creates_valid_instances(): void
    {
        $event = WebhookEvent::factory()->create();

        $this->assertInstanceOf(WebhookEvent::class, $event);
        $this->assertNotNull($event->event_id);
        $this->assertNotNull($event->event_type);
        $this->assertIsArray($event->payload);
        $this->assertEquals(0, $event->retry_count);
    }

    public function test_webhook_event_factory_processed_state(): void
    {
        $event = WebhookEvent::factory()->processed()->create();

        $this->assertTrue($event->isProcessed());
        $this->assertNotNull($event->processed_at);
    }

    public function test_webhook_event_factory_failed_state(): void
    {
        $event = WebhookEvent::factory()->failed()->create();

        $this->assertTrue($event->isFailed());
        $this->assertNotNull($event->failed_at);
        $this->assertNotNull($event->error_message);
        $this->assertGreaterThan(0, $event->retry_count);
    }

    public function test_webhook_event_factory_payment_captured_state(): void
    {
        $event = WebhookEvent::factory()->paymentCaptured()->create();

        $this->assertEquals('payment.captured', $event->event_type);
        $this->assertArrayHasKey('data', $event->payload);
        $this->assertEquals('captured', $event->payload['data']['object']['status']);
    }

    public function test_webhook_event_factory_payment_failed_state(): void
    {
        $event = WebhookEvent::factory()->paymentFailed()->create();

        $this->assertEquals('payment.failed', $event->event_type);
        $this->assertArrayHasKey('data', $event->payload);
        $this->assertEquals('failed', $event->payload['data']['object']['status']);
        $this->assertArrayHasKey('failure_reason', $event->payload['data']['object']);
    }
}