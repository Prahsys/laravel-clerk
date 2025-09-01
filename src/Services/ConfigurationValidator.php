<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Services;

use Prahsys\LaravelClerk\Exceptions\ConfigurationException;

class ConfigurationValidator
{
    public function validateConfiguration(): void
    {
        $this->validateApiConfiguration();
        $this->validateMerchantConfiguration();
        $this->validateRetryConfiguration();
        $this->validateWebhookConfiguration();
    }

    protected function validateApiConfiguration(): void
    {
        $sandboxMode = config('clerk.api.sandbox_mode');
        $sandboxUrl = config('clerk.api.sandbox_url');
        $productionUrl = config('clerk.api.production_url');
        $sandboxKey = config('clerk.api.sandbox_api_key');
        $productionKey = config('clerk.api.production_api_key');

        // Validate URLs
        if (!$sandboxUrl || !filter_var($sandboxUrl, FILTER_VALIDATE_URL)) {
            throw ConfigurationException::invalidConfiguration('sandbox_url', 'must be a valid URL');
        }

        if (!$productionUrl || !filter_var($productionUrl, FILTER_VALIDATE_URL)) {
            throw ConfigurationException::invalidConfiguration('production_url', 'must be a valid URL');
        }

        // Validate API keys based on mode
        if ($sandboxMode) {
            if (!$sandboxKey || strlen($sandboxKey) < 10) {
                throw ConfigurationException::invalidConfiguration('sandbox_api_key', 'must be at least 10 characters');
            }
        } else {
            if (!$productionKey || strlen($productionKey) < 10) {
                throw ConfigurationException::invalidConfiguration('production_api_key', 'must be at least 10 characters');
            }
        }

        // Validate both keys are present for completeness
        if (!$sandboxKey && !$productionKey) {
            throw ConfigurationException::invalidConfiguration('api_keys', 'at least one API key must be configured');
        }
    }

    protected function validateMerchantConfiguration(): void
    {
        $merchantId = config('clerk.api.merchant_id');

        if (!$merchantId || !is_string($merchantId) || strlen($merchantId) < 3) {
            throw ConfigurationException::missingMerchantId();
        }
    }

    protected function validateRetryConfiguration(): void
    {
        $maxRetries = config('clerk.api.max_retries', 3);
        $retryDelay = config('clerk.api.retry_delay', 1000);

        if (!is_int($maxRetries) || $maxRetries < 0 || $maxRetries > 10) {
            throw ConfigurationException::invalidConfiguration('max_retries', 'must be between 0 and 10');
        }

        if (!is_int($retryDelay) || $retryDelay < 100 || $retryDelay > 30000) {
            throw ConfigurationException::invalidConfiguration('retry_delay', 'must be between 100 and 30000 milliseconds');
        }
    }

    protected function validateWebhookConfiguration(): void
    {
        $webhooksEnabled = config('clerk.webhooks.enabled', true);
        
        if (!$webhooksEnabled) {
            return; // Skip validation if webhooks are disabled
        }

        $webhookRoute = config('clerk.webhooks.route');
        $webhookSecret = config('clerk.webhooks.secret');

        if (!$webhookRoute || !is_string($webhookRoute)) {
            throw ConfigurationException::invalidConfiguration('webhooks.route', 'must be configured when webhooks are enabled');
        }

        if (!str_starts_with($webhookRoute, '/')) {
            throw ConfigurationException::invalidConfiguration('webhooks.route', 'must start with /');
        }

        if ($webhookSecret && strlen($webhookSecret) < 32) {
            throw ConfigurationException::invalidConfiguration('webhooks.secret', 'must be at least 32 characters when provided');
        }
    }

    /**
     * Check if configuration is valid without throwing exceptions
     */
    public function isConfigurationValid(): bool
    {
        try {
            $this->validateConfiguration();
            return true;
        } catch (ConfigurationException $e) {
            return false;
        }
    }

    /**
     * Get configuration validation errors
     */
    public function getValidationErrors(): array
    {
        $errors = [];

        try {
            $this->validateApiConfiguration();
        } catch (ConfigurationException $e) {
            $errors[] = 'API Configuration: ' . $e->getMessage();
        }

        try {
            $this->validateMerchantConfiguration();
        } catch (ConfigurationException $e) {
            $errors[] = 'Merchant Configuration: ' . $e->getMessage();
        }

        try {
            $this->validateRetryConfiguration();
        } catch (ConfigurationException $e) {
            $errors[] = 'Retry Configuration: ' . $e->getMessage();
        }

        try {
            $this->validateWebhookConfiguration();
        } catch (ConfigurationException $e) {
            $errors[] = 'Webhook Configuration: ' . $e->getMessage();
        }

        return $errors;
    }
}