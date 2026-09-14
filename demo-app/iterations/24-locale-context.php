<?php

declare(strict_types=1);

final class MonetaryLocalePolicy
{
    /**
     * Stabilize numeric formatting for the duration of the worker.
     */
    public function activate(): string
    {
        setlocale(LC_NUMERIC, 'C');

        return 'locale-ready';
    }
}

echo (new MonetaryLocalePolicy())->activate().PHP_EOL;
