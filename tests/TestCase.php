<?php

namespace Prahsys\LaravelClerk\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Prahsys\LaravelClerk\ClerkServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ClerkServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set test configuration for Prahsys API
        config()->set('clerk.api.sandbox_mode', true);
        config()->set('clerk.api.sandbox_url', 'https://sandbox-api.prahsys.com');
        config()->set('clerk.api.production_url', 'https://api.prahsys.com');
        config()->set('clerk.api.sandbox_api_key', 'sb_test_key_123');
        config()->set('clerk.api.production_api_key', 'pk_live_key_456');
        config()->set('clerk.api.merchant_id', 'MERCHANT_123');
    }
}