<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Data;

use Spatie\LaravelData\Data;

class PrahsysErrorData extends Data
{
    public function __construct(
        public string $code,
        public string $message,
        public string $type,
        public ?array $details = null
    ) {
    }
}