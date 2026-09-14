<?php

declare(strict_types=1);

namespace DemoApp\Shipping;

final readonly class ShipOrderRequest extends ShippingContext
{
    public function __construct(
        public string $orderNumber,
        public string $address,
        public string $serviceLevel,
    ) {
        parent::__construct('req-62', 'customer-9', 'en');
    }

    public function summary(): string
    {
        return $this->requestId.'|'.$this->orderNumber.'|'.$this->serviceLevel.'|'.$this->locale;
    }
}
