<?php

declare(strict_types=1);

namespace DemoApp\Billing;

final readonly class Money
{
    public int $amount;

    public string $currency;

    public function __construct(int $amount, string $currency)
    {
        $this->amount = $amount;
        $this->currency = strtoupper($currency);
    }

    public function formatted(): string
    {
        return sprintf('%s %0.2f', $this->currency, $this->amount / 100);
    }
}
