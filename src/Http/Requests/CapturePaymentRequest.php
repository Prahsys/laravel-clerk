<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Http\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class CapturePaymentRequest extends Request
{
    protected Method $method = Method::POST;

    public function __construct(
        protected string $paymentId,
        protected ?float $amount = null
    ) {
    }

    public function resolveEndpoint(): string
    {
        $merchantId = config('clerk.api.merchant_id');
        return "/payments/n1/merchant/{$merchantId}/payment/{$this->paymentId}/capture";
    }

    protected function defaultBody(): array
    {
        $body = [];

        if ($this->amount !== null) {
            $body['amount'] = $this->amount;
        }

        return $body;
    }
}