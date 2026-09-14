<?php

declare(strict_types=1);

final class OrderController
{
    public function show(string $number): string
    {
        return 'showing:'.$number;
    }
}

echo (new OrderController)->show('ORD-2048').PHP_EOL;
