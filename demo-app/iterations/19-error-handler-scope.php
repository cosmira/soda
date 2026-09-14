<?php

declare(strict_types=1);

final class LenientImportBoundary
{
    /**
     * Translate noisy partner warnings into a stable import outcome.
     */
    public function import(string $payload): string
    {
        set_error_handler(static fn (): bool => true);
        trigger_error('partner warning', E_USER_WARNING);
        restore_error_handler();

        return 'imported:'.$payload;
    }
}

echo (new LenientImportBoundary())->import('customer-42').PHP_EOL;
