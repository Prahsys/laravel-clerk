<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Prahsys\LaravelClerk\Models\WebhookEvent;

class WebhookReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public WebhookEvent $webhookEvent
    ) {
    }
}