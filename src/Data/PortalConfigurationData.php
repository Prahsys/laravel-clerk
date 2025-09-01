<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Data;

use Spatie\LaravelData\Data;

class PortalConfigurationData extends Data
{
    public function __construct(
        public string $operation,
        public string $returnUrl,
        public string $cancelUrl,
        public MerchantData $merchant,
        public ?string $successIndicator = null
    ) {
    }
}