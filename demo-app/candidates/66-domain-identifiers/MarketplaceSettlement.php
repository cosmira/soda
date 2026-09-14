<?php

declare(strict_types=1);

namespace DemoApp\Candidates\DomainIdentifiers;

final readonly class MarketplaceSettlement
{
    public function __construct(
        private int $expectedSettlementAmount,
        private int $capturedSettlementAmount,
        private int $refundedSettlementAmount,
    ) {}

    public function unresolvedAmount(): int
    {
        return $this->expectedSettlementAmount
            - $this->capturedSettlementAmount
            - $this->refundedSettlementAmount;
    }
}
