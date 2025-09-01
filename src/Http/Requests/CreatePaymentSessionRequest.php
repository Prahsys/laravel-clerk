<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Http\Requests;

use Prahsys\LaravelClerk\Data\PaymentData;
use Prahsys\LaravelClerk\Data\PortalConfigurationData;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class CreatePaymentSessionRequest extends Request
{
    protected Method $method = Method::POST;

    public function __construct(
        protected PaymentData $payment,
        protected ?PortalConfigurationData $portal = null
    ) {
    }

    public function resolveEndpoint(): string
    {
        $merchantId = config('clerk.api.merchant_id');
        return "/payments/n1/merchant/{$merchantId}/session";
    }

    protected function defaultBody(): array
    {
        $body = [
            'payment' => $this->payment->toArray(),
        ];

        if ($this->portal) {
            $body['portal'] = $this->portal->toArray();
        }

        return $body;
    }
}