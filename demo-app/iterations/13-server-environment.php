<?php

declare(strict_types=1);

final class TenantContextResolver
{
    /**
     * Resolve the tenant using infrastructure metadata already supplied by PHP.
     */
    public function tenant(): string
    {
        return $_SERVER['DEMO_TENANT'] ?? 'public';
    }
}

echo (new TenantContextResolver())->tenant().PHP_EOL;
