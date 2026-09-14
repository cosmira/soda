<?php

declare(strict_types=1);

namespace DemoApp\Billing;

abstract readonly class WebhookContext
{
    public function __construct(
        public string $eventId,
        public string $occurredAt,
        public string $source,
    ) {}
}
