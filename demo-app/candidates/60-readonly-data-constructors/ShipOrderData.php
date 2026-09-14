<?php

declare(strict_types=1);

namespace DemoApp\Shipping;

final readonly class ShipOrderData
{
    public function __construct(
        public string $orderNumber,
        public string $address,
        public string $serviceLevel,
        public string $requestedDate,
    ) {}

    public function label(): string
    {
        return implode('|', [
            $this->orderNumber,
            $this->serviceLevel,
            $this->requestedDate,
            $this->address,
        ]);
    }
}
