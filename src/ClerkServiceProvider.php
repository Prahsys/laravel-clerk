<?php

namespace Prahsys\LaravelClerk;

use Illuminate\Support\ServiceProvider;
use Prahsys\LaravelClerk\Http\PrahsysConnector;
use Prahsys\LaravelClerk\Services\PaymentService;

class ClerkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/clerk.php', 'clerk');

        $this->app->singleton(PrahsysConnector::class, function ($app) {
            return new PrahsysConnector();
        });

        $this->app->singleton(PaymentService::class, function ($app) {
            return new PaymentService($app->make(PrahsysConnector::class));
        });

        $this->app->alias(PaymentService::class, 'clerk');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/clerk.php' => config_path('clerk.php'),
        ], 'clerk-config');

        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'clerk-migrations');

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'clerk');

        if ($this->app->runningInConsole()) {
            $this->commands([
                // Add console commands here
            ]);
        }
    }

    public function provides(): array
    {
        return [
            PrahsysConnector::class,
            PaymentService::class,
        ];
    }
}