<?php

namespace Prahsys\LaravelClerk\Tests\Unit;

use Prahsys\LaravelClerk\ClerkServiceProvider;
use Prahsys\LaravelClerk\Tests\TestCase;

class PackageStructureTest extends TestCase
{
    /** @test */
    public function service_provider_is_registered(): void
    {
        $this->assertTrue(
            $this->app->providerIsLoaded(ClerkServiceProvider::class)
        );
    }

    /** @test */
    public function config_is_published(): void
    {
        $this->artisan('vendor:publish', [
            '--provider' => ClerkServiceProvider::class,
            '--tag' => 'clerk-config',
        ])->assertExitCode(0);

        $this->assertFileExists(config_path('clerk.php'));
    }

    /** @test */
    public function package_has_correct_namespace(): void
    {
        $reflection = new \ReflectionClass(ClerkServiceProvider::class);
        $this->assertEquals('Prahsys\LaravelClerk', $reflection->getNamespaceName());
    }

    /** @test */
    public function configuration_is_merged(): void
    {
        $this->assertIsArray(config('clerk'));
        $this->assertEquals('sb_test_key_123', config('clerk.api.sandbox_api_key'));
        $this->assertTrue(config('clerk.api.sandbox_mode'));
    }

    /** @test */
    public function package_has_required_directories(): void
    {
        $packageRoot = dirname(__DIR__, 2);
        
        $this->assertDirectoryExists($packageRoot . '/src');
        $this->assertDirectoryExists($packageRoot . '/tests');
        $this->assertDirectoryExists($packageRoot . '/config');
        $this->assertDirectoryExists($packageRoot . '/database/migrations');
    }
}