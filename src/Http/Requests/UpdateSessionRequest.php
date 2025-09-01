<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Http\Requests;

use Prahsys\LaravelClerk\Data\PaymentData;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class UpdateSessionRequest extends Request
{
    protected Method $method = Method::PUT;

    public function __construct(
        protected string $sessionId,
        protected PaymentData $payment
    ) {
    }

    public function resolveEndpoint(): string
    {
        $merchantId = config('clerk.api.merchant_id');
        return "/payments/n1/merchant/{$merchantId}/session/{$this->sessionId}";
    }

    protected function defaultBody(): array
    {
        return [
            'payment' => $this->payment->toArray(),
        ];
    }
}