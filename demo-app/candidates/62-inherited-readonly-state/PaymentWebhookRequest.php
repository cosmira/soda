<?php

declare(strict_types=1);

namespace DemoApp\Billing;

final readonly class PaymentWebhookRequest extends WebhookContext
{
    public function __construct(
        public int $amount,
        public string $currency,
        public string $status,
    ) {
        parent::__construct('evt-62', '2026-08-22T12:00:00Z', 'stripe');
    }

    public function summary(): string
    {
        return $this->eventId.'|'.$this->currency.'|'.$this->amount.'|'.$this->status;
    }
}
