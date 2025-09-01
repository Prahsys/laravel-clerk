<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Data;

use Spatie\LaravelData\Data;

class PaymentData extends Data
{
    public function __construct(
        public string $id,
        public float $amount,
        public string $description,
        public string $currency = 'USD',
        public ?string $reference = null,
        public ?string $captureMethod = null,
        public bool $cardPresent = false,
        public ?string $terminalId = null,
        public ?string $receiptNumber = null,
        public ?string $capturedAt = null
    ) {
    }
}