<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Data;

use Spatie\LaravelData\Data;

class MerchantData extends Data
{
    public function __construct(
        public string $name,
        public ?string $logo = null
    ) {
    }
}