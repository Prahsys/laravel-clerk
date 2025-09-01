<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Tests\Unit\Http;

use Prahsys\LaravelClerk\Http\PrahsysConnector;
use Prahsys\LaravelClerk\Tests\TestCase;

class PrahsysConnectorTest extends TestCase
{
    public function test_resolves_sandbox_base_url_when_sandbox_mode_enabled()
    {
        config([
            'clerk.api.sandbox_mode' => true,
            'clerk.api.sandbox_url' => 'https://sandbox-api.prahsys.com',
            'clerk.api.production_url' => 'https://api.prahsys.com',
        ]);

        $connector = new PrahsysConnector();

        $this->assertEquals('https://sandbox-api.prahsys.com', $connector->resolveBaseUrl());
    }

    public function test_resolves_production_base_url_when_sandbox_mode_disabled()
    {
        config([
            'clerk.api.sandbox_mode' => false,
            'clerk.api.sandbox_url' => 'https://sandbox-api.prahsys.com',
            'clerk.api.production_url' => 'https://api.prahsys.com',
        ]);

        $connector = new PrahsysConnector();

        $this->assertEquals('https://api.prahsys.com', $connector->resolveBaseUrl());
    }

    public function test_includes_bearer_token_in_default_headers()
    {
        config([
            'clerk.api.sandbox_mode' => true,
            'clerk.api.sandbox_api_key' => 'sb_test_key_123',
            'clerk.api.production_api_key' => 'pk_live_key_456',
        ]);

        $connector = new PrahsysConnector();
        $headers = $connector->headers()->all();

        $this->assertEquals('Bearer sb_test_key_123', $headers['Authorization']);
        $this->assertEquals('application/json', $headers['Content-Type']);
        $this->assertEquals('application/json', $headers['Accept']);
    }

    public function test_uses_production_api_key_when_sandbox_mode_disabled()
    {
        config([
            'clerk.api.sandbox_mode' => false,
            'clerk.api.sandbox_api_key' => 'sb_test_key_123',
            'clerk.api.production_api_key' => 'pk_live_key_456',
        ]);

        $connector = new PrahsysConnector();
        $headers = $connector->headers()->all();

        $this->assertEquals('Bearer pk_live_key_456', $headers['Authorization']);
    }

    public function test_has_appropriate_timeout_configuration()
    {
        $connector = new PrahsysConnector();
        $config = $connector->config()->all();

        $this->assertEquals(30, $config['timeout']);
        $this->assertEquals(10, $config['connect_timeout']);
    }

    public function test_throws_exception_when_api_key_missing()
    {
        config([
            'clerk.api.sandbox_mode' => true,
            'clerk.api.sandbox_api_key' => null,
            'clerk.api.production_api_key' => null,
        ]);

        $this->expectException(\Prahsys\LaravelClerk\Exceptions\ConfigurationException::class);
        $this->expectExceptionMessage('API key is required for sandbox mode');

        new PrahsysConnector();
    }

    public function test_throws_exception_when_sandbox_api_key_missing_in_sandbox_mode()
    {
        config([
            'clerk.api.sandbox_mode' => true,
            'clerk.api.sandbox_api_key' => null,
            'clerk.api.production_api_key' => 'pk_live_key_456',
        ]);

        $this->expectException(\Prahsys\LaravelClerk\Exceptions\ConfigurationException::class);
        $this->expectExceptionMessage('API key is required for sandbox mode');

        new PrahsysConnector();
    }

    public function test_throws_exception_when_production_api_key_missing_in_production_mode()
    {
        config([
            'clerk.api.sandbox_mode' => false,
            'clerk.api.sandbox_api_key' => 'sb_test_key_123',
            'clerk.api.production_api_key' => null,
        ]);

        $this->expectException(\Prahsys\LaravelClerk\Exceptions\ConfigurationException::class);
        $this->expectExceptionMessage('API key is required for production mode');

        new PrahsysConnector();
    }

    public function test_validates_base_urls_are_configured()
    {
        config([
            'clerk.api.sandbox_mode' => true,
            'clerk.api.sandbox_api_key' => 'sb_test_key_123',
            'clerk.api.sandbox_url' => null,
            'clerk.api.production_url' => 'https://api.prahsys.com',
        ]);

        $this->expectException(\Prahsys\LaravelClerk\Exceptions\ConfigurationException::class);
        $this->expectExceptionMessage('API base URLs must be configured');

        $connector = new PrahsysConnector();
        // The exception should be thrown when trying to resolve the base URL
        $connector->resolveBaseUrl();
    }
}