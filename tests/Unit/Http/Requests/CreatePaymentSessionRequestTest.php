<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Tests\Unit\Http\Requests;

use Prahsys\LaravelClerk\Data\PaymentData;
use Prahsys\LaravelClerk\Data\PortalConfigurationData;
use Prahsys\LaravelClerk\Data\MerchantData;
use Prahsys\LaravelClerk\Http\PrahsysConnector;
use Prahsys\LaravelClerk\Http\Requests\CreatePaymentSessionRequest;
use Prahsys\LaravelClerk\Tests\TestCase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

class CreatePaymentSessionRequestTest extends TestCase
{
    public function test_creates_pay_session_request_without_portal()
    {
        $paymentData = new PaymentData(
            id: 'PAYMENT-123',
            amount: 99.99,
            description: 'Premium subscription',
            currency: 'USD'
        );

        $request = new CreatePaymentSessionRequest($paymentData);

        $this->assertEquals('POST', $request->getMethod());
        $this->assertTrue(str_ends_with($request->resolveEndpoint(), '/session'));
        
        $body = $request->body()->all();
        $this->assertEquals([
            'payment' => [
                'id' => 'PAYMENT-123',
                'amount' => 99.99,
                'description' => 'Premium subscription',
                'currency' => 'USD',
            ],
        ], $body);
    }

    public function test_creates_pay_portal_session_request_with_portal_configuration()
    {
        $paymentData = new PaymentData(
            id: 'PAYMENT-123',
            amount: 99.99,
            description: 'Premium subscription',
            currency: 'USD'
        );

        $merchantData = new MerchantData(
            name: 'Your Store Name',
            logo: 'https://your-store.com/logo.png'
        );

        $portalData = new PortalConfigurationData(
            operation: 'PAY',
            returnUrl: 'https://your-store.com/checkout/complete',
            cancelUrl: 'https://your-store.com/checkout/cancel',
            merchant: $merchantData
        );

        $request = new CreatePaymentSessionRequest($paymentData, $portalData);

        $body = $request->body()->all();
        $this->assertEquals([
            'payment' => [
                'id' => 'PAYMENT-123',
                'amount' => 99.99,
                'description' => 'Premium subscription',
                'currency' => 'USD',
            ],
            'portal' => [
                'operation' => 'PAY',
                'returnUrl' => 'https://your-store.com/checkout/complete',
                'cancelUrl' => 'https://your-store.com/checkout/cancel',
                'merchant' => [
                    'name' => 'Your Store Name',
                    'logo' => 'https://your-store.com/logo.png',
                ],
            ],
        ], $body);
    }

    public function test_supports_different_portal_operations()
    {
        $paymentData = new PaymentData(
            id: 'PAYMENT-123',
            amount: 99.99,
            description: 'Card verification',
            currency: 'USD'
        );

        $merchantData = new MerchantData(
            name: 'Your Store Name'
        );

        // Test VERIFY operation for card verification
        $portalData = new PortalConfigurationData(
            operation: 'VERIFY',
            returnUrl: 'https://your-store.com/verify/complete',
            cancelUrl: 'https://your-store.com/verify/cancel',
            merchant: $merchantData
        );

        $request = new CreatePaymentSessionRequest($paymentData, $portalData);
        $body = $request->body()->all();

        $this->assertEquals('VERIFY', $body['portal']['operation']);
    }

    public function test_supports_authorize_operation()
    {
        $paymentData = new PaymentData(
            id: 'PAYMENT-123',
            amount: 99.99,
            description: 'Authorization hold',
            currency: 'USD'
        );

        $merchantData = new MerchantData(
            name: 'Your Store Name'
        );

        $portalData = new PortalConfigurationData(
            operation: 'AUTHORIZE',
            returnUrl: 'https://your-store.com/authorize/complete',
            cancelUrl: 'https://your-store.com/authorize/cancel',
            merchant: $merchantData
        );

        $request = new CreatePaymentSessionRequest($paymentData, $portalData);
        $body = $request->body()->all();

        $this->assertEquals('AUTHORIZE', $body['portal']['operation']);
    }

    public function test_supports_none_operation_for_tokenization()
    {
        $paymentData = new PaymentData(
            id: 'PAYMENT-123',
            amount: 0.00,
            description: 'Card tokenization',
            currency: 'USD'
        );

        $merchantData = new MerchantData(
            name: 'Your Store Name'
        );

        $portalData = new PortalConfigurationData(
            operation: 'NONE',
            returnUrl: 'https://your-store.com/tokenize/complete',
            cancelUrl: 'https://your-store.com/tokenize/cancel',
            merchant: $merchantData
        );

        $request = new CreatePaymentSessionRequest($paymentData, $portalData);
        $body = $request->body()->all();

        $this->assertEquals('NONE', $body['portal']['operation']);
    }

    public function test_includes_merchant_endpoint_in_url()
    {
        config(['clerk.api.merchant_id' => 'MERCHANT_123']);

        $paymentData = new PaymentData(
            id: 'PAYMENT-123',
            amount: 99.99,
            description: 'Test payment'
        );

        $request = new CreatePaymentSessionRequest($paymentData);

        $this->assertTrue(str_contains($request->resolveEndpoint(), '/merchant/MERCHANT_123/session'));
    }

    public function test_supports_card_present_transactions()
    {
        $paymentData = new PaymentData(
            id: 'PAYMENT-123',
            amount: 99.99,
            description: 'In-store purchase',
            currency: 'USD',
            cardPresent: true,
            terminalId: 'TERMINAL_001'
        );

        $request = new CreatePaymentSessionRequest($paymentData);
        $body = $request->body()->all();

        $this->assertTrue($body['payment']['cardPresent']);
        $this->assertEquals('TERMINAL_001', $body['payment']['terminalId']);
    }

    public function test_can_create_session_with_mock_response()
    {
        $mockClient = new MockClient([
            CreatePaymentSessionRequest::class => MockResponse::fixture('success'),
        ]);

        $connector = new PrahsysConnector();
        $connector->withMockClient($mockClient);

        $paymentData = new PaymentData(
            id: 'PAYMENT-123',
            amount: 99.99,
            description: 'Premium subscription'
        );

        $request = new CreatePaymentSessionRequest($paymentData);
        $response = $connector->send($request);

        $this->assertEquals(200, $response->status());
        $this->assertTrue($response->json('success'));
        $this->assertEquals('SESSION0002776555072F1187121H61', $response->json('data.id'));
    }

    public function test_can_create_portal_session_with_mock_response()
    {
        $mockClient = new MockClient([
            CreatePaymentSessionRequest::class => MockResponse::fixture('portal-success'),
        ]);

        $connector = new PrahsysConnector();
        $connector->withMockClient($mockClient);

        $paymentData = new PaymentData(
            id: 'PAYMENT-123',
            amount: 99.99,
            description: 'Premium subscription'
        );

        $merchantData = new MerchantData(
            name: 'Your Store Name',
            logo: 'https://your-store.com/logo.png'
        );

        $portalData = new PortalConfigurationData(
            operation: 'PAY',
            returnUrl: 'https://your-store.com/checkout/complete',
            cancelUrl: 'https://your-store.com/checkout/cancel',
            merchant: $merchantData
        );

        $request = new CreatePaymentSessionRequest($paymentData, $portalData);
        $response = $connector->send($request);

        $this->assertEquals(200, $response->status());
        $this->assertTrue($response->json('success'));
        $this->assertEquals('b7a1513be2914023', $response->json('data.portal.successIndicator'));
    }
}