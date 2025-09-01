<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Http\Requests;

use Prahsys\LaravelClerk\Data\PaymentData;
use Prahsys\LaravelClerk\Data\SessionData;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class ProcessPaymentRequest extends Request
{
    protected Method $method = Method::POST;

    public function __construct(
        protected string $paymentId,
        protected PaymentData $payment,
        protected SessionData $session
    ) {
    }

    public function resolveEndpoint(): string
    {
        $merchantId = config('clerk.api.merchant_id');
        return "/payments/n1/merchant/{$merchantId}/payment/{$this->paymentId}/pay";
    }

    protected function defaultBody(): array
    {
        return [
            'payment' => $this->payment->toArray(),
            'session' => $this->session->toArray(),
        ];
    }
}