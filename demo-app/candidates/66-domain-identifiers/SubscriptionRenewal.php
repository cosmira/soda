<?php

declare(strict_types=1);

namespace DemoApp\Candidates\DomainIdentifiers;

final readonly class SubscriptionRenewal
{
    public function __construct(
        private int $configuredGracePeriodDays,
        private int $elapsedGracePeriodDays,
        private int $suspendedGracePeriodDays,
    ) {}

    public function remainingGracePeriod(): int
    {
        return $this->configuredGracePeriodDays
            - $this->elapsedGracePeriodDays
            - $this->suspendedGracePeriodDays;
    }
}
