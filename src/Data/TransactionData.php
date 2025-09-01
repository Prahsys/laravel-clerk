<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Data;

use Spatie\LaravelData\Data;

class TransactionData extends Data
{
    public function __construct(
        public string $id,
        public string $status,
        public PaymentData $payment,
        public SessionData $session,
        public ?CustomerData $customer = null,
        public ?CardData $card = null,
        public ?string $processedAt = null,
        public ?array $metadata = null
    ) {
    }
}