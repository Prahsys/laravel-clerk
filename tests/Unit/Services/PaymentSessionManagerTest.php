<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Prahsys\LaravelClerk\Services\PaymentSessionManager;
use Prahsys\LaravelClerk\Services\PaymentService;
use Prahsys\LaravelClerk\Models\PaymentSession;
use Prahsys\LaravelClerk\Models\PaymentTransaction;
use Prahsys\LaravelClerk\Data\PaymentData;
use Prahsys\LaravelClerk\Data\PortalConfigurationData;
use Prahsys\LaravelClerk\Data\MerchantData;
use Prahsys\LaravelClerk\Data\SessionData;
use Saloon\Http\Response;

class PaymentSessionManagerTest extends TestCase
{
    protected PaymentSessionManager $manager;
    protected MockInterface $paymentService;
    protected MockInterface $response;
    protected MockInterface $paymentSession;
    protected MockInterface $paymentTransaction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentService = Mockery::mock(PaymentService::class);
        $this->manager = new PaymentSessionManager($this->paymentService);
        
        $this->response = Mockery::mock(Response::class);
        $this->paymentSession = Mockery::mock(PaymentSession::class);
        $this->paymentTransaction = Mockery::mock(PaymentTransaction::class);

        // Mock Config facade
        Config::spy();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_create_payment_session_successfully()
    {
        // Arrange
        $paymentId = 'payment_123';
        $amount = 25.50;
        $description = 'Test payment';
        $currency = 'USD';
        $metadata = ['order_id' => 'order_456'];

        $responseData = [
            'data' => [
                'id' => 'session_abc123',
                'portal' => [
                    'successIndicator' => 'success_indicator_123'
                ]
            ]
        ];

        $this->response->shouldReceive('successful')->once()->andReturn(true);
        $this->response->shouldReceive('json')->once()->andReturn($responseData);
        
        $this->paymentService->shouldReceive('createSession')
            ->once()
            ->with(
                Mockery::type(PaymentData::class),
                null
            )
            ->andReturn($this->response);

        Config::shouldReceive('get')
            ->with('clerk.api.merchant_id')
            ->once()
            ->andReturn('merchant_123');

        PaymentSession::shouldReceive('create')
            ->once()
            ->with([
                'session_id' => 'session_abc123',
                'payment_id' => $paymentId,
                'merchant_id' => 'merchant_123',
                'status' => 'created',
                'amount' => $amount,
                'currency' => $currency,
                'description' => $description,
                'portal_configuration' => null,
                'success_indicator' => 'success_indicator_123',
                'metadata' => $metadata,
                'expires_at' => Mockery::type(Carbon::class),
            ])
            ->andReturn($this->paymentSession);

        // Act
        $result = $this->manager->createSession($paymentId, $amount, $description, $currency, null, $metadata);

        // Assert
        $this->assertSame($this->paymentSession, $result);
    }

    /** @test */
    public function it_throws_exception_when_session_creation_fails()
    {
        // Arrange
        $this->response->shouldReceive('successful')->once()->andReturn(false);
        $this->response->shouldReceive('body')->once()->andReturn('API Error');
        
        $this->paymentService->shouldReceive('createSession')
            ->once()
            ->andReturn($this->response);

        // Expect
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to create payment session: API Error');

        // Act
        $this->manager->createSession('payment_123', 25.50, 'Test payment');
    }

    /** @test */
    public function it_can_create_portal_session_with_configuration()
    {
        // Arrange
        $paymentId = 'payment_123';
        $amount = 25.50;
        $description = 'Test payment';
        $returnUrl = 'https://example.com/success';
        $cancelUrl = 'https://example.com/cancel';
        $merchantName = 'Test Merchant';
        $merchantLogo = 'https://example.com/logo.png';

        $responseData = [
            'data' => [
                'id' => 'session_abc123',
                'portal' => [
                    'successIndicator' => 'success_indicator_123'
                ]
            ]
        ];

        $this->response->shouldReceive('successful')->once()->andReturn(true);
        $this->response->shouldReceive('json')->once()->andReturn($responseData);
        
        $this->paymentService->shouldReceive('createSession')
            ->once()
            ->with(
                Mockery::type(PaymentData::class),
                Mockery::type(PortalConfigurationData::class)
            )
            ->andReturn($this->response);

        Config::shouldReceive('get')
            ->with('clerk.api.merchant_id')
            ->once()
            ->andReturn('merchant_123');

        PaymentSession::shouldReceive('create')
            ->once()
            ->andReturn($this->paymentSession);

        // Act
        $result = $this->manager->createPortalSession(
            $paymentId,
            $amount,
            $description,
            $returnUrl,
            $cancelUrl,
            $merchantName,
            $merchantLogo
        );

        // Assert
        $this->assertSame($this->paymentSession, $result);
    }

    /** @test */
    public function it_can_sync_session_status_from_api()
    {
        // Arrange
        $sessionId = 'session_123';
        
        $this->paymentSession->shouldReceive('getAttribute')
            ->with('session_id')
            ->andReturn($sessionId);
        $this->paymentSession->shouldReceive('getAttribute')
            ->with('customer_email')
            ->andReturn(null);
        $this->paymentSession->shouldReceive('getAttribute')
            ->with('customer_name')
            ->andReturn(null);
        $this->paymentSession->shouldReceive('getAttribute')
            ->with('card_last4')
            ->andReturn(null);
        $this->paymentSession->shouldReceive('getAttribute')
            ->with('card_brand')
            ->andReturn(null);
        $this->paymentSession->shouldReceive('getAttribute')
            ->with('completed_at')
            ->andReturn(null);

        $responseData = [
            'data' => [
                'status' => 'CAPTURED',
                'customer' => [
                    'email' => 'john@example.com',
                    'name' => 'John Doe'
                ],
                'card' => [
                    'last4' => '4242',
                    'brand' => 'visa'
                ],
                'completedAt' => '2023-01-01T12:00:00Z'
            ]
        ];

        $this->response->shouldReceive('successful')->once()->andReturn(true);
        $this->response->shouldReceive('json')->once()->andReturn($responseData);
        
        $this->paymentService->shouldReceive('getSession')
            ->once()
            ->with($sessionId)
            ->andReturn($this->response);

        $this->paymentSession->shouldReceive('update')
            ->once()
            ->with([
                'status' => 'captured',
                'customer_email' => 'john@example.com',
                'customer_name' => 'John Doe',
                'card_last4' => '4242',
                'card_brand' => 'visa',
                'completed_at' => Mockery::type(Carbon::class),
            ]);

        $this->paymentSession->shouldReceive('fresh')
            ->once()
            ->andReturn($this->paymentSession);

        // Act
        $result = $this->manager->syncSessionStatus($this->paymentSession);

        // Assert
        $this->assertSame($this->paymentSession, $result);
    }

    /** @test */
    public function it_returns_unchanged_session_when_sync_fails()
    {
        // Arrange
        $sessionId = 'session_123';
        
        $this->paymentSession->shouldReceive('getAttribute')
            ->with('session_id')
            ->andReturn($sessionId);

        $this->response->shouldReceive('successful')->once()->andReturn(false);
        
        $this->paymentService->shouldReceive('getSession')
            ->once()
            ->with($sessionId)
            ->andReturn($this->response);

        // Act
        $result = $this->manager->syncSessionStatus($this->paymentSession);

        // Assert
        $this->assertSame($this->paymentSession, $result);
    }

    /** @test */
    public function it_can_process_payment_for_session()
    {
        // Arrange
        $this->paymentSession->shouldReceive('getAttribute')
            ->with('payment_id')
            ->andReturn('payment_123');
        $this->paymentSession->shouldReceive('getAttribute')
            ->with('amount')
            ->andReturn(25.50);
        $this->paymentSession->shouldReceive('getAttribute')
            ->with('description')
            ->andReturn('Test payment');
        $this->paymentSession->shouldReceive('getAttribute')
            ->with('currency')
            ->andReturn('USD');
        $this->paymentSession->shouldReceive('getAttribute')
            ->with('session_id')
            ->andReturn('session_123');
        $this->paymentSession->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn(1);

        $responseData = [
            'data' => [
                'id' => 'txn_456',
                'status' => 'CAPTURED',
                'payment' => [
                    'reference' => 'ref_789'
                ],
                'card' => [
                    'last4' => '4242',
                    'brand' => 'visa'
                ],
                'customer' => [
                    'email' => 'john@example.com',
                    'name' => 'John Doe'
                ],
                'processedAt' => '2023-01-01T12:00:00Z'
            ]
        ];

        $this->response->shouldReceive('json')->once()->andReturn($responseData);
        
        $this->paymentService->shouldReceive('processPayment')
            ->once()
            ->with(
                'payment_123',
                Mockery::type(PaymentData::class),
                Mockery::type(SessionData::class)
            )
            ->andReturn($this->response);

        PaymentTransaction::shouldReceive('create')
            ->once()
            ->with([
                'payment_session_id' => 1,
                'transaction_id' => 'txn_456',
                'type' => 'payment',
                'status' => 'captured',
                'amount' => 25.50,
                'currency' => 'USD',
                'reference' => 'ref_789',
                'gateway_response' => $responseData,
                'card_data' => ['last4' => '4242', 'brand' => 'visa'],
                'customer_data' => ['email' => 'john@example.com', 'name' => 'John Doe'],
                'processed_at' => Mockery::type(Carbon::class),
            ])
            ->andReturn($this->paymentTransaction);

        $this->paymentTransaction->shouldReceive('isSuccessful')
            ->once()
            ->andReturn(true);

        $this->paymentSession->shouldReceive('update')
            ->once()
            ->with([
                'status' => 'captured',
                'completed_at' => Mockery::type(Carbon::class),
            ]);

        // Act
        $result = $this->manager->processPayment($this->paymentSession);

        // Assert
        $this->assertSame($this->paymentTransaction, $result);
    }

    /** @test */
    public function it_can_verify_portal_payment_successfully()
    {
        // Arrange
        $resultIndicator = 'result_123';
        $successIndicator = 'success_123';

        $this->paymentSession->shouldReceive('isPortalSession')
            ->once()
            ->andReturn(true);
        $this->paymentSession->shouldReceive('getAttribute')
            ->with('success_indicator')
            ->andReturn($successIndicator);

        $this->paymentService->shouldReceive('verifyPortalPayment')
            ->once()
            ->with($successIndicator, $resultIndicator)
            ->andReturn(true);

        $this->paymentSession->shouldReceive('update')
            ->once()
            ->with([
                'result_indicator' => $resultIndicator,
                'status' => 'verified',
            ]);

        // Act
        $result = $this->manager->verifyPortalPayment($this->paymentSession, $resultIndicator);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_rejects_verification_for_non_portal_session()
    {
        // Arrange
        $this->paymentSession->shouldReceive('isPortalSession')
            ->once()
            ->andReturn(false);

        // Act
        $result = $this->manager->verifyPortalPayment($this->paymentSession, 'result_123');

        // Assert
        $this->assertFalse($result);
    }

    /** @test */
    public function it_rejects_verification_without_success_indicator()
    {
        // Arrange
        $this->paymentSession->shouldReceive('isPortalSession')
            ->once()
            ->andReturn(true);
        $this->paymentSession->shouldReceive('getAttribute')
            ->with('success_indicator')
            ->andReturn(null);

        // Act
        $result = $this->manager->verifyPortalPayment($this->paymentSession, 'result_123');

        // Assert
        $this->assertFalse($result);
    }

    /** @test */
    public function it_can_cleanup_expired_sessions()
    {
        // Arrange
        $expectedCount = 5;

        PaymentSession::shouldReceive('where')
            ->with('expires_at', '<', Mockery::type(Carbon::class))
            ->once()
            ->andReturnSelf();
        PaymentSession::shouldReceive('where')
            ->with('status', 'created')
            ->once()
            ->andReturnSelf();
        PaymentSession::shouldReceive('update')
            ->with(['status' => 'expired'])
            ->once()
            ->andReturn($expectedCount);

        // Act
        $result = $this->manager->cleanupExpiredSessions();

        // Assert
        $this->assertEquals($expectedCount, $result);
    }

    /** @test */
    public function it_can_get_session_statistics_without_date_filters()
    {
        // Arrange
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('count')
            ->times(6)
            ->andReturn(100, 75, 10, 5, 60, 40);
        $mockQuery->shouldReceive('whereIn')
            ->with('status', ['captured', 'authorized'])
            ->times(2)
            ->andReturnSelf();
        $mockQuery->shouldReceive('where')
            ->with('status', 'failed')
            ->once()
            ->andReturnSelf();
        $mockQuery->shouldReceive('where')
            ->with('status', 'expired')
            ->once()
            ->andReturnSelf();
        $mockQuery->shouldReceive('sum')
            ->with('amount')
            ->once()
            ->andReturn(12500.75);
        $mockQuery->shouldReceive('whereNotNull')
            ->with('portal_configuration')
            ->once()
            ->andReturnSelf();
        $mockQuery->shouldReceive('whereNull')
            ->with('portal_configuration')
            ->once()
            ->andReturnSelf();

        PaymentSession::shouldReceive('query')
            ->once()
            ->andReturn($mockQuery);

        // Act
        $result = $this->manager->getSessionStatistics();

        // Assert
        $expected = [
            'total_sessions' => 100,
            'completed_sessions' => 75,
            'failed_sessions' => 10,
            'expired_sessions' => 5,
            'total_amount' => 12500.75,
            'portal_sessions' => 60,
            'embedded_sessions' => 40,
        ];

        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function it_can_get_session_statistics_with_date_filters()
    {
        // Arrange
        $startDate = Carbon::parse('2023-01-01');
        $endDate = Carbon::parse('2023-12-31');

        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')
            ->with('created_at', '>=', $startDate)
            ->once()
            ->andReturnSelf();
        $mockQuery->shouldReceive('where')
            ->with('created_at', '<=', $endDate)
            ->once()
            ->andReturnSelf();
        $mockQuery->shouldReceive('count')
            ->times(6)
            ->andReturn(50, 35, 5, 2, 30, 20);
        $mockQuery->shouldReceive('whereIn')
            ->with('status', ['captured', 'authorized'])
            ->times(2)
            ->andReturnSelf();
        $mockQuery->shouldReceive('where')
            ->with('status', 'failed')
            ->once()
            ->andReturnSelf();
        $mockQuery->shouldReceive('where')
            ->with('status', 'expired')
            ->once()
            ->andReturnSelf();
        $mockQuery->shouldReceive('sum')
            ->with('amount')
            ->once()
            ->andReturn(6250.50);
        $mockQuery->shouldReceive('whereNotNull')
            ->with('portal_configuration')
            ->once()
            ->andReturnSelf();
        $mockQuery->shouldReceive('whereNull')
            ->with('portal_configuration')
            ->once()
            ->andReturnSelf();

        PaymentSession::shouldReceive('query')
            ->once()
            ->andReturn($mockQuery);

        // Act
        $result = $this->manager->getSessionStatistics($startDate, $endDate);

        // Assert
        $expected = [
            'total_sessions' => 50,
            'completed_sessions' => 35,
            'failed_sessions' => 5,
            'expired_sessions' => 2,
            'total_amount' => 6250.50,
            'portal_sessions' => 30,
            'embedded_sessions' => 20,
        ];

        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function it_can_get_session_statistics_with_only_start_date()
    {
        // Arrange
        $startDate = Carbon::parse('2023-06-01');

        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')
            ->with('created_at', '>=', $startDate)
            ->once()
            ->andReturnSelf();
        $mockQuery->shouldReceive('count')
            ->times(6)
            ->andReturn(25, 20, 3, 1, 15, 10);
        $mockQuery->shouldReceive('whereIn')
            ->with('status', ['captured', 'authorized'])
            ->times(2)
            ->andReturnSelf();
        $mockQuery->shouldReceive('where')
            ->with('status', 'failed')
            ->once()
            ->andReturnSelf();
        $mockQuery->shouldReceive('where')
            ->with('status', 'expired')
            ->once()
            ->andReturnSelf();
        $mockQuery->shouldReceive('sum')
            ->with('amount')
            ->once()
            ->andReturn(3125.25);
        $mockQuery->shouldReceive('whereNotNull')
            ->with('portal_configuration')
            ->once()
            ->andReturnSelf();
        $mockQuery->shouldReceive('whereNull')
            ->with('portal_configuration')
            ->once()
            ->andReturnSelf();

        PaymentSession::shouldReceive('query')
            ->once()
            ->andReturn($mockQuery);

        // Act
        $result = $this->manager->getSessionStatistics($startDate);

        // Assert
        $expected = [
            'total_sessions' => 25,
            'completed_sessions' => 20,
            'failed_sessions' => 3,
            'expired_sessions' => 1,
            'total_amount' => 3125.25,
            'portal_sessions' => 15,
            'embedded_sessions' => 10,
        ];

        $this->assertEquals($expected, $result);
    }
}