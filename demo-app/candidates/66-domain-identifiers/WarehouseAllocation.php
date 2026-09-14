<?php

declare(strict_types=1);

namespace DemoApp\Candidates\DomainIdentifiers;

final readonly class WarehouseAllocation
{
    public function __construct(
        private int $physicalInventoryQuantity,
        private int $reservedInventoryQuantity,
        private int $damagedInventoryQuantity,
    ) {}

    public function availableQuantity(): int
    {
        return $this->physicalInventoryQuantity
            - $this->reservedInventoryQuantity
            - $this->damagedInventoryQuantity;
    }
}
