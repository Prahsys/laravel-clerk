<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Exceptions;

use Exception;
use Prahsys\LaravelClerk\Data\PrahsysErrorData;
use Saloon\Http\Response;

class PrahsysException extends Exception
{
    public function __construct(
        public string $type,
        public string $errorCode,
        string $message,
        public ?array $details = null,
        public ?Response $response = null
    ) {
        parent::__construct($message);
    }

    public static function fromResponse(Response $response): self
    {
        $errorData = $response->json('error');
        
        if (!$errorData) {
            return new self(
                type: 'api_error',
                code: 'unknown_error',
                message: 'An unknown error occurred',
                response: $response
            );
        }

        return new self(
            type: $errorData['type'] ?? 'api_error',
            errorCode: $errorData['code'] ?? 'unknown_error',
            message: $errorData['message'] ?? 'An unknown error occurred',
            details: $errorData['details'] ?? null,
            response: $response
        );
    }

    public static function fromErrorData(PrahsysErrorData $errorData, ?Response $response = null): self
    {
        return new self(
            type: $errorData->type,
            errorCode: $errorData->code,
            message: $errorData->message,
            details: $errorData->details,
            response: $response
        );
    }

    public function getHttpStatusCode(): ?int
    {
        return $this->response?->status();
    }

    public function isAuthenticationError(): bool
    {
        return $this->type === 'authentication_error';
    }

    public function isValidationError(): bool
    {
        return $this->type === 'validation_error';
    }

    public function isRateLimitError(): bool
    {
        return $this->errorCode === 'rate_limit_exceeded';
    }

    public function isNetworkError(): bool
    {
        return $this->type === 'network_error';
    }

    public function isRetryable(): bool
    {
        return $this->isRateLimitError() 
            || $this->isNetworkError() 
            || ($this->getHttpStatusCode() >= 500);
    }
}