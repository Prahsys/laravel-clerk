<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use Prahsys\LaravelClerk\Services\PaymentService;
use Prahsys\LaravelClerk\Http\PrahsysConnector;
use Prahsys\LaravelClerk\Data\PaymentData;
use Prahsys\LaravelClerk\Data\PortalConfigurationData;
use Prahsys\LaravelClerk\Data\SessionData;
use Prahsys\LaravelClerk\Http\Requests\CreatePaymentSessionRequest;
use Prahsys\LaravelClerk\Http\Requests\GetSessionRequest;
use Prahsys\LaravelClerk\Http\Requests\UpdateSessionRequest;
use Prahsys\LaravelClerk\Http\Requests\ProcessPaymentRequest;
use Prahsys\LaravelClerk\Http\Requests\CapturePaymentRequest;
use Prahsys\LaravelClerk\Http\Requests\RefundPaymentRequest;
use Prahsys\LaravelClerk\Http\Requests\VoidPaymentRequest;
use Saloon\Http\Response;

class PaymentServiceTest extends TestCase
{
    protected PaymentService $paymentService;
    protected MockInterface $connector;
    protected MockInterface $paymentData;
    protected MockInterface $portalConfigData;
    protected MockInterface $sessionData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connector = Mockery::mock(PrahsysConnector::class);
        $this->paymentService = new PaymentService($this->connector);
        
        $this->paymentData = Mockery::mock(PaymentData::class);
        $this->portalConfigData = Mockery::mock(PortalConfigurationData::class);
        $this->sessionData = Mockery::mock(SessionData::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_create_payment_session_without_portal_config()
    {
        // Arrange
        $mockResponse = Mockery::mock(Response::class);
        
        $this->connector->shouldReceive('send')
            ->once()
            ->with(Mockery::type(CreatePaymentSessionRequest::class))
            ->andReturn($mockResponse);

        // Act
        $result = $this->paymentService->createSession($this->paymentData);

        // Assert
        $this->assertSame($mockResponse, $result);
    }

    /** @test */
    public function it_can_create_payment_session_with_portal_config()
    {
        // Arrange
        $mockResponse = Mockery::mock(Response::class);
        
        $this->connector->shouldReceive('send')
            ->once()
            ->with(Mockery::type(CreatePaymentSessionRequest::class))
            ->andReturn($mockResponse);

        // Act
        $result = $this->paymentService->createSession($this->paymentData, $this->portalConfigData);

        // Assert
        $this->assertSame($mockResponse, $result);
    }

    /** @test */
    public function it_can_create_pay_session_for_embedded_payments()
    {
        // Arrange
        $mockResponse = Mockery::mock(Response::class);
        
        $this->connector->shouldReceive('send')
            ->once()
            ->with(Mockery::type(CreatePaymentSessionRequest::class))
            ->andReturn($mockResponse);

        // Act
        $result = $this->paymentService->createPaySession($this->paymentData);

        // Assert
        $this->assertSame($mockResponse, $result);
    }

    /** @test */
    public function it_can_create_portal_session_for_hosted_checkout()
    {
        // Arrange
        $mockResponse = Mockery::mock(Response::class);
        
        $this->connector->shouldReceive('send')
            ->once()
            ->with(Mockery::type(CreatePaymentSessionRequest::class))
            ->andReturn($mockResponse);

        // Act
        $result = $this->paymentService->createPortalSession($this->paymentData, $this->portalConfigData);

        // Assert
        $this->assertSame($mockResponse, $result);
    }

    /** @test */
    public function it_can_get_session_details()
    {
        // Arrange
        $sessionId = 'session_test_123';
        $mockResponse = Mockery::mock(Response::class);
        
        $this->connector->shouldReceive('send')
            ->once()
            ->with(Mockery::type(GetSessionRequest::class))
            ->andReturn($mockResponse);

        // Act
        $result = $this->paymentService->getSession($sessionId);

        // Assert
        $this->assertSame($mockResponse, $result);
    }

    /** @test */
    public function it_can_update_session_with_new_payment_details()
    {
        // Arrange
        $sessionId = 'session_test_123';
        $mockResponse = Mockery::mock(Response::class);
        
        $this->connector->shouldReceive('send')
            ->once()
            ->with(Mockery::type(UpdateSessionRequest::class))
            ->andReturn($mockResponse);

        // Act
        $result = $this->paymentService->updateSession($sessionId, $this->paymentData);

        // Assert
        $this->assertSame($mockResponse, $result);
    }

    /** @test */
    public function it_can_process_payment_using_session_data()
    {
        // Arrange
        $paymentId = 'payment_test_123';
        $mockResponse = Mockery::mock(Response::class);
        
        $this->connector->shouldReceive('send')
            ->once()
            ->with(Mockery::type(ProcessPaymentRequest::class))
            ->andReturn($mockResponse);

        // Act
        $result = $this->paymentService->processPayment($paymentId, $this->paymentData, $this->sessionData);

        // Assert
        $this->assertSame($mockResponse, $result);
    }

    /** @test */
    public function it_can_capture_payment_without_specific_amount()
    {
        // Arrange
        $paymentId = 'payment_test_123';
        $mockResponse = Mockery::mock(Response::class);
        
        $this->connector->shouldReceive('send')
            ->once()
            ->with(Mockery::type(CapturePaymentRequest::class))
            ->andReturn($mockResponse);

        // Act
        $result = $this->paymentService->capturePayment($paymentId);

        // Assert
        $this->assertSame($mockResponse, $result);
    }

    /** @test */
    public function it_can_capture_payment_with_specific_amount()
    {
        // Arrange
        $paymentId = 'payment_test_123';
        $amount = 25.50;
        $mockResponse = Mockery::mock(Response::class);
        
        $this->connector->shouldReceive('send')
            ->once()
            ->with(Mockery::type(CapturePaymentRequest::class))
            ->andReturn($mockResponse);

        // Act
        $result = $this->paymentService->capturePayment($paymentId, $amount);

        // Assert
        $this->assertSame($mockResponse, $result);
    }

    /** @test */
    public function it_can_refund_payment_without_amount_or_reason()
    {
        // Arrange
        $paymentId = 'payment_test_123';
        $mockResponse = Mockery::mock(Response::class);
        
        $this->connector->shouldReceive('send')
            ->once()
            ->with(Mockery::type(RefundPaymentRequest::class))
            ->andReturn($mockResponse);

        // Act
        $result = $this->paymentService->refundPayment($paymentId);

        // Assert
        $this->assertSame($mockResponse, $result);
    }

    /** @test */
    public function it_can_refund_payment_with_amount_and_reason()
    {
        // Arrange
        $paymentId = 'payment_test_123';
        $amount = 15.00;
        $reason = 'Customer requested refund';
        $mockResponse = Mockery::mock(Response::class);
        
        $this->connector->shouldReceive('send')
            ->once()
            ->with(Mockery::type(RefundPaymentRequest::class))
            ->andReturn($mockResponse);

        // Act
        $result = $this->paymentService->refundPayment($paymentId, $amount, $reason);

        // Assert
        $this->assertSame($mockResponse, $result);
    }

    /** @test */
    public function it_can_void_authorized_payment()
    {
        // Arrange
        $paymentId = 'payment_test_123';
        $mockResponse = Mockery::mock(Response::class);
        
        $this->connector->shouldReceive('send')
            ->once()
            ->with(Mockery::type(VoidPaymentRequest::class))
            ->andReturn($mockResponse);

        // Act
        $result = $this->paymentService->voidPayment($paymentId);

        // Assert
        $this->assertSame($mockResponse, $result);
    }

    /** @test */
    public function it_can_verify_portal_payment_with_matching_indicators()
    {
        // Arrange
        $successIndicator = 'success_123';
        $resultIndicator = 'success_123';

        // Act
        $result = $this->paymentService->verifyPortalPayment($successIndicator, $resultIndicator);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_rejects_portal_payment_with_mismatched_indicators()
    {
        // Arrange
        $successIndicator = 'success_123';
        $resultIndicator = 'different_456';

        // Act
        $result = $this->paymentService->verifyPortalPayment($successIndicator, $resultIndicator);

        // Assert
        $this->assertFalse($result);
    }

    /** @test */
    public function it_identifies_successful_payment_with_captured_status()
    {
        // Arrange
        $sessionId = 'session_test_123';
        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('successful')->once()->andReturn(true);
        $mockResponse->shouldReceive('json')
            ->with('data.status')
            ->once()
            ->andReturn('CAPTURED');
        
        $this->connector->shouldReceive('send')
            ->once()
            ->with(Mockery::type(GetSessionRequest::class))
            ->andReturn($mockResponse);

        // Act
        $result = $this->paymentService->isPaymentSuccessful($sessionId);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_identifies_successful_payment_with_authorized_status()
    {
        // Arrange
        $sessionId = 'session_test_123';
        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('successful')->once()->andReturn(true);
        $mockResponse->shouldReceive('json')
            ->with('data.status')
            ->once()
            ->andReturn('AUTHORIZED');
        
        $this->connector->shouldReceive('send')
            ->once()
            ->with(Mockery::type(GetSessionRequest::class))
            ->andReturn($mockResponse);

        // Act
        $result = $this->paymentService->isPaymentSuccessful($sessionId);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_rejects_unsuccessful_payment_with_pending_status()
    {
        // Arrange
        $sessionId = 'session_test_123';
        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('successful')->once()->andReturn(true);
        $mockResponse->shouldReceive('json')
            ->with('data.status')
            ->once()
            ->andReturn('PENDING');
        
        $this->connector->shouldReceive('send')
            ->once()
            ->with(Mockery::type(GetSessionRequest::class))
            ->andReturn($mockResponse);

        // Act
        $result = $this->paymentService->isPaymentSuccessful($sessionId);

        // Assert
        $this->assertFalse($result);
    }

    /** @test */
    public function it_rejects_successful_payment_when_api_call_fails()
    {
        // Arrange
        $sessionId = 'session_test_123';
        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('successful')->once()->andReturn(false);
        
        $this->connector->shouldReceive('send')
            ->once()
            ->with(Mockery::type(GetSessionRequest::class))
            ->andReturn($mockResponse);

        // Act
        $result = $this->paymentService->isPaymentSuccessful($sessionId);

        // Assert
        $this->assertFalse($result);
    }

    /** @test */
    public function it_identifies_pending_payment_correctly()
    {
        // Arrange
        $sessionId = 'session_test_123';
        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('successful')->once()->andReturn(true);
        $mockResponse->shouldReceive('json')
            ->with('data.status')
            ->once()
            ->andReturn('PENDING');
        
        $this->connector->shouldReceive('send')
            ->once()
            ->with(Mockery::type(GetSessionRequest::class))
            ->andReturn($mockResponse);

        // Act
        $result = $this->paymentService->isPaymentPending($sessionId);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_rejects_pending_payment_with_captured_status()
    {
        // Arrange
        $sessionId = 'session_test_123';
        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('successful')->once()->andReturn(true);
        $mockResponse->shouldReceive('json')
            ->with('data.status')
            ->once()
            ->andReturn('CAPTURED');
        
        $this->connector->shouldReceive('send')
            ->once()
            ->with(Mockery::type(GetSessionRequest::class))
            ->andReturn($mockResponse);

        // Act
        $result = $this->paymentService->isPaymentPending($sessionId);

        // Assert
        $this->assertFalse($result);
    }

    /** @test */
    public function it_rejects_pending_payment_when_api_call_fails()
    {
        // Arrange
        $sessionId = 'session_test_123';
        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('successful')->once()->andReturn(false);
        
        $this->connector->shouldReceive('send')
            ->once()
            ->with(Mockery::type(GetSessionRequest::class))
            ->andReturn($mockResponse);

        // Act
        $result = $this->paymentService->isPaymentPending($sessionId);

        // Assert
        $this->assertFalse($result);
    }

    /** @test */
    public function it_identifies_failed_payment_correctly()
    {
        // Arrange
        $sessionId = 'session_test_123';
        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('successful')->once()->andReturn(true);
        $mockResponse->shouldReceive('json')
            ->with('data.status')
            ->once()
            ->andReturn('FAILED');
        
        $this->connector->shouldReceive('send')
            ->once()
            ->with(Mockery::type(GetSessionRequest::class))
            ->andReturn($mockResponse);

        // Act
        $result = $this->paymentService->isPaymentFailed($sessionId);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_considers_failed_payment_when_api_call_fails()
    {
        // Arrange
        $sessionId = 'session_test_123';
        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('successful')->once()->andReturn(false);
        
        $this->connector->shouldReceive('send')
            ->once()
            ->with(Mockery::type(GetSessionRequest::class))
            ->andReturn($mockResponse);

        // Act
        $result = $this->paymentService->isPaymentFailed($sessionId);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_rejects_failed_payment_with_successful_status()
    {
        // Arrange
        $sessionId = 'session_test_123';
        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('successful')->once()->andReturn(true);
        $mockResponse->shouldReceive('json')
            ->with('data.status')
            ->once()
            ->andReturn('CAPTURED');
        
        $this->connector->shouldReceive('send')
            ->once()
            ->with(Mockery::type(GetSessionRequest::class))
            ->andReturn($mockResponse);

        // Act
        $result = $this->paymentService->isPaymentFailed($sessionId);

        // Assert
        $this->assertFalse($result);
    }
}