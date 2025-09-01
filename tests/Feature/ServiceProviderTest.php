<?php

namespace Prahsys\LaravelClerk\Tests\Feature;

use Prahsys\LaravelClerk\Http\PrahsysConnector;
use Prahsys\LaravelClerk\Services\PaymentService;
use Prahsys\LaravelClerk\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    /** @test */
    public function it_can_resolve_prahsys_connector_from_container(): void
    {
        $connector = $this->app->make(PrahsysConnector::class);
        
        $this->assertInstanceOf(PrahsysConnector::class, $connector);
    }

    /** @test */
    public function it_can_resolve_payment_service_from_container(): void
    {
        $paymentService = $this->app->make(PaymentService::class);
        
        $this->assertInstanceOf(PaymentService::class, $paymentService);
    }

    /** @test */
    public function it_can_resolve_payment_service_via_alias(): void
    {
        $paymentService = $this->app->make('clerk');
        
        $this->assertInstanceOf(PaymentService::class, $paymentService);
    }
}