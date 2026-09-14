<?php

declare(strict_types=1);

final class ShipOrder
{
    public function handle(string $number): string
    {
        return 'shipped:'.$number;
    }
}

echo (new ShipOrder)->handle('ORD-2048').PHP_EOL;
