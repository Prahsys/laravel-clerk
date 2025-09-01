<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Http\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetSessionRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(protected string $sessionId)
    {
    }

    public function resolveEndpoint(): string
    {
        $merchantId = config('clerk.api.merchant_id');
        return "/payments/n1/merchant/{$merchantId}/session/{$this->sessionId}";
    }
}