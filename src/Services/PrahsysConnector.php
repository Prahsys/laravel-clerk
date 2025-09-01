<?php

namespace Prahsys\Clerk\Services;

class PrahsysConnector
{
    public function __construct(
        private string $apiKey,
        private bool $sandboxMode = true
    ) {
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function isSandboxMode(): bool
    {
        return $this->sandboxMode;
    }
}