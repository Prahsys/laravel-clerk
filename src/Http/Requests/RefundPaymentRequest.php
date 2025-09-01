<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Http\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class RefundPaymentRequest extends Request
{
    protected Method $method = Method::POST;

    public function __construct(
        protected string $paymentId,
        protected ?float $amount = null,
        protected ?string $reason = null
    ) {
    }

    public function resolveEndpoint(): string
    {
        $merchantId = config('clerk.api.merchant_id');
        return "/payments/n1/merchant/{$merchantId}/payment/{$this->paymentId}/refund";
    }

    protected function defaultBody(): array
    {
        $body = [];

        if ($this->amount !== null) {
            $body['amount'] = $this->amount;
        }

        if ($this->reason !== null) {
            $body['reason'] = $this->reason;
        }

        return $body;
    }
}