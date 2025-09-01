<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Tests\Unit\Http\Requests;

use Prahsys\LaravelClerk\Data\PaymentData;
use Prahsys\LaravelClerk\Data\SessionData;
use Prahsys\LaravelClerk\Http\Requests\ProcessPaymentRequest;
use Prahsys\LaravelClerk\Tests\TestCase;

class ProcessPaymentRequestTest extends TestCase
{
    public function test_creates_payment_processing_request()
    {
        $paymentData = new PaymentData(
            id: 'PAYMENT-123',
            amount: 12.99,
            currency: 'USD'
        );

        $sessionData = new SessionData(
            id: 'SESSION0002776555072F1187121H61'
        );

        $request = new ProcessPaymentRequest('PAYMENT-123', $paymentData, $sessionData);

        $this->assertEquals('POST', $request->getMethod());
        $this->assertTrue(str_ends_with($request->resolveEndpoint(), '/payment/PAYMENT-123/pay'));
        
        $body = $request->body()->all();
        $this->assertEquals([
            'payment' => [
                'id' => 'PAYMENT-123',
                'amount' => 12.99,
                'currency' => 'USD',
            ],
            'session' => [
                'id' => 'SESSION0002776555072F1187121H61',
            ],
        ], $body);
    }

    public function test_supports_authorization_only_payments()
    {
        $paymentData = new PaymentData(
            id: 'PAYMENT-AUTH-123',
            amount: 99.99,
            currency: 'USD',
            captureMethod: 'manual'
        );

        $sessionData = new SessionData(
            id: 'SESSION0002776555072F1187121H61'
        );

        $request = new ProcessPaymentRequest('PAYMENT-AUTH-123', $paymentData, $sessionData);
        $body = $request->body()->all();

        $this->assertEquals('manual', $body['payment']['captureMethod']);
    }

    public function test_includes_merchant_endpoint_in_url()
    {
        config(['clerk.api.merchant_id' => 'MERCHANT_456']);

        $paymentData = new PaymentData(
            id: 'PAYMENT-123',
            amount: 25.00
        );

        $sessionData = new SessionData(
            id: 'SESSION0002776555072F1187121H61'
        );

        $request = new ProcessPaymentRequest('PAYMENT-123', $paymentData, $sessionData);

        $this->assertTrue(str_contains($request->resolveEndpoint(), '/merchant/MERCHANT_456/payment/PAYMENT-123/pay'));
    }

    public function test_supports_card_present_payment_processing()
    {
        $paymentData = new PaymentData(
            id: 'PAYMENT-POS-123',
            amount: 45.67,
            currency: 'USD',
            cardPresent: true,
            terminalId: 'TERMINAL_002',
            receiptNumber: 'RCP001234'
        );

        $sessionData = new SessionData(
            id: 'SESSION0002776555072F1187121H61'
        );

        $request = new ProcessPaymentRequest('PAYMENT-POS-123', $paymentData, $sessionData);
        $body = $request->body()->all();

        $this->assertTrue($body['payment']['cardPresent']);
        $this->assertEquals('TERMINAL_002', $body['payment']['terminalId']);
        $this->assertEquals('RCP001234', $body['payment']['receiptNumber']);
    }
}