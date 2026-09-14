<?php

declare(strict_types=1);

namespace DemoApp\Shipping;

abstract readonly class ShippingContext
{
    public function __construct(
        public string $requestId,
        public string $actorId,
        public string $locale,
    ) {}
}
