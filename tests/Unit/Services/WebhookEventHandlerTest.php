<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Config;
use Prahsys\LaravelClerk\Services\WebhookEventHandler;
use Prahsys\LaravelClerk\Services\PaymentSessionManager;
use Prahsys\LaravelClerk\Models\PaymentSession;
use Prahsys\LaravelClerk\Models\WebhookEvent;
use Prahsys\LaravelClerk\Events\WebhookReceived;
use Prahsys\LaravelClerk\Exceptions\WebhookVerificationException;

class WebhookEventHandlerTest extends TestCase
{
    protected WebhookEventHandler $handler;
    protected MockInterface $sessionManager;
    protected MockInterface $request;
    protected MockInterface $webhookEvent;
    protected MockInterface $paymentSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionManager = Mockery::mock(PaymentSessionManager::class);
        $this->handler = new WebhookEventHandler($this->sessionManager);
        
        $this->request = Mockery::mock(Request::class);
        $this->webhookEvent = Mockery::mock(WebhookEvent::class);
        $this->paymentSession = Mockery::mock(PaymentSession::class);

        // Mock facades
        Log::spy();
        Event::spy();
        Config::spy();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_handle_webhook_with_valid_signature()
    {
        // Arrange
        $payload = [
            'id' => 'evt_123',
            'type' => 'payment.captured',
            'data' => ['object' => ['session_id' => 'session_123']]
        ];
        $signature = 'prahsys_validSignature123';
        $content = json_encode($payload);

        $this->request->shouldReceive('all')->once()->andReturn($payload);
        $this->request->shouldReceive('header')
            ->with('Prahsys-Signature')
            ->once()
            ->andReturn($signature);
        $this->request->shouldReceive('getContent')->once()->andReturn($content);

        // Mock signature verification
        Config::shouldReceive('get')
            ->with('clerk.webhooks.secret')
            ->once()
            ->andReturn('webhook_secret');

        // Mock webhook event creation
        WebhookEvent::shouldReceive('create')
            ->once()
            ->with([
                'event_id' => 'evt_123',
                'event_type' => 'payment.captured',
                'status' => 'pending',
                'payload' => $payload,
                'signature' => $signature,
            ])
            ->andReturn($this->webhookEvent);

        // Mock payment session lookup
        PaymentSession::shouldReceive('where')
            ->with('session_id', 'session_123')
            ->once()
            ->andReturnSelf();
        PaymentSession::shouldReceive('first')
            ->once()
            ->andReturn($this->paymentSession);

        $this->paymentSession->shouldReceive('getAttribute')
            ->with('id')
            ->once()
            ->andReturn(1);

        $this->webhookEvent->shouldReceive('update')
            ->with(['payment_session_id' => 1])
            ->once();

        // Act
        $result = $this->handler->handleWebhook($this->request);

        // Assert
        $this->assertSame($this->webhookEvent, $result);
        Event::shouldHaveReceived('dispatch')->with(Mockery::type(WebhookReceived::class))->once();
    }

    /** @test */
    public function it_throws_exception_for_invalid_signature()
    {
        // Arrange
        $payload = ['id' => 'evt_123', 'type' => 'payment.captured'];
        $signature = 'prahsys_invalidSignature';
        $content = json_encode($payload);

        $this->request->shouldReceive('all')->once()->andReturn($payload);
        $this->request->shouldReceive('header')
            ->with('Prahsys-Signature')
            ->once()
            ->andReturn($signature);
        $this->request->shouldReceive('getContent')->once()->andReturn($content);

        Config::shouldReceive('get')
            ->with('clerk.webhooks.secret')
            ->once()
            ->andReturn('webhook_secret');

        // Expect
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('Invalid webhook signature');

        // Act
        $this->handler->handleWebhook($this->request);
    }

    /** @test */
    public function it_handles_webhook_without_signature_when_no_secret_configured()
    {
        // Arrange
        $payload = ['id' => 'evt_123', 'type' => 'payment.captured'];
        $content = json_encode($payload);

        $this->request->shouldReceive('all')->once()->andReturn($payload);
        $this->request->shouldReceive('header')
            ->with('Prahsys-Signature')
            ->once()
            ->andReturn(null);
        $this->request->shouldReceive('getContent')->once()->andReturn($content);

        Config::shouldReceive('get')
            ->with('clerk.webhooks.secret')
            ->once()
            ->andReturn(null);

        // Expect
        $this->expectException(WebhookVerificationException::class);

        // Act
        $this->handler->handleWebhook($this->request);
    }

    /** @test */
    public function it_processes_payment_captured_event_successfully()
    {
        // Arrange
        $payload = [
            'data' => [
                'object' => [
                    'id' => 'payment_123',
                    'amount' => 2500,
                    'currency' => 'USD',
                    'status' => 'captured'
                ]
            ]
        ];

        $this->webhookEvent->shouldReceive('getAttribute')
            ->with('event_type')
            ->andReturn('payment.captured');
        $this->webhookEvent->shouldReceive('getAttribute')
            ->with('payload')
            ->andReturn($payload);
        $this->webhookEvent->shouldReceive('getAttribute')
            ->with('paymentSession')
            ->andReturn($this->paymentSession);
        $this->webhookEvent->shouldReceive('getAttribute')
            ->with('event_id')
            ->andReturn('evt_123');

        $this->paymentSession->shouldReceive('update')
            ->with([
                'status' => 'captured',
                'completed_at' => Mockery::type(\Carbon\Carbon::class),
            ])
            ->once();

        $this->paymentSession->shouldReceive('getAttribute')
            ->with('amount')
            ->andReturn(2500);
        $this->paymentSession->shouldReceive('getAttribute')
            ->with('currency')
            ->andReturn('USD');

        // Mock transactions relationship
        $transactionsRelation = Mockery::mock();
        $transactionsRelation->shouldReceive('create')
            ->once()
            ->with(Mockery::type('array'));

        $this->paymentSession->shouldReceive('transactions')
            ->once()
            ->andReturn($transactionsRelation);

        $this->webhookEvent->shouldReceive('markAsProcessed')->once();

        // Act
        $this->handler->processWebhookEvent($this->webhookEvent);

        // Assert
        Log::shouldHaveReceived('info')
            ->with('Webhook event processed successfully', Mockery::type('array'))
            ->once();
    }

    /** @test */
    public function it_processes_payment_failed_event_successfully()
    {
        // Arrange
        $payload = [
            'data' => [
                'object' => [
                    'id' => 'payment_123',
                    'amount' => 2500,
                    'currency' => 'USD',
                    'status' => 'failed'
                ]
            ]
        ];

        $this->webhookEvent->shouldReceive('getAttribute')
            ->with('event_type')
            ->andReturn('payment.failed');
        $this->webhookEvent->shouldReceive('getAttribute')
            ->with('payload')
            ->andReturn($payload);
        $this->webhookEvent->shouldReceive('getAttribute')
            ->with('paymentSession')
            ->andReturn($this->paymentSession);
        $this->webhookEvent->shouldReceive('getAttribute')
            ->with('event_id')
            ->andReturn('evt_123');

        $this->paymentSession->shouldReceive('update')
            ->with(['status' => 'failed'])
            ->once();

        $this->paymentSession->shouldReceive('getAttribute')
            ->with('amount')
            ->andReturn(2500);
        $this->paymentSession->shouldReceive('getAttribute')
            ->with('currency')
            ->andReturn('USD');

        // Mock transactions relationship
        $transactionsRelation = Mockery::mock();
        $transactionsRelation->shouldReceive('create')
            ->once()
            ->with(Mockery::type('array'));

        $this->paymentSession->shouldReceive('transactions')
            ->once()
            ->andReturn($transactionsRelation);

        $this->webhookEvent->shouldReceive('markAsProcessed')->once();

        // Act
        $this->handler->processWebhookEvent($this->webhookEvent);

        // Assert
        Log::shouldHaveReceived('info')
            ->with('Webhook event processed successfully', Mockery::type('array'))
            ->once();
    }

    /** @test */
    public function it_processes_payment_refunded_event_successfully()
    {
        // Arrange
        $payload = [
            'data' => [
                'object' => [
                    'id' => 'refund_123',
                    'amount' => 1000,
                    'currency' => 'USD',
                    'status' => 'completed'
                ]
            ]
        ];

        $this->webhookEvent->shouldReceive('getAttribute')
            ->with('event_type')
            ->andReturn('payment.refunded');
        $this->webhookEvent->shouldReceive('getAttribute')
            ->with('payload')
            ->andReturn($payload);
        $this->webhookEvent->shouldReceive('getAttribute')
            ->with('paymentSession')
            ->andReturn($this->paymentSession);
        $this->webhookEvent->shouldReceive('getAttribute')
            ->with('event_id')
            ->andReturn('evt_123');

        $this->paymentSession->shouldReceive('getAttribute')
            ->with('amount')
            ->andReturn(2500);
        $this->paymentSession->shouldReceive('getAttribute')
            ->with('currency')
            ->andReturn('USD');

        // Mock transactions relationship
        $transactionsRelation = Mockery::mock();
        $transactionsRelation->shouldReceive('create')
            ->once()
            ->with(Mockery::type('array'));

        $this->paymentSession->shouldReceive('transactions')
            ->once()
            ->andReturn($transactionsRelation);

        $this->webhookEvent->shouldReceive('markAsProcessed')->once();

        // Act
        $this->handler->processWebhookEvent($this->webhookEvent);

        // Assert
        Log::shouldHaveReceived('info')
            ->with('Webhook event processed successfully', Mockery::type('array'))
            ->once();
    }

    /** @test */
    public function it_handles_session_expired_event()
    {
        // Arrange
        $payload = ['data' => ['object' => ['session_id' => 'session_123']]];

        $this->webhookEvent->shouldReceive('getAttribute')
            ->with('event_type')
            ->andReturn('session.expired');
        $this->webhookEvent->shouldReceive('getAttribute')
            ->with('payload')
            ->andReturn($payload);
        $this->webhookEvent->shouldReceive('getAttribute')
            ->with('paymentSession')
            ->andReturn($this->paymentSession);
        $this->webhookEvent->shouldReceive('getAttribute')
            ->with('event_id')
            ->andReturn('evt_123');

        $this->paymentSession->shouldReceive('update')
            ->with(['status' => 'expired'])
            ->once();

        $this->webhookEvent->shouldReceive('markAsProcessed')->once();

        // Act
        $this->handler->processWebhookEvent($this->webhookEvent);

        // Assert
        Log::shouldHaveReceived('info')
            ->with('Webhook event processed successfully', Mockery::type('array'))
            ->once();
    }

    /** @test */
    public function it_logs_warning_for_unknown_event_types()
    {
        // Arrange
        $payload = ['data' => ['object' => []]];

        $this->webhookEvent->shouldReceive('getAttribute')
            ->with('event_type')
            ->andReturn('unknown.event');
        $this->webhookEvent->shouldReceive('getAttribute')
            ->with('payload')
            ->andReturn($payload);
        $this->webhookEvent->shouldReceive('getAttribute')
            ->with('event_id')
            ->andReturn('evt_123');

        $this->webhookEvent->shouldReceive('markAsProcessed')->once();

        // Act
        $this->handler->processWebhookEvent($this->webhookEvent);

        // Assert
        Log::shouldHaveReceived('warning')
            ->with('Unknown webhook event type', [
                'event_type' => 'unknown.event',
                'event_id' => 'evt_123',
            ])
            ->once();
    }

    /** @test */
    public function it_handles_webhook_processing_failure()
    {
        // Arrange
        $this->webhookEvent->shouldReceive('getAttribute')
            ->with('event_type')
            ->andReturn('payment.captured');
        $this->webhookEvent->shouldReceive('getAttribute')
            ->with('payload')
            ->andThrow(new \Exception('Database error'));
        $this->webhookEvent->shouldReceive('getAttribute')
            ->with('event_id')
            ->andReturn('evt_123');

        $this->webhookEvent->shouldReceive('markAsFailed')
            ->with('Database error')
            ->once();
        $this->webhookEvent->shouldReceive('incrementRetryCount')->once();
        $this->webhookEvent->shouldReceive('getAttribute')
            ->with('retry_count')
            ->andReturn(1);

        // Expect
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');

        // Act
        $this->handler->processWebhookEvent($this->webhookEvent);

        // Assert
        Log::shouldHaveReceived('error')
            ->with('Failed to process webhook event', Mockery::type('array'))
            ->once();
    }

    /** @test */
    public function it_can_retry_failed_webhooks()
    {
        // Arrange
        $failedEvents = collect([$this->webhookEvent]);

        WebhookEvent::shouldReceive('where')
            ->with('status', 'failed')
            ->once()
            ->andReturnSelf();
        WebhookEvent::shouldReceive('where')
            ->with('retry_count', '<', 3)
            ->once()
            ->andReturnSelf();
        WebhookEvent::shouldReceive('get')
            ->once()
            ->andReturn($failedEvents);

        Config::shouldReceive('get')
            ->with('clerk.webhooks.max_attempts', 3)
            ->once()
            ->andReturn(3);

        $this->webhookEvent->shouldReceive('getAttribute')
            ->with('event_type')
            ->andReturn('payment.captured');
        $this->webhookEvent->shouldReceive('getAttribute')
            ->with('payload')
            ->andReturn(['data' => ['object' => []]]);
        $this->webhookEvent->shouldReceive('getAttribute')
            ->with('paymentSession')
            ->andReturn(null);
        $this->webhookEvent->shouldReceive('getAttribute')
            ->with('event_id')
            ->andReturn('evt_123');

        $this->webhookEvent->shouldReceive('markAsProcessed')->once();

        // Act
        $result = $this->handler->retryFailedWebhooks();

        // Assert
        $this->assertEquals(1, $result);
    }

    /** @test */
    public function it_verifies_webhook_signature_correctly()
    {
        // Arrange
        $payload = json_encode(['test' => 'data']);
        $secret = 'webhook_secret';
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        $signature = 'prahsys_' . $expectedSignature;

        Config::shouldReceive('get')
            ->with('clerk.webhooks.secret')
            ->once()
            ->andReturn($secret);

        $this->request->shouldReceive('all')
            ->once()
            ->andReturn(['test' => 'data']);
        $this->request->shouldReceive('header')
            ->with('Prahsys-Signature')
            ->once()
            ->andReturn($signature);
        $this->request->shouldReceive('getContent')
            ->once()
            ->andReturn($payload);

        WebhookEvent::shouldReceive('create')
            ->once()
            ->andReturn($this->webhookEvent);

        // Act & Assert - Should not throw exception
        $result = $this->handler->handleWebhook($this->request);
        $this->assertSame($this->webhookEvent, $result);
    }

    /** @test */
    public function it_extracts_session_id_from_various_payload_structures()
    {
        // Test different payload structures using reflection
        $reflection = new \ReflectionClass($this->handler);
        $method = $reflection->getMethod('extractSessionId');
        $method->setAccessible(true);

        // Test nested structure
        $payload1 = ['data' => ['object' => ['session_id' => 'session_123']]];
        $result1 = $method->invoke($this->handler, $payload1);
        $this->assertEquals('session_123', $result1);

        // Test direct structure
        $payload2 = ['session_id' => 'session_456'];
        $result2 = $method->invoke($this->handler, $payload2);
        $this->assertEquals('session_456', $result2);

        // Test missing session ID
        $payload3 = ['data' => ['object' => []]];
        $result3 = $method->invoke($this->handler, $payload3);
        $this->assertNull($result3);
    }
}