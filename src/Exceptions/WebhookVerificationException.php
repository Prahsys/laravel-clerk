<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Exceptions;

use Exception;

class WebhookVerificationException extends Exception
{
    public static function invalidSignature(): self
    {
        return new self('Webhook signature verification failed');
    }

    public static function missingSignature(): self
    {
        return new self('Webhook signature is missing');
    }

    public static function invalidPayload(): self
    {
        return new self('Webhook payload is invalid');
    }
}