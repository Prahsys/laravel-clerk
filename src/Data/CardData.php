<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Data;

use Spatie\LaravelData\Data;

class CardData extends Data
{
    public function __construct(
        public ?string $last4 = null,
        public ?string $brand = null,
        public ?string $expiryMonth = null,
        public ?string $expiryYear = null,
        public ?string $token = null,
        public ?string $fingerprint = null
    ) {
    }
}