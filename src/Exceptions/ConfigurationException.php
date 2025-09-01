<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Exceptions;

use InvalidArgumentException;

class ConfigurationException extends InvalidArgumentException
{
    public static function missingApiKey(string $mode): self
    {
        return new self("API key is required for {$mode} mode");
    }

    public static function missingBaseUrl(): self
    {
        return new self('API base URLs must be configured');
    }

    public static function missingMerchantId(): self
    {
        return new self('Merchant ID is required');
    }

    public static function invalidConfiguration(string $field, string $reason): self
    {
        return new self("Invalid configuration for '{$field}': {$reason}");
    }
}