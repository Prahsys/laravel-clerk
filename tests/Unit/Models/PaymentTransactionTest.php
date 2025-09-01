<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Tests\Unit\Models;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use Prahsys\LaravelClerk\Models\PaymentSession;
use Prahsys\LaravelClerk\Models\PaymentTransaction;
use Prahsys\LaravelClerk\ClerkServiceProvider;

class PaymentTransactionTest extends TestCase
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

    public function test_payment_transaction_has_correct_table_name(): void
    {
        $transaction = new PaymentTransaction();
        
        $this->assertEquals('clerk_payment_transactions', $transaction->getTable());
    }

    public function test_payment_transaction_has_correct_fillable_attributes(): void
    {
        $expected = [
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

        $transaction = new PaymentTransaction();
        
        $this->assertEquals($expected, $transaction->getFillable());
    }

    public function test_payment_transaction_casts_correctly(): void
    {
        $transaction = PaymentTransaction::factory()->create([
            'amount' => '99.99',
            'gateway_response' => ['code' => '00'],
            'card_data' => ['last4' => '1234'],
            'customer_data' => ['name' => 'John Doe'],
            'processed_at' => '2024-01-01 12:00:00',
            'captured_at' => '2024-01-01 12:05:00',
            'refunded_at' => '2024-01-01 12:10:00',
            'voided_at' => '2024-01-01 12:15:00',
        ]);

        $this->assertIsFloat($transaction->amount);
        $this->assertEquals(99.99, $transaction->amount);
        $this->assertIsArray($transaction->gateway_response);
        $this->assertIsArray($transaction->card_data);
        $this->assertIsArray($transaction->customer_data);
        $this->assertInstanceOf(Carbon::class, $transaction->processed_at);
        $this->assertInstanceOf(Carbon::class, $transaction->captured_at);
        $this->assertInstanceOf(Carbon::class, $transaction->refunded_at);
        $this->assertInstanceOf(Carbon::class, $transaction->voided_at);
    }

    public function test_payment_transaction_belongs_to_payment_session(): void
    {
        $session = PaymentSession::factory()->create();
        $transaction = PaymentTransaction::factory()->create([
            'payment_session_id' => $session->id,
        ]);

        $this->assertInstanceOf(PaymentSession::class, $transaction->paymentSession);
        $this->assertEquals($session->id, $transaction->paymentSession->id);
    }

    public function test_payment_transaction_has_audit_logs_relationship(): void
    {
        $transaction = PaymentTransaction::factory()->create();
        
        // The HasAuditLog trait should automatically create audit logs
        $this->assertTrue($transaction->auditLogs()->exists());
        $this->assertEquals('created', $transaction->auditLogs->first()->event_type);
    }

    public function test_is_payment_returns_true_for_payment_type(): void
    {
        $transaction = PaymentTransaction::factory()->create([
            'type' => 'payment',
        ]);

        $this->assertTrue($transaction->isPayment());
        $this->assertFalse($transaction->isCapture());
        $this->assertFalse($transaction->isRefund());
        $this->assertFalse($transaction->isVoid());
    }

    public function test_is_capture_returns_true_for_capture_type(): void
    {
        $transaction = PaymentTransaction::factory()->capture()->create();

        $this->assertTrue($transaction->isCapture());
        $this->assertFalse($transaction->isPayment());
        $this->assertFalse($transaction->isRefund());
        $this->assertFalse($transaction->isVoid());
    }

    public function test_is_refund_returns_true_for_refund_type(): void
    {
        $transaction = PaymentTransaction::factory()->refund()->create();

        $this->assertTrue($transaction->isRefund());
        $this->assertFalse($transaction->isPayment());
        $this->assertFalse($transaction->isCapture());
        $this->assertFalse($transaction->isVoid());
    }

    public function test_is_void_returns_true_for_void_type(): void
    {
        $transaction = PaymentTransaction::factory()->void()->create();

        $this->assertTrue($transaction->isVoid());
        $this->assertFalse($transaction->isPayment());
        $this->assertFalse($transaction->isCapture());
        $this->assertFalse($transaction->isRefund());
    }

    public function test_is_successful_returns_true_for_successful_statuses(): void
    {
        $successfulStatuses = ['captured', 'authorized', 'completed'];
        
        foreach ($successfulStatuses as $status) {
            $transaction = PaymentTransaction::factory()->create([
                'status' => $status,
            ]);

            $this->assertTrue($transaction->isSuccessful(), "Failed for status: {$status}");
            $this->assertFalse($transaction->isPending());
            $this->assertFalse($transaction->isFailed());
        }
    }

    public function test_is_pending_returns_true_for_pending_status(): void
    {
        $transaction = PaymentTransaction::factory()->pending()->create();

        $this->assertTrue($transaction->isPending());
        $this->assertFalse($transaction->isSuccessful());
        $this->assertFalse($transaction->isFailed());
    }

    public function test_is_failed_returns_true_for_failed_status(): void
    {
        $transaction = PaymentTransaction::factory()->failed()->create();

        $this->assertTrue($transaction->isFailed());
        $this->assertFalse($transaction->isSuccessful());
        $this->assertFalse($transaction->isPending());
    }

    public function test_soft_deletes_are_enabled(): void
    {
        $transaction = PaymentTransaction::factory()->create();
        $transactionId = $transaction->id;

        $transaction->delete();

        $this->assertSoftDeleted('clerk_payment_transactions', ['id' => $transactionId]);
        $this->assertNotNull(PaymentTransaction::withTrashed()->find($transactionId)->deleted_at);
    }

    public function test_payment_transaction_factory_creates_valid_instances(): void
    {
        $transaction = PaymentTransaction::factory()->create();

        $this->assertInstanceOf(PaymentTransaction::class, $transaction);
        $this->assertNotNull($transaction->transaction_id);
        $this->assertEquals('payment', $transaction->type);
        $this->assertNotNull($transaction->amount);
        $this->assertEquals('USD', $transaction->currency);
    }

    public function test_payment_transaction_factory_capture_state(): void
    {
        $transaction = PaymentTransaction::factory()->capture()->create();

        $this->assertTrue($transaction->isCapture());
        $this->assertEquals('captured', $transaction->status);
        $this->assertNotNull($transaction->captured_at);
    }

    public function test_payment_transaction_factory_refund_state(): void
    {
        $transaction = PaymentTransaction::factory()->refund()->create();

        $this->assertTrue($transaction->isRefund());
        $this->assertEquals('completed', $transaction->status);
        $this->assertNotNull($transaction->refunded_at);
    }

    public function test_payment_transaction_factory_void_state(): void
    {
        $transaction = PaymentTransaction::factory()->void()->create();

        $this->assertTrue($transaction->isVoid());
        $this->assertEquals('completed', $transaction->status);
        $this->assertNotNull($transaction->voided_at);
    }

    public function test_payment_transaction_factory_failed_state(): void
    {
        $transaction = PaymentTransaction::factory()->failed()->create();

        $this->assertTrue($transaction->isFailed());
        $this->assertArrayHasKey('response_code', $transaction->gateway_response);
        $this->assertEquals('05', $transaction->gateway_response['response_code']);
    }

    public function test_payment_transaction_factory_successful_state(): void
    {
        $transaction = PaymentTransaction::factory()->successful()->create();

        $this->assertTrue($transaction->isSuccessful());
        $this->assertNotNull($transaction->processed_at);
        $this->assertNotNull($transaction->captured_at);
    }

    public function test_payment_transaction_factory_pending_state(): void
    {
        $transaction = PaymentTransaction::factory()->pending()->create();

        $this->assertTrue($transaction->isPending());
        $this->assertNull($transaction->processed_at);
        $this->assertNull($transaction->captured_at);
    }

    public function test_payment_transaction_factory_card_present_state(): void
    {
        $transaction = PaymentTransaction::factory()->cardPresent()->create();

        $this->assertArrayHasKey('terminal_id', $transaction->gateway_response);
        $this->assertArrayHasKey('entry_method', $transaction->gateway_response);
        $this->assertEquals('chip_pin', $transaction->gateway_response['entry_method']);
        $this->assertArrayHasKey('entry_method', $transaction->card_data);
    }
}