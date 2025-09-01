<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Services;

use Prahsys\LaravelClerk\Data\PaymentData;
use Prahsys\LaravelClerk\Data\PortalConfigurationData;
use Prahsys\LaravelClerk\Data\SessionData;
use Prahsys\LaravelClerk\Http\PrahsysConnector;
use Prahsys\LaravelClerk\Http\Requests\CapturePaymentRequest;
use Prahsys\LaravelClerk\Http\Requests\CreatePaymentSessionRequest;
use Prahsys\LaravelClerk\Http\Requests\GetSessionRequest;
use Prahsys\LaravelClerk\Http\Requests\ProcessPaymentRequest;
use Prahsys\LaravelClerk\Http\Requests\RefundPaymentRequest;
use Prahsys\LaravelClerk\Http\Requests\UpdateSessionRequest;
use Prahsys\LaravelClerk\Http\Requests\VoidPaymentRequest;
use Saloon\Http\Response;

class PaymentService
{
    public function __construct(
        protected PrahsysConnector $connector
    ) {
    }

    /**
     * Create a new payment session
     */
    public function createSession(PaymentData $payment, ?PortalConfigurationData $portal = null): Response
    {
        $request = new CreatePaymentSessionRequest($payment, $portal);
        return $this->connector->send($request);
    }

    /**
     * Create a Pay Session for embedded payments
     */
    public function createPaySession(PaymentData $payment): Response
    {
        return $this->createSession($payment);
    }

    /**
     * Create a Pay Portal session for hosted checkout
     */
    public function createPortalSession(PaymentData $payment, PortalConfigurationData $portal): Response
    {
        return $this->createSession($payment, $portal);
    }

    /**
     * Get session details
     */
    public function getSession(string $sessionId): Response
    {
        $request = new GetSessionRequest($sessionId);
        return $this->connector->send($request);
    }

    /**
     * Update an existing session with new payment details
     */
    public function updateSession(string $sessionId, PaymentData $payment): Response
    {
        $request = new UpdateSessionRequest($sessionId, $payment);
        return $this->connector->send($request);
    }

    /**
     * Process a payment using session data
     */
    public function processPayment(string $paymentId, PaymentData $payment, SessionData $session): Response
    {
        $request = new ProcessPaymentRequest($paymentId, $payment, $session);
        return $this->connector->send($request);
    }

    /**
     * Capture a previously authorized payment
     */
    public function capturePayment(string $paymentId, ?float $amount = null): Response
    {
        $request = new CapturePaymentRequest($paymentId, $amount);
        return $this->connector->send($request);
    }

    /**
     * Refund a captured payment
     */
    public function refundPayment(string $paymentId, ?float $amount = null, ?string $reason = null): Response
    {
        $request = new RefundPaymentRequest($paymentId, $amount, $reason);
        return $this->connector->send($request);
    }

    /**
     * Void an authorized but not captured payment
     */
    public function voidPayment(string $paymentId): Response
    {
        $request = new VoidPaymentRequest($paymentId);
        return $this->connector->send($request);
    }

    /**
     * Verify payment success using success indicator (for Portal payments)
     */
    public function verifyPortalPayment(string $successIndicator, string $resultIndicator): bool
    {
        return $successIndicator === $resultIndicator;
    }

    /**
     * Check if payment is successful based on session status
     */
    public function isPaymentSuccessful(string $sessionId): bool
    {
        $response = $this->getSession($sessionId);
        
        if (!$response->successful()) {
            return false;
        }

        $status = $response->json('data.status');
        return in_array($status, ['CAPTURED', 'AUTHORIZED']);
    }

    /**
     * Check if payment is pending
     */
    public function isPaymentPending(string $sessionId): bool
    {
        $response = $this->getSession($sessionId);
        
        if (!$response->successful()) {
            return false;
        }

        $status = $response->json('data.status');
        return $status === 'PENDING';
    }

    /**
     * Check if payment failed
     */
    public function isPaymentFailed(string $sessionId): bool
    {
        $response = $this->getSession($sessionId);
        
        if (!$response->successful()) {
            return true;
        }

        $status = $response->json('data.status');
        return $status === 'FAILED';
    }
}