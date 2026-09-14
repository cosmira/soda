<?php

declare(strict_types=1);

namespace DemoApp\Orders;

final class OrderNumberFormatter
{
    private string $prefix;

    public function __construct(string $storeCode)
    {
        $this->prefix = strtoupper($storeCode);
    }

    public function format(int $sequence): string
    {
        return sprintf('%s-%06d', $this->prefix, $sequence);
    }
}
