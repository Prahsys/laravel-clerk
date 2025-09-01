<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Data;

use Spatie\LaravelData\Data;

class SessionData extends Data
{
    public function __construct(
        public string $id,
        public ?string $status = null,
        public ?PaymentData $payment = null,
        public ?PortalConfigurationData $portal = null,
        public ?CustomerData $customer = null,
        public ?CardData $card = null
    ) {
    }
}