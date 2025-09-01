<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Http\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class VoidPaymentRequest extends Request
{
    protected Method $method = Method::POST;

    public function __construct(protected string $paymentId)
    {
    }

    public function resolveEndpoint(): string
    {
        $merchantId = config('clerk.api.merchant_id');
        return "/payments/n1/merchant/{$merchantId}/payment/{$this->paymentId}/void";
    }
}