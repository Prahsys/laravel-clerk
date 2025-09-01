<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Data;

use Spatie\LaravelData\Data;

class PrahsysResponseData extends Data
{
    public function __construct(
        public bool $success,
        public string $message,
        public mixed $data = null,
        public ?PrahsysErrorData $error = null
    ) {
    }
}