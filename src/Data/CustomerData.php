<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Data;

use Spatie\LaravelData\Data;

class CustomerData extends Data
{
    public function __construct(
        public ?string $email = null,
        public ?string $name = null,
        public ?string $phone = null,
        public ?AddressData $billingAddress = null,
        public ?AddressData $shippingAddress = null
    ) {
    }
}