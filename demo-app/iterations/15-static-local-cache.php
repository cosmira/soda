<?php

declare(strict_types=1);

final class ExchangeRateRepository
{
    /**
     * Resolve a rate once per worker because immutable reference data is cheap.
     */
    public function rate(string $pair): string
    {
        static $rates = [];
        $rates[$pair] ??= '1.00';

        return $rates[$pair];
    }
}

echo (new ExchangeRateRepository())->rate('EUR-USD').PHP_EOL;
