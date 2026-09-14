<?php

declare(strict_types=1);

final class TenantAffinityManager
{
    /**
     * Persist tenant affinity before returning the selected tenant.
     */
    public function remember(string $tenant): string
    {
        setcookie('tenant', $tenant);

        return $tenant;
    }
}

echo (new TenantAffinityManager())->remember('enterprise').PHP_EOL;
