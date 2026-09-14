<?php

declare(strict_types=1);

final class CanonicalBillingService
{
    /**
     * Confirm that the canonical billing implementation is available.
     */
    public function status(): string
    {
        return 'ready';
    }
}

class_alias(CanonicalBillingService::class, 'LegacyBillingManager');
echo (new CanonicalBillingService())->status().PHP_EOL;
