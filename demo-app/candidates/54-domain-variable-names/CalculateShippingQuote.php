<?php

declare(strict_types=1);

final class CalculateShippingQuote
{
    public function __invoke(string $orderNumber, string $shippingAddress): string
    {
        $deliveryZone = str_starts_with($shippingAddress, 'Moscow') ? 'local' : 'remote';

        return $orderNumber.':'.$deliveryZone;
    }
}

echo (new CalculateShippingQuote)->__invoke('ORD-4096', 'Moscow, Tverskaya 1').PHP_EOL;
