<?php

declare(strict_types=1);

final class ResolveTenantBilling
{
    public function __invoke(string $tenantId, string $billingCycle): string
    {
        $invoiceTotal = $billingCycle === 'annual' ? 1200 : 120;

        return $tenantId.':'.$invoiceTotal;
    }
}

echo (new ResolveTenantBilling)->__invoke('tenant-17', 'annual').PHP_EOL;
