<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Http;

use Prahsys\LaravelClerk\Exceptions\ConfigurationException;
use Prahsys\LaravelClerk\Exceptions\PrahsysException;
use Saloon\Http\Connector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Plugins\AcceptsJson;

class PrahsysConnector extends Connector
{
    use AcceptsJson;

    public function resolveBaseUrl(): string
    {
        $sandboxMode = config('clerk.api.sandbox_mode', true);
        $sandboxUrl = config('clerk.api.sandbox_url');
        $productionUrl = config('clerk.api.production_url');

        if (!$sandboxUrl || !$productionUrl) {
            throw ConfigurationException::missingBaseUrl();
        }

        return $sandboxMode ? $sandboxUrl : $productionUrl;
    }

    protected function defaultHeaders(): array
    {
        $apiKey = $this->getApiKey();

        return [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    protected function defaultConfig(): array
    {
        return [
            'timeout' => 30,
            'connect_timeout' => 10,
        ];
    }

    private function getApiKey(): string
    {
        $sandboxMode = config('clerk.api.sandbox_mode', true);
        $sandboxKey = config('clerk.api.sandbox_api_key');
        $productionKey = config('clerk.api.production_api_key');

        if ($sandboxMode) {
            if (!$sandboxKey) {
                throw ConfigurationException::missingApiKey('sandbox');
            }
            return $sandboxKey;
        }

        if (!$productionKey) {
            throw ConfigurationException::missingApiKey('production');
        }

        return $productionKey;
    }

    public function __construct()
    {
        // Validate API key is available on instantiation
        $this->getApiKey();
    }

    public function send(Request $request, ?MockClient $mockClient = null, ?callable $handleRetry = null): Response
    {
        $maxRetries = config('clerk.api.max_retries', 3);
        $baseDelay = config('clerk.api.retry_delay', 1000); // milliseconds
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = parent::send($request, $mockClient, $handleRetry);
                
                if ($response->successful()) {
                    return $response;
                }

                // Handle specific HTTP errors
                if ($response->status() >= 500) {
                    // Server error - retry with exponential backoff
                    if ($attempt < $maxRetries) {
                        $delay = $baseDelay * pow(2, $attempt - 1);
                        usleep($delay * 1000); // Convert to microseconds
                        continue;
                    }
                }

                // For client errors, don't retry - throw immediately
                throw PrahsysException::fromResponse($response);

            } catch (\Throwable $e) {
                if ($e instanceof PrahsysException) {
                    // If it's already a PrahsysException and not retryable, throw it
                    if (!$e->isRetryable() || $attempt >= $maxRetries) {
                        throw $e;
                    }
                } else {
                    // Network or other errors - create PrahsysException
                    $prahsysException = new PrahsysException(
                        type: 'network_error',
                        errorCode: 'connection_failed',
                        message: $e->getMessage()
                    );

                    if ($attempt >= $maxRetries || !$prahsysException->isRetryable()) {
                        throw $prahsysException;
                    }
                }

                // Wait before retrying with exponential backoff
                if ($attempt < $maxRetries) {
                    $delay = $baseDelay * pow(2, $attempt - 1);
                    usleep($delay * 1000); // Convert to microseconds
                }
            }
        }

        // This should never be reached, but just in case
        throw new PrahsysException(
            type: 'api_error',
            errorCode: 'max_retries_exceeded',
            message: 'Maximum retry attempts exceeded'
        );
    }
}